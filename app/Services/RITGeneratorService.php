<?php

namespace App\Services;

use App\Models\ArticuloLegal;
use App\Models\Empresa;
use App\Services\BibliotecaLegalService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

class RITGeneratorService
{
    /** Modelo que terminó generando el texto. Consultable desde el código llamador. */
    public string $modeloUsado = '';

    /** True solo cuando se llegó al último recurso (flash-lite). */
    public bool $esFallbackLite = false;

    /**
     * Genera el texto completo del RIT usando Gemini, capítulo por capítulo.
     */
    public function generarTextoRIT(array $respuestas, Empresa $empresa): string
    {
        return $this->generarCapitulosRIT($respuestas, $empresa);
    }

    /**
     * Genera el RIT capítulo por capítulo con soporte de callback de progreso.
     * El callback $onProgress recibe ($capActual, $total, $tituloCapitulo).
     */
    public function generarCapitulosRIT(
        array     $respuestas,
        Empresa   $empresa,
        ?\Closure $onProgress = null
    ): string {
        $biblioteca     = app(BibliotecaLegalService::class);
        $capitulos      = self::getCapitulos();
        $total          = count($capitulos);
        $partes         = [];
        $articuloInicio = 1;

        foreach (array_values($capitulos) as $idx => $cap) {
            if ($onProgress) {
                $onProgress($idx + 1, $total, $cap['titulo']);
            }

            Log::info('RITGeneratorService: generando capítulo', [
                'empresa_id'      => $empresa->id,
                'capitulo'        => $cap['numero'],
                'titulo'          => $cap['titulo'],
                'articulo_inicio' => $articuloInicio,
            ]);

            $rag                   = $biblioteca->buscarFragmentos($cap['query_rag'], limite: 8, umbral: 0.30);
            $codigosObligatorios   = $cap['codigos_obligatorios'] ?? [];
            $articulosObligatorios = $this->obtenerArticulosObligatorios($codigosObligatorios);
            $articulosPorTema      = $this->buscarArticulosPorTema(
                $cap['query_rag'],
                $codigosObligatorios,   // excluir los ya obtenidos por código exacto
                8
            );
            // Combinar: los obligatorios primero, luego los encontrados por tema
            $articulosObligatorios = trim($articulosObligatorios . ($articulosPorTema ? "\n\n" . $articulosPorTema : ''));
            $contextoEmpresa       = $this->construirContextoEmpresa($cap, $respuestas, $empresa);

            $prompt = $this->construirPrompt(
                cap:                   $cap,
                contextoEmpresa:       $contextoEmpresa,
                articulosObligatorios: $articulosObligatorios,
                rag:                   $rag,
                articuloInicio:        $articuloInicio,
                empresa:               $empresa,
            );

            $prompt = preg_replace(
                '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
                '', $prompt
            ) ?? iconv('UTF-8', 'UTF-8//IGNORE', $prompt);

            $textoCapitulo = $this->llamarGemini($prompt, $empresa->id);

            if (!$this->validarCapitulo($textoCapitulo, $cap['titulo'])) {
                Log::warning('RITGeneratorService: capítulo inválido, reintentando', [
                    'empresa_id' => $empresa->id,
                    'capitulo'   => $cap['numero'],
                ]);
                $textoCapitulo = $this->llamarGemini($prompt, $empresa->id);
            }

            $partes[] = trim($textoCapitulo);

            preg_match_all('/^ARTÍCULO\s+\d+/imu', $textoCapitulo, $matches);
            $articuloInicio += max(1, count($matches[0]));
        }

        return implode("\n\n", $partes);
    }

    // ── Métodos internos de generación ────────────────────────────────────────

    private function llamarGemini(string $prompt, int $empresaId = 0): string
    {
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? '';

        $modelPrincipal = 'gemini-2.5-flash';
        $modelosCascada = ['gemini-2.5-flash', 'gemini-2.5-flash-lite'];

        // $prompt = $this->construirPrompt($respuestas, $empresa);

        // Limpiar bytes UTF-8 inválidos que provienen de fragmentos de PDFs/DOCX
        // y que rompen json_encode al construir el payload
        $prompt = preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $prompt
        ) ?? iconv('UTF-8', 'UTF-8//IGNORE', $prompt);

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 32768,
                'topP'            => 0.95,
            ],
        ];

        $lastError    = null;
        $totalModelos = count($modelosCascada);

        foreach (array_values($modelosCascada) as $idx => $model) {
            $url         = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $esUltimo    = ($idx === $totalModelos - 1);
            $maxIntentos = 2;
            $esperas     = [10, 30];

            Log::info('RITGeneratorService: llamada Gemini', [
                'empresa_id'     => $empresaId,
                'model'          => $model,
                'intento_modelo' => $idx + 1,
            ]);

            $sobrecarga = false;

            for ($intento = 1; $intento <= $maxIntentos; $intento++) {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(90)
                    ->post($url, $payload);

                if ($response->successful()) {
                    $data  = $response->json();
                    $parts = $data['candidates'][0]['content']['parts'] ?? [];

                    $texto = '';
                    foreach (array_reverse($parts) as $part) {
                        if (empty($part['thought']) && !empty($part['text'])) {
                            $texto = $part['text'];
                            break;
                        }
                    }
                    if (empty($texto)) {
                        $texto = $parts[0]['text'] ?? '';
                    }
                    if (empty($texto)) {
                        throw new \RuntimeException('Respuesta de Gemini sin contenido válido');
                    }

                    $this->modeloUsado    = $model;
                    $this->esFallbackLite = ($model === 'gemini-2.5-flash-lite');

                    if ($idx > 0) {
                        Log::info('RITGeneratorService: usando modelo de respaldo', [
                            'empresa_id'    => $empresaId,
                            'model_usado'   => $model,
                            'model_primario' => $modelPrincipal,
                        ]);
                    }

                    return trim($texto);
                }

                $status    = $response->status();
                $lastError = $response->body();

                $esSobrecarga  = in_array($status, [429, 503]);
                $esTransitorio = in_array($status, [500, 502, 504]);

                Log::warning('RITGeneratorService: fallo en intento', [
                    'empresa_id' => $empresaId,
                    'model'      => $model,
                    'intento'    => $intento,
                    'status'     => $status,
                ]);

                if ($esSobrecarga) {
                    if ($intento < $maxIntentos) {
                        sleep($esperas[$intento - 1]);
                    } else {
                        $sobrecarga = true;
                        break;
                    }
                } elseif ($esTransitorio && $intento < $maxIntentos) {
                    sleep($esperas[$intento - 1]);
                } else {
                    throw new \RuntimeException('Error en API Gemini: ' . $lastError);
                }
            }

            if ($sobrecarga && !$esUltimo) {
                Log::warning('RITGeneratorService: modelo saturado, cascadeando', [
                    'empresa_id'    => $empresaId,
                    'model_fallido' => $model,
                    'model_next'    => $modelosCascada[$idx + 1] ?? 'ninguno',
                ]);
                continue;
            }

            break;
        }

        throw new \RuntimeException('Error en API Gemini (todos los modelos intentados): ' . $lastError);
    }

    public function validarCapitulo(string $texto, string $titulo): bool
    {
        if (strlen(trim($texto)) < 200) {
            Log::warning("RITGeneratorService: capítulo '{$titulo}' demasiado corto");
            return false;
        }
        if (!preg_match('/ARTÍCULO\s+\d+/iu', $texto)) {
            Log::warning("RITGeneratorService: capítulo '{$titulo}' sin artículos detectados");
            return false;
        }
        return true;
    }

    public function obtenerArticulosObligatorios(array $codigos): string
    {
        if (empty($codigos)) return '';

        try {
            // El scraper produce dos formatos: "Art. X CST" y "Artículo. X CST".
            // Se consultan ambos para no perder artículos con prefijo diferente (ej: Art. 236).
            $articuloCodes    = array_map(fn($c) => preg_replace('/^Art\./', 'Artículo.', $c), $codigos);
            $todosLosFormatos = array_unique(array_merge($codigos, $articuloCodes));

            $articulos = ArticuloLegal::whereIn('codigo', $todosLosFormatos)
                ->whereNull('empresa_id')
                ->where('activo', true)
                ->get();

            if ($articulos->isEmpty()) return '';

            // Deduplicar por contenido: el scraper puede haber guardado "Art. 238" y "Artículo. 238" idénticos.
            $vistos    = [];
            $resultado = [];
            foreach ($articulos as $a) {
                $hash = md5(mb_substr($a->texto_completo, 0, 200));
                if (!isset($vistos[$hash])) {
                    $vistos[$hash] = true;
                    $resultado[]   = "--- {$a->codigo}: {$a->titulo} ---\n{$a->texto_completo}";
                }
            }

            return implode("\n\n", $resultado);
        } catch (\Throwable $e) {
            Log::warning('RITGeneratorService: no se pudieron obtener artículos obligatorios', [
                'codigos' => $codigos,
                'error'   => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Busca artículos scrapeados por palabras clave del tema del capítulo.
     * Complementa obtenerArticulosObligatorios() con artículos relevantes
     * que no están en la lista codigos_obligatorios.
     * Excluye los artículos ya obtenidos por código exacto.
     */
    public function buscarArticulosPorTema(string $queryTema, array $yaObtenidos = [], int $limite = 8): string
    {
        // Extraer términos significativos (> 4 chars, sin stopwords básicas)
        $stopwords = ['para', 'como', 'desde', 'hasta', 'sobre', 'entre', 'según', 'cuando', 'donde', 'trabajo'];
        $terminos = array_filter(
            array_unique(explode(' ', mb_strtolower($queryTema))),
            fn($t) => strlen($t) > 4 && !in_array($t, $stopwords)
        );
        $terminos = array_slice(array_values($terminos), 0, 6);

        if (empty($terminos)) return '';

        try {
            $query = \App\Models\ArticuloLegal::whereNull('empresa_id')
                ->where('activo', true);

            if (!empty($yaObtenidos)) {
                $query->whereNotIn('codigo', $yaObtenidos);
            }

            // Al menos un término debe aparecer en título o texto (OR - no AND)
            $query->where(function ($q) use ($terminos) {
                foreach ($terminos as $termino) {
                    $q->orWhereRaw('LOWER(titulo) LIKE ?', ["%{$termino}%"])
                      ->orWhereRaw('LOWER(texto_completo) LIKE ?', ["%{$termino}%"]);
                }
            });

            $articulos = $query->limit($limite)->get();

            if ($articulos->isEmpty()) return '';

            return $articulos
                ->map(fn($a) => "--- {$a->codigo}: {$a->titulo} ---\n{$a->texto_completo}")
                ->implode("\n\n");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('RITGeneratorService: buscarArticulosPorTema falló', [
                'query' => $queryTema,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Búsqueda semántica (vector / cosine similarity) sobre articulos_legales.
     * Usa los embeddings pre-calculados para obtener relevancia real en lugar
     * de coincidencias de palabras clave. Fallback a buscarArticulosPorTema()
     * si el embedding del query falla.
     */
    public function buscarArticulosPorEmbedding(
        string $queryTema,
        array  $yaObtenidos = [],
        int    $limite      = 10,
        float  $umbral      = 0.35,
    ): string {
        $queryEmb = $this->obtenerEmbeddingQuery($queryTema);

        if (empty($queryEmb)) {
            // Fallback a keyword search si la API de embeddings falla
            return $this->buscarArticulosPorTema($queryTema, $yaObtenidos, $limite);
        }

        try {
            // Fase 1: puntuar candidatos cargando solo [id, embedding] (memoria mínima).
            $q = ArticuloLegal::whereNull('empresa_id')
                ->where('activo', true)
                ->whereNotNull('embedding');

            if (!empty($yaObtenidos)) {
                $q->whereNotIn('codigo', $yaObtenidos);
            }

            $top = \App\Support\VectorSearch::topK($q, $queryEmb, $limite, $umbral);

            if (empty($top)) {
                return $this->buscarArticulosPorTema($queryTema, $yaObtenidos, $limite);
            }

            // Fase 2: hidratar el texto completo SOLO del top-K, conservando el orden por score.
            $ids       = array_column($top, 'key');
            $articulos = ArticuloLegal::whereIn('id', $ids)
                ->get(['id', 'codigo', 'titulo', 'texto_completo'])
                ->keyBy('id');

            $out = [];
            foreach ($top as $t) {
                $a = $articulos[$t['key']] ?? null;
                if ($a) {
                    $out[] = "--- {$a->codigo}: {$a->titulo} ---\n{$a->texto_completo}";
                }
            }

            return implode("\n\n", $out);

        } catch (\Throwable $e) {
            Log::warning('RITGeneratorService: buscarArticulosPorEmbedding falló', [
                'query' => $queryTema,
                'error' => $e->getMessage(),
            ]);
            return $this->buscarArticulosPorTema($queryTema, $yaObtenidos, $limite);
        }
    }

    /**
     * Obtiene embedding de una query (taskType RETRIEVAL_QUERY) con caché de 24h.
     */
    private function obtenerEmbeddingQuery(string $texto): ?array
    {
        $apiKey   = config('services.ia.gemini.api_key', '');
        $cacheKey = 'emb_q_' . md5($texto);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(24), function () use ($texto, $apiKey) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key=' . $apiKey;
            try {
                $response = Http::timeout(20)->post($url, [
                    'model'    => 'models/gemini-embedding-001',
                    'content'  => ['parts' => [['text' => mb_substr($texto, 0, 8000)]]],
                    'taskType' => 'RETRIEVAL_QUERY',
                ]);
                if (!$response->successful()) return null;
                $values = $response->json('embedding.values');
                return is_array($values) && !empty($values) ? $values : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = $magA = $magB = 0.0;
        $n   = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot  += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }
        $denom = sqrt($magA) * sqrt($magB);
        return $denom > 0.0 ? $dot / $denom : 0.0;
    }

    public function construirContextoEmpresa(array $cap, array $respuestas, Empresa $empresa): string
    {
        $lista = fn($arr) => is_array($arr) ? implode(', ', array_filter($arr)) : ($arr ?? '');

        $lineas = [
            'DATOS DE LA EMPRESA:',
            '- Razón social: ' . $empresa->nombre_completo,
            '- NIT: ' . $empresa->nit,
            '- Tipo societario: ' . ($empresa->tipo_societario ?? 'No especificado'),
            '- Representante Legal: ' . ($empresa->representante_legal ?? ''),
            '- Fecha de elaboración: ' . now()->locale('es')->translatedFormat('j \d\e F \d\e Y'),
        ];

        foreach ($cap['datos_empresa_keys'] as $key) {
            $val = $respuestas[$key] ?? null;

            // El cuestionario guarda la actividad económica como texto libre;
            // si el analista no la diligenció ahí, se recurre al dato real y
            // estructurado de Empresa.actividad_economica_id (misma fuente ya
            // usada en SolicitudContratoIAService::completarDetallesCargo()),
            // en vez de dejar que la IA invente un placeholder entre corchetes
            // - bug real confirmado en producción (RIT mejorado de RENBEL
            // S.A.S.: "[Actividad Económica Principal de RENBEL S.A.S.]" en
            // el texto final, pese a la prohibición explícita de corchetes).
            if (($val === null || $val === '') && $key === 'actividad_economica') {
                $val = $empresa->actividadEconomica?->nombre;
            }
            if (($val === null || $val === '') && $key === 'actividades_secundarias') {
                $val = $empresa->actividadesSecundarias->pluck('nombre')->filter()->implode('; ') ?: null;
            }

            // Mismo patrón: 'num_trabajadores' y 'domicilio' del cuestionario
            // tienen equivalente real en Empresa (numero_empleados /
            // dirección+ciudad+departamento) - verificado empíricamente que
            // sin este fallback ambos se omitían en silencio igual que la
            // actividad económica, con el mismo riesgo de que la IA rellene
            // con un placeholder entre corchetes.
            if (($val === null || $val === '') && $key === 'num_trabajadores') {
                $val = $empresa->numero_empleados;
            }
            if (($val === null || $val === '') && $key === 'domicilio') {
                $val = trim(collect([$empresa->direccion, $empresa->ciudad, $empresa->departamento])->filter()->implode(', '), ', ') ?: null;
            }

            if ($val === null || $val === '') continue;

            if ($key === 'cargos') {
                $txt = '';
                foreach ((array) $val as $c) {
                    $nombre   = $c['nombre_cargo'] ?? '';
                    $sanciona = ($c['puede_sancionar'] ?? false) ? 'puede sancionar' : 'no sanciona';
                    if ($nombre) $txt .= "  - {$nombre} ({$sanciona})\n";
                }
                $lineas[] = "- Cargos:\n{$txt}";
            } elseif ($key === 'sucursales') {
                $txt = '';
                foreach ((array) $val as $s) {
                    $ciudad = $s['ciudad'] ?? '';
                    $dir    = $s['direccion'] ?? '';
                    $trab   = $s['num_trabajadores'] ?? '';
                    if ($ciudad) $txt .= "  - {$ciudad}: {$dir}, {$trab} trabajadores\n";
                }
                $lineas[] = "- Sucursales:\n{$txt}";
            } elseif ($key === 'beneficios_extralegales') {
                $txt = '';
                foreach ((array) $val as $b) {
                    $nb = $b['nombre_beneficio'] ?? '';
                    $db = $b['descripcion'] ?? '';
                    if ($nb) $txt .= "  - {$nb}: {$db}\n";
                }
                $lineas[] = "- Beneficios extralegales:\n" . ($txt ?: "  - Ninguno\n");
            } elseif ($key === 'sanciones_configuradas') {
                $leves     = array_filter((array) $val, fn($s) => ($s['tipo_falta'] ?? '') === 'leve');
                $graves    = array_filter((array) $val, fn($s) => ($s['tipo_falta'] ?? '') === 'grave');
                $muyGraves = array_filter((array) $val, fn($s) => ($s['tipo_falta'] ?? '') === 'muy_grave');
                $fmtS   = fn(array $s): string => match ($s['tipo_sancion'] ?? '') {
                    'llamado_atencion' => 'llamado de atención',
                    'suspension'       => 'suspensión' . (!empty($s['dias_suspension']) ? ' de ' . $s['dias_suspension'] . ' días' : ''),
                    'terminacion'      => 'terminación del contrato',
                    default            => $s['tipo_sancion'] ?? '',
                };
                $txt = '';
                foreach ($leves     as $s) $txt .= "  - [leve] {$s['nombre']}: {$fmtS($s)}\n";
                foreach ($graves    as $s) $txt .= "  - [grave] {$s['nombre']}: {$fmtS($s)}\n";
                foreach ($muyGraves as $s) $txt .= "  - [muy grave] {$s['nombre']}: {$fmtS($s)}\n";
                if ($txt) $lineas[] = "- Sanciones configuradas:\n{$txt}";
            } elseif (is_array($val)) {
                $lineas[] = "- {$key}: " . $lista($val);
            } else {
                $lineas[] = "- {$key}: {$val}";
            }
        }

        return implode("\n", $lineas);
    }

    private function construirPrompt(
        array   $cap,
        string  $contextoEmpresa,
        string  $articulosObligatorios,
        string  $rag,
        int     $articuloInicio,
        Empresa $empresa,
    ): string {
        $numero  = $cap['numero'];
        $titulo  = $cap['titulo'];
        $instr   = $cap['instrucciones'];

        // Estándar de oro: elementos obligatorios que este capítulo debe cubrir (contenido curado).
        $goldItems  = \App\Support\RitGoldStandard::paraCapitulo($numero);
        $goldBloque = $goldItems
            ? "\nELEMENTOS OBLIGATORIOS QUE DEBE CUBRIR ESTE CAPÍTULO (no dejes ninguno por fuera; desarróllalos con la profundidad de un abogado experto):\n"
              . \App\Support\RitGoldStandard::comoLista($goldItems) . "\n"
            : '';

        $seccionArticulos = $articulosObligatorios
            ? "═══════════════════════════════════════════════════\n"
              . "TEXTO OFICIAL DE ARTÍCULOS DEL CST (fuente: base de datos interna)\n"
              . "Reproduce el contenido de estos artículos con fidelidad. Puedes citar su número.\n"
              . "═══════════════════════════════════════════════════\n"
              . $articulosObligatorios . "\n"
            : '';

        $seccionBiblioteca = $rag
            ? "═══════════════════════════════════════════════════\n"
              . "FRAGMENTOS DE LA BIBLIOTECA JURÍDICA INTERNA\n"
              . "Puedes citar artículos y leyes que aparezcan textualmente en estos fragmentos.\n"
              . "═══════════════════════════════════════════════════\n"
              . $rag . "\n"
            : '';

        $razonSocial = $empresa->nombre_completo;

        $advertenciaLegal = (!$articulosObligatorios && !$rag)
            ? "ADVERTENCIA: No hay contexto jurídico disponible para este capítulo. "
              . "Redacta el contenido temático completo SIN citar números de artículo ni nombres de ley.\n"
            : '';

        return <<<PROMPT
Eres un abogado laboral colombiano experto en Reglamentos Internos de Trabajo.

Redacta ÚNICAMENTE el CAPÍTULO {$numero} ({$titulo}) del RIT de "{$razonSocial}".
Los artículos comienzan desde ARTÍCULO {$articuloInicio}.

REGLA FUNDAMENTAL - ANTI-ALUCINACIÓN (INCUMPLIRLA INVALIDA EL DOCUMENTO):
PROHIBICIÓN ABSOLUTA: NUNCA menciones ningún número de artículo, nombre de ley, decreto,
resolución, sentencia, porcentaje, plazo en días/semanas/meses, ni salario mínimo concreto
que NO aparezca literalmente en la sección "ARTÍCULOS OFICIALES" proporcionada abajo.
ESTO INCLUYE todo lo que recuerdes de tu entrenamiento, aunque estés completamente seguro
de que es correcto. "Ley 2101 de 2021", "Art. 167A", "18 semanas", "Resolución 652/2012",
"Decreto 1072/2015" - NINGUNO puede aparecer en el texto generado a menos que esté
literalmente en el contexto proporcionado.
CUANDO QUIERAS CITAR algo que no está en el contexto proporcionado: escribe la obligación
en términos generales. Ejemplos CORRECTOS: "conforme a la normativa vigente", "según la
ley laboral aplicable", "de acuerdo con el contexto jurídico que rige esta materia".
ÚNICO ORIGEN VÁLIDO de cualquier cifra, artículo, ley o porcentaje específico:
los textos que aparecen en la sección ARTÍCULOS OFICIALES más abajo.

ESTÁNDAR DE CALIDAD (debes igualar o SUPERAR el trabajo de un abogado experto):
- Sé EXHAUSTIVO: desarrolla todos los sub-temas que abarcan las INSTRUCCIONES TEMÁTICAS y los
  ARTÍCULOS OFICIALES; nada de artículos genéricos ni vacíos de contenido.
- Redacta de forma GARANTISTA: protege el debido proceso y los derechos del trabajador.
- Prefiere el detalle práctico (procedimientos, plazos, responsables) SIEMPRE que la fuente lo
  respalde; si la fuente no lo trae, exprésalo en términos generales (nunca inventes cifras ni normas).

INSTRUCCIONES TEMÁTICAS DE ESTE CAPÍTULO:
{$instr}
{$goldBloque}
REGLAS DE FORMATO - OBLIGATORIAS:
1. Inicia con CAPÍTULO {$numero} (primera línea) y {$titulo} (segunda línea), ambas en MAYÚSCULAS.
2. Cada artículo en su propia línea: ARTÍCULO N. NOMBRE. Texto completo (mínimo 60 palabras).
3. Párrafos adicionales de un artículo: líneas a continuación sin prefijo ARTÍCULO.
4. PARÁGRAFO en línea propia: PARÁGRAFO PRIMERO. Texto.
5. Listas dentro de artículo: 1) texto  2) texto  (en líneas separadas).
6. TABLAS cuando corresponda (horarios, escalas de sanciones, etapas disciplinarias):
   TABLA:
   ENCABEZADO: Col1 | Col2
   FILA: dato1 | dato2
   FIN_TABLA
7. Sin Markdown: sin *, sin #, sin **.
8. NUNCA uses corchetes ni placeholders. Usa los datos reales de la empresa.
9. Devuelve SOLO el texto del capítulo.

{$advertenciaLegal}
{$seccionArticulos}
{$seccionBiblioteca}
{$contextoEmpresa}

Comienza ahora con "CAPÍTULO {$numero}":
PROMPT;
    }

    /**
     * Genera el documento Word (.docx) con el texto del RIT.
     * Retorna la ruta relativa dentro de storage/app/private/.
     */
    /**
     * Genera el DOCX y lo guarda en storage/app/private/rits/{id}/reglamento.docx.
     * Retorna la ruta relativa. Lanza excepción si no puede escribir.
     */
    public function generarDocumentoWord(string $textoRIT, Empresa $empresa): string
    {
        $directorio = "private/rits/{$empresa->id}";
        Storage::makeDirectory($directorio);

        $rutaRelativa = "{$directorio}/reglamento.docx";
        $rutaAbsoluta = storage_path("app/{$rutaRelativa}");

        $this->escribirDocx($textoRIT, $empresa, $rutaAbsoluta);

        Log::info('RITGeneratorService: documento Word guardado', [
            'empresa_id' => $empresa->id,
            'ruta'       => $rutaRelativa,
        ]);

        return $rutaRelativa;
    }

    /**
     * Genera un PDF del RIT en un archivo temporal.
     * Intenta primero con LibreOffice (DOCX → PDF, calidad Word real).
     * Fallback: DomPDF si LibreOffice no está disponible.
     *
     * @param  bool  $proteger  Cifrar el PDF (solo impresión, sin copia/edición).
     *   Solo para RIT producidos por la IA. Los RIT subidos por el cliente son
     *   su propio documento y se entregan SIN protección.
     */
    public function generarPDFTemp(string $textoRIT, Empresa $empresa, bool $proteger = true): string
    {
        $loPath = $this->detectarLibreOffice();

        if ($loPath) {
            try {
                return $this->generarPDFviaLibreOffice($textoRIT, $empresa, $loPath, $proteger);
            } catch (\Exception $e) {
                Log::warning('RITGeneratorService: LibreOffice falló, fallback a DomPDF', [
                    'empresa_id' => $empresa->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $this->generarPDFviaDomPDF($textoRIT, $empresa, $proteger);
    }

    /** Detecta la ruta de LibreOffice según el SO. */
    private function detectarLibreOffice(): ?string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            foreach (['/usr/bin/soffice', '/usr/local/bin/soffice', '/snap/bin/soffice'] as $p) {
                if (file_exists($p)) return $p;
            }
            return null;
        }
        foreach ([
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ] as $p) {
            if (file_exists($p)) return $p;
        }
        return null;
    }

    /** Genera PDF usando LibreOffice: escribe DOCX y lo convierte. */
    private function generarPDFviaLibreOffice(string $textoRIT, Empresa $empresa, string $loPath, bool $proteger = true): string
    {
        $uid    = uniqid('rit_', true);
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $uid;
        mkdir($tmpDir, 0755, true);

        $docxPath = $tmpDir . DIRECTORY_SEPARATOR . 'reglamento.docx';
        $pdfPath  = $tmpDir . DIRECTORY_SEPARATOR . 'reglamento.pdf';

        $this->escribirDocx($textoRIT, $empresa, $docxPath);

        // Perfil de usuario único para evitar conflictos de instancia concurrente
        // file:/// (3 slashes) es necesario para rutas absolutas en Windows y Linux
        $profileDir = str_replace('\\', '/', $tmpDir . '/lo_profile');
        $loProfile  = 'file:///' . ltrim($profileDir, '/');

        // Exportar con restricciones SOLO si se pide proteger (RIT de IA):
        // contraseña de propietario + solo impresión, sin contraseña de apertura.
        // Los RIT subidos por el cliente se convierten sin cifrado.
        if ($proteger) {
            $ownerPass = $this->ownerPassword($empresa);
            $filterData = json_encode([
                'EncryptFile'                            => ['type' => 'boolean', 'value' => 'true'],
                'PermissionPassword'                     => ['type' => 'string',  'value' => $ownerPass],
                'Printing'                               => ['type' => 'long',    'value' => '2'],  // 2 = alta resolución
                'Changes'                                => ['type' => 'long',    'value' => '0'],  // 0 = ningún cambio
                'EnableCopyingOfContent'                 => ['type' => 'boolean', 'value' => 'false'],
                'EnableTextAccessForAccessibilityTools'  => ['type' => 'boolean', 'value' => 'false'],
            ], JSON_UNESCAPED_SLASHES);
            $convertTo = 'pdf:writer_pdf_Export:' . $filterData;
        } else {
            $convertTo = 'pdf';
        }

        $cmd = [
            $loPath,
            '--headless',
            '--nofirststartwizard',
            '-env:UserInstallation=' . $loProfile,
            '--convert-to', $convertTo,
            '--outdir', $tmpDir,
            $docxPath,
        ];

        // Usar proc_open con timeout de 60s para evitar bloqueos indefinidos en Windows
        $process = proc_open(
            $cmd,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if (!is_resource($process)) {
            $this->limpiarDir($tmpDir);
            throw new \RuntimeException('No se pudo iniciar el proceso LibreOffice');
        }

        fclose($pipes[0]);

        $timeout  = 60;  // segundos
        $deadline = microtime(true) + $timeout;
        $output   = '';

        // Lectura no bloqueante con timeout
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $code = null;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $code = $status['exitcode'];
                break;
            }
            $output .= stream_get_contents($pipes[1]);
            $output .= stream_get_contents($pipes[2]);
            usleep(200_000); // 200ms
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        if ($code === null) {
            // Timeout: matar el proceso
            proc_terminate($process);
            proc_close($process);
            $this->limpiarDir($tmpDir);
            throw new \RuntimeException('LibreOffice superó el tiempo límite de ' . $timeout . 's');
        }

        proc_close($process);

        if ($code !== 0 || !file_exists($pdfPath)) {
            $this->limpiarDir($tmpDir);
            throw new \RuntimeException(
                'LibreOffice no convirtió el DOCX (código ' . $code . '): ' . trim($output)
            );
        }

        // Garantía de protección (solo RIT de IA): si LibreOffice no cifró el PDF
        // (filtro no soportado en esta versión), abortar para que generarPDFTemp caiga
        // al fallback DomPDF, que sí cifra. Así NUNCA se entrega un RIT de IA sin proteger.
        if ($proteger && !$this->pdfEstaCifrado($pdfPath)) {
            $this->limpiarDir($tmpDir);
            throw new \RuntimeException('LibreOffice generó el PDF sin cifrado; se usará DomPDF');
        }

        $finalPath = tempnam(sys_get_temp_dir(), 'rit_') . '.pdf';
        copy($pdfPath, $finalPath);
        $this->limpiarDir($tmpDir);

        Log::info('RITGeneratorService: PDF generado con LibreOffice', [
            'empresa_id' => $empresa->id,
            'protegido'  => $proteger,
        ]);

        return $finalPath;
    }

    /** Contraseña de propietario determinística por empresa (no se expone al usuario). */
    private function ownerPassword(Empresa $empresa): string
    {
        return substr(hash('sha256', config('app.key') . $empresa->id . 'rit'), 0, 32);
    }

    /** Comprueba si un PDF está cifrado (contiene el diccionario /Encrypt). */
    private function pdfEstaCifrado(string $rutaPdf): bool
    {
        $contenido = @file_get_contents($rutaPdf);
        return $contenido !== false && str_contains($contenido, '/Encrypt');
    }

    /** Fallback: genera PDF con DomPDF desde HTML. */
    private function generarPDFviaDomPDF(string $textoRIT, Empresa $empresa, bool $proteger = true): string
    {
        $html = $this->textoAHtml($textoRIT, $empresa);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        // Cifrar solo RIT de IA (solo impresión). Los subidos por el cliente, sin protección.
        if ($proteger) {
            \App\Support\PdfProteccion::proteger($dompdf, $this->ownerPassword($empresa));
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'rit_') . '.pdf';
        file_put_contents($tmpPath, $dompdf->output());

        return $tmpPath;
    }

    /** Elimina todos los archivos y el directorio temporal. */
    private function limpiarDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $f) {
            is_dir($f) ? $this->limpiarDir($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    /**
     * Convierte el texto plano del RIT a HTML profesional para DOMPDF.
     * Genera portada, encabezados de capítulo, artículos, parágrafos y listas con diseño formal.
     */
    private function textoAHtml(string $textoRIT, Empresa $empresa): string
    {
        $eNombre        = htmlspecialchars($empresa->nombre_completo ?? $empresa->razon_social ?? '', ENT_QUOTES, 'UTF-8');
        $eNit           = htmlspecialchars($empresa->nit ?? '', ENT_QUOTES, 'UTF-8');
        $eRepresentante = htmlspecialchars($empresa->representante_legal ?? '', ENT_QUOTES, 'UTF-8');
        $eCiudad        = htmlspecialchars($empresa->ciudad ?? '', ENT_QUOTES, 'UTF-8');
        $eDpto          = htmlspecialchars($empresa->departamento ?? '', ENT_QUOTES, 'UTF-8');
        $eDireccion     = htmlspecialchars($empresa->direccion ?? '', ENT_QUOTES, 'UTF-8');
        $eTelefono      = htmlspecialchars($empresa->telefono ?? '', ENT_QUOTES, 'UTF-8');
        $eEmail         = htmlspecialchars($empresa->email_contacto ?? '', ENT_QUOTES, 'UTF-8');
        $eLugar         = trim($eCiudad . ($eDpto ? ', ' . $eDpto : ''));

        $fLine1 = implode('. ', array_filter([$eDireccion, $eLugar]));
        $fLine2 = implode('   ', array_filter([
            $eTelefono ? 'Tel. ' . $eTelefono : '',
            $eEmail    ? 'Email. ' . $eEmail   : '',
        ]));

        // Eliminar la primera línea si Gemini repite el título (evita duplicado con el header HTML)
        $textoRIT = ltrim($textoRIT);
        if (preg_match('/^REGLAMENTO\s+INTERNO\s+DE\s+TRABAJO/iu', $textoRIT)) {
            $textoRIT = ltrim(substr($textoRIT, strpos($textoRIT, "\n")), "\r\n");
        }

        $cuerpo     = '';
        $enLista    = false;
        $enTabla    = false;
        $tablaHdr   = null;
        $tablaRows  = [];
        $lastCapNum = false;

        foreach (explode("\n", $textoRIT) as $linea) {
            $linea = rtrim($linea);

            // ── INICIO DE TABLA ───────────────────────────────────────────────
            if (preg_match('/^TABLA:/iu', $linea)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $enTabla   = true;
                $tablaHdr  = null;
                $tablaRows = [];
                $lastCapNum = false;
                continue;
            }

            // ── DENTRO DE TABLA ───────────────────────────────────────────────
            if ($enTabla) {
                if (preg_match('/^ENCABEZADO:\s*(.+)$/iu', $linea, $m)) {
                    $tablaHdr = array_map('trim', explode('|', $m[1]));
                } elseif (preg_match('/^FILA:\s*(.+)$/iu', $linea, $m)) {
                    $tablaRows[] = array_map('trim', explode('|', $m[1]));
                } elseif (preg_match('/^FIN_TABLA/iu', $linea)) {
                    $enTabla = false;
                    $cuerpo .= '<table class="rit-tbl">';
                    if ($tablaHdr) {
                        $cuerpo .= '<tr>';
                        foreach ($tablaHdr as $th) {
                            $cuerpo .= '<th>' . htmlspecialchars($th, ENT_QUOTES, 'UTF-8') . '</th>';
                        }
                        $cuerpo .= '</tr>';
                    }
                    foreach ($tablaRows as $fila) {
                        $cuerpo .= '<tr>';
                        foreach ($fila as $td) {
                            $cuerpo .= '<td>' . htmlspecialchars($td, ENT_QUOTES, 'UTF-8') . '</td>';
                        }
                        $cuerpo .= '</tr>';
                    }
                    $cuerpo .= '</table>';
                }
                // ignorar cualquier otra línea dentro de la tabla
                continue;
            }

            if (trim($linea) === '') {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $lastCapNum = false;
                continue;
            }

            $linea = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $linea);
            $linea = ltrim($linea, '-*# ');
            $linea = rtrim($linea);

            // ── CAPÍTULO con separador: CAPÍTULO I - TÍTULO ───────────────────
            if (preg_match('/^(CAPÍTULO\s+[IVXLCDM]+)\s*[-–\-]+\s*(.+)$/iu', $linea, $m)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $cuerpo .= '<p class="cap-num">' . htmlspecialchars(strtoupper($m[1]), ENT_QUOTES, 'UTF-8') . '</p>'
                         . '<p class="cap-tit">' . htmlspecialchars(strtoupper(trim($m[2])), ENT_QUOTES, 'UTF-8') . '</p>';
                $lastCapNum = false;
                continue;
            }

            // ── CAPÍTULO solo (la siguiente línea será el título) ─────────────
            if (preg_match('/^CAPÍTULO\s+[IVXLCDM]+\.?\s*$/iu', $linea)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $cuerpo    .= '<p class="cap-num">' . htmlspecialchars(strtoupper(trim($linea)), ENT_QUOTES, 'UTF-8') . '</p>';
                $lastCapNum = true;
                continue;
            }

            // ── Título del capítulo (línea inmediatamente después de CAPÍTULO) ─
            if ($lastCapNum) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $cuerpo    .= '<p class="cap-tit">' . htmlspecialchars(strtoupper(trim($linea)), ENT_QUOTES, 'UTF-8') . '</p>';
                $lastCapNum = false;
                continue;
            }
            $lastCapNum = false;

            // ── ARTÍCULO: título en negrita, cuerpo en normal ─────────────────
            if (preg_match('/^(ARTÍCULO\s+\d+\.[^.]+\.)\s*(.*)$/iu', $linea, $mArt)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $tArt = htmlspecialchars(trim($mArt[1]), ENT_QUOTES, 'UTF-8');
                $bArt = htmlspecialchars(trim($mArt[2] ?? ''), ENT_QUOTES, 'UTF-8');
                $cuerpo .= '<p class="art"><strong>' . $tArt . '</strong>'
                         . ($bArt !== '' ? ' ' . $bArt : '') . '</p>';
                continue;
            }

            // ── PARÁGRAFO: etiqueta en negrita, cuerpo en normal ──────────────
            if (preg_match('/^(PARÁGRAFO(?:\s+(?:ÚNICO|PRIMERO|SEGUNDO|TERCERO|CUARTO|\d+))?\s*[:.])(.*)$/iu', $linea, $mPar)) {
                if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
                $tPar = htmlspecialchars(trim($mPar[1]), ENT_QUOTES, 'UTF-8');
                $bPar = htmlspecialchars(trim($mPar[2] ?? ''), ENT_QUOTES, 'UTF-8');
                $cuerpo .= '<p class="paragrafo"><strong>' . $tPar . '</strong>'
                         . ($bPar !== '' ? ' ' . $bPar : '') . '</p>';
                continue;
            }

            // ── Sub-ítems numerados: 1) o a) ─────────────────────────────────
            if (preg_match('/^\s*(\d+|[a-zA-Z])\)\s+(.+)$/', $linea, $m)) {
                if (!$enLista) { $cuerpo .= '<div class="lista">'; $enLista = true; }
                $cuerpo .= '<div class="lista-item">'
                         . '<span class="lista-marc">' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . ')</span>'
                         . '<span class="lista-txt">'  . htmlspecialchars(trim($m[2]), ENT_QUOTES, 'UTF-8') . '</span>'
                         . '</div>';
                continue;
            }

            // ── Viñetas: • ────────────────────────────────────────────────────
            if (preg_match('/^\s*[•·▪▸]\s+(.+)$/', $linea, $m)) {
                if (!$enLista) { $cuerpo .= '<div class="lista">'; $enLista = true; }
                $cuerpo .= '<div class="lista-item">'
                         . '<span class="lista-marc">•</span>'
                         . '<span class="lista-txt">' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</span>'
                         . '</div>';
                continue;
            }

            // ── Cuerpo genérico ───────────────────────────────────────────────
            if ($enLista) { $cuerpo .= '</div>'; $enLista = false; }
            $cuerpo .= '<p class="body">' . htmlspecialchars($linea, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        if ($enLista) { $cuerpo .= '</div>'; }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 2.5cm 3cm; }
* { box-sizing: border-box; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    line-height: 1.08;
    color: #000000;
}
.titulo {
    text-align: center;
    font-weight: bold;
    font-size: 9pt;
    margin-top: 0;
    margin-bottom: 8pt;
}
.cap-num {
    text-align: center;
    font-weight: bold;
    font-size: 9pt;
    margin-top: 16pt;
    margin-bottom: 0;
    page-break-after: avoid;
}
.cap-tit {
    text-align: center;
    font-weight: bold;
    font-size: 9pt;
    margin-top: 0;
    margin-bottom: 8pt;
    page-break-before: avoid;
}
.art {
    text-align: justify;
    font-weight: normal;
    font-size: 9pt;
    margin-top: 0;
    margin-bottom: 8pt;
}
.paragrafo {
    text-align: justify;
    font-weight: normal;
    font-size: 9pt;
    margin-top: 0;
    margin-bottom: 8pt;
    margin-left: 14pt;
}
.body {
    text-align: justify;
    font-weight: normal;
    font-size: 9pt;
    margin-top: 0;
    margin-bottom: 8pt;
}
.lista { margin-left: 14pt; margin-bottom: 0; }
.lista-item { display: table; width: 100%; font-size: 9pt; margin-bottom: 4pt; }
.lista-marc { display: table-cell; width: 14pt; vertical-align: top; }
.lista-txt  { display: table-cell; text-align: justify; vertical-align: top; }
.rit-tbl {
    width: 100%;
    border-collapse: collapse;
    margin-top: 6pt;
    margin-bottom: 8pt;
    font-size: 9pt;
}
.rit-tbl th {
    border: 0.5pt solid #000000;
    padding: 3pt 5pt;
    font-weight: bold;
    text-align: center;
    vertical-align: middle;
}
.rit-tbl td {
    border: 0.5pt solid #000000;
    padding: 3pt 5pt;
    text-align: left;
    vertical-align: top;
}
</style>
</head>
<body>

<p class="titulo">REGLAMENTO INTERNO DE TRABAJO DE {$eNombre}</p>

{$cuerpo}

</body>
</html>
HTML;
    }

    /**
     * Genera el DOCX en un archivo temporal del sistema y retorna su ruta absoluta.
     * Usar para descargas en servidores con permisos restringidos en storage.
     */
    public function generarDocumentoWordTemp(string $textoRIT, Empresa $empresa): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'rit_') . '.docx';
        $this->escribirDocx($textoRIT, $empresa, $tmpPath);
        return $tmpPath;
    }

    /**
     * Genera el DOCX, lo almacena en el disco 'public' y retorna la ruta relativa.
     * Usa un temp file intermedio para evitar errores de permisos en directorios storage.
     * Retorna null si no pudo guardar en disco.
     */
    public function guardarDocxPublico(string $textoRIT, Empresa $empresa): ?string
    {
        try {
            $tmpPath     = tempnam(sys_get_temp_dir(), 'rit_') . '.docx';
            $this->escribirDocx($textoRIT, $empresa, $tmpPath);

            $rutaPublica = "rits/{$empresa->id}/reglamento.docx";
            Storage::disk('public')->put($rutaPublica, file_get_contents($tmpPath));
            @unlink($tmpPath);

            return $rutaPublica;
        } catch (\Throwable $e) {
            Log::warning('RITGeneratorService: no se pudo guardar DOCX en disco público', [
                'empresa_id' => $empresa->id,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function escribirDocx(string $textoRIT, Empresa $empresa, string $rutaAbsoluta): void
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(9);

        // Estilos comunes - igual que SERVISOM: interlineado sencillo (240 twips), 8pt after
        $fNorm = ['name' => 'Arial', 'size' => 9];
        $fBold = ['name' => 'Arial', 'size' => 9, 'bold' => true];
        $fSmal = ['name' => 'Arial', 'size' => 8];

        $pBody = ['alignment' => Jc::BOTH,   'spaceAfter' => 160, 'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240];
        $pCap  = ['alignment' => Jc::CENTER,  'spaceAfter' => 0,   'spaceBefore' => 280, 'lineRule' => 'auto', 'line' => 240];
        $pCapT = ['alignment' => Jc::CENTER,  'spaceAfter' => 160, 'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240];
        $pArt  = ['alignment' => Jc::BOTH,   'spaceAfter' => 0,   'spaceBefore' => 160, 'lineRule' => 'auto', 'line' => 240];
        $pPar  = ['alignment' => Jc::BOTH,   'spaceAfter' => 0,   'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240, 'indentation' => ['left' => Converter::cmToTwip(0.7)]];
        $pR    = ['alignment' => Jc::RIGHT,  'spaceAfter' => 0,   'spaceBefore' => 0,   'lineRule' => 'auto', 'line' => 240];

        $section = $phpWord->addSection([
            'paperSize'    => 'A4',
            'marginTop'    => Converter::cmToTwip(2.5),
            'marginBottom' => Converter::cmToTwip(2.5),
            'marginLeft'   => Converter::cmToTwip(3.0),
            'marginRight'  => Converter::cmToTwip(3.0),
        ]);

        // Footer con datos de la empresa (alineado a la derecha, igual que el PDF)
        $footer  = $section->addFooter();
        $eLugar  = trim(($empresa->ciudad ?? '') . (($empresa->departamento ?? '') ? ', ' . $empresa->departamento : ''));
        $fLine1  = implode('. ', array_filter([$empresa->direccion ?? '', $eLugar]));
        $fLine2  = implode('   ', array_filter([
            ($empresa->telefono       ?? '') ? 'Tel. '   . $empresa->telefono       : '',
            ($empresa->email_contacto ?? '') ? 'Email. ' . $empresa->email_contacto : '',
        ]));
        if ($fLine1) $footer->addText(htmlspecialchars($fLine1), $fSmal, $pR);
        if ($fLine2) $footer->addText(htmlspecialchars($fLine2), $fSmal, $pR);

        // Título principal
        $eNombre = strtoupper($empresa->nombre_completo ?? $empresa->razon_social ?? '');
        $section->addText(
            "REGLAMENTO INTERNO DE TRABAJO DE {$eNombre}",
            $fBold,
            ['alignment' => Jc::CENTER, 'spaceAfter' => 160, 'spaceBefore' => 0, 'lineRule' => 'auto', 'line' => 240]
        );

        // Eliminar la primera línea si Gemini repite el título (evita duplicado con el addText del título)
        $textoRIT = ltrim($textoRIT);
        if (preg_match('/^REGLAMENTO\s+INTERNO\s+DE\s+TRABAJO/iu', $textoRIT)) {
            $textoRIT = ltrim(substr($textoRIT, strpos($textoRIT, "\n")), "\r\n");
        }

        // Parser - idéntica lógica a textoAHtml()
        $lastCapNum = false;

        foreach (explode("\n", $textoRIT) as $linea) {
            $linea = rtrim($linea);

            if (trim($linea) === '') {
                $lastCapNum = false;
                continue; // no añadir saltos de línea extras; el spaceAfter ya separa
            }

            // Limpiar markdown
            $linea = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $linea);
            $linea = ltrim($linea, '-*# ');
            $linea = rtrim($linea);
            if ($linea === '') continue;

            // ── TABLA ──────────────────────────────────────────────────────────
            // (las tablas en DOCX se omiten por complejidad; se dejan como texto)
            if (preg_match('/^(TABLA:|ENCABEZADO:|FILA:|FIN_TABLA)/iu', $linea)) {
                continue;
            }

            // ── CAPÍTULO X - TÍTULO (una sola línea) ──────────────────────────
            if (preg_match('/^(CAPÍTULO\s+[IVXLCDM]+)\s*[-–\-]+\s*(.+)$/iu', $linea, $m)) {
                $section->addText(strtoupper($m[1]), $fBold, $pCap);
                $section->addText(strtoupper(trim($m[2])), $fBold, $pCapT);
                $lastCapNum = false;
                continue;
            }

            // ── CAPÍTULO X solo (siguiente línea = título) ────────────────────
            if (preg_match('/^CAPÍTULO\s+[IVXLCDM]+\.?\s*$/iu', $linea)) {
                $section->addText(strtoupper(trim($linea)), $fBold, $pCap);
                $lastCapNum = true;
                continue;
            }

            // ── Título del capítulo ───────────────────────────────────────────
            if ($lastCapNum) {
                $section->addText(strtoupper(trim($linea)), $fBold, $pCapT);
                $lastCapNum = false;
                continue;
            }
            $lastCapNum = false;

            // ── ARTÍCULO: título en negrita, cuerpo en normal ─────────────────
            if (preg_match('/^(ARTÍCULO\s+\d+\.[^.]+\.)\s*(.*)$/iu', $linea, $mArt)) {
                $tArt = trim($mArt[1]);
                $bArt = trim($mArt[2] ?? '');
                $run  = $section->addTextRun($pArt);
                $run->addText($tArt . ($bArt !== '' ? ' ' : ''), $fBold);
                if ($bArt !== '') {
                    $run->addText($bArt, $fNorm);
                }
                continue;
            }

            // ── PARÁGRAFO: etiqueta en negrita, cuerpo en normal ──────────────
            if (preg_match('/^(PARÁGRAFO(?:\s+(?:ÚNICO|PRIMERO|SEGUNDO|TERCERO|CUARTO|\d+))?\s*[:.])(.*)$/iu', $linea, $mPar)) {
                $tPar = trim($mPar[1]);
                $bPar = trim($mPar[2] ?? '');
                $run  = $section->addTextRun($pPar);
                $run->addText($tPar . ($bPar !== '' ? ' ' : ''), $fBold);
                if ($bPar !== '') {
                    $run->addText($bPar, $fNorm);
                }
                continue;
            }

            // ── Cuerpo genérico ───────────────────────────────────────────────
            $section->addText($linea, $fNorm, $pBody);
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($rutaAbsoluta);
    }

    public static function getCapitulos(): array
    {
        return [
            [
                'numero' => 'I', 'titulo' => 'DENOMINACIÓN, DOMICILIO Y OBJETO',
                'query_rag' => 'reglamento interno trabajo denominación domicilio objeto ámbito aplicación',
                'codigos_obligatorios' => ['Art. 104 CST', 'Art. 105 CST', 'Art. 106 CST'],
                'datos_empresa_keys'   => ['domicilio', 'tiene_sucursales', 'sucursales', 'actividad_economica', 'actividades_secundarias', 'num_trabajadores'],
                'instrucciones' => implode("\n", [
                    'Redacta estos artículos, cada uno como párrafo completo:',
                    '1. Ámbito de aplicación del reglamento: a quiénes aplica, desde cuándo rige.',
                    '2. Denominación completa, NIT y tipo societario de la empresa.',
                    '3. Domicilio principal; si tiene sucursales, listarlas con ciudad y dirección.',
                    '4. Actividad económica principal y secundarias.',
                    '5. Representante legal y su facultad de dirección y sanción disciplinaria.',
                    'IMPORTANTE: los artículos del CST que aplican están en el contexto jurídico proporcionado.',
                ]),
            ],
            [
                'numero' => 'II', 'titulo' => 'ADMISIÓN Y PERÍODO DE PRUEBA',
                'query_rag' => 'admisión trabajadores período de prueba requisitos ingreso contrato trabajo',
                'codigos_obligatorios' => ['Art. 76 CST', 'Art. 77 CST', 'Art. 78 CST', 'Art. 80 CST'],
                'datos_empresa_keys'   => ['tipos_contrato', 'tiene_trabajadores_mision', 'cargos'],
                'instrucciones' => implode("\n", [
                    '1. REQUISITOS DE INGRESO: lista de documentos que la empresa exige (hoja de vida, documento de identidad, certificados de estudio y experiencia). Los antecedentes judiciales solo son exigibles cuando el cargo lo requiera por razones de seguridad, no como criterio automático de exclusión. Lista en formato 1) 2) 3).',
                    '2. PERÍODO DE PRUEBA - artículo completo y detallado con todos estos elementos:',
                    '   a) El período de prueba SIEMPRE debe pactarse POR ESCRITO; sin pacto escrito no existe período de prueba.',
                    '   b) Duración máxima según el tipo de contrato (indefinido y término fijo). Las duraciones exactas provienen del contexto jurídico proporcionado.',
                    '   c) Prohibición en contratos sucesivos: cuando el trabajador ya prestó servicios al mismo empleador para el mismo cargo, no puede pactarse nuevo período de prueba. Las condiciones exactas provienen del contexto jurídico proporcionado.',
                    '   d) Terminación durante el período: cualquiera de las partes puede terminar el contrato durante el período de prueba. Las condiciones provienen del contexto jurídico proporcionado.',
                    '3. PROHIBICIONES DE INGRESO - artículo expreso y detallado que incluya: a) Prohibición de exigir prueba de embarazo o preguntas sobre planes reproductivos, deseo de conformar familia o estado civil como condición de ingreso o permanencia en el empleo. b) Prohibición de exigir libreta militar como requisito de ingreso (excepto contratos con el sector público o cuando la norma lo exija expresamente). c) Prohibición de pruebas de VIH u otras pruebas de salud que constituyan discriminación. Los artículos y normas aplicables están en el contexto jurídico proporcionado.',
                    '4. TIPOS DE CONTRATO que usa la empresa (según datos empresa). Si usa trabajadores en misión (empresas temporales), mencionar el marco aplicable.',
                    'IMPORTANTE: los plazos, porcentajes y referencias legales exactas provienen únicamente del contexto jurídico proporcionado.',
                ]),
            ],
            [
                'numero' => 'III', 'titulo' => 'JORNADA ORDINARIA DE TRABAJO',
                'query_rag' => 'jornada laboral ordinaria horas trabajo diurno nocturno descanso dominical compensatorio recargo',
                'codigos_obligatorios' => ['Art. 158 CST', 'Art. 159 CST', 'Art. 160 CST', 'Art. 161 CST', 'Art. 162 CST', 'Art. 179 CST', 'Art. 180 CST', 'Art. 181 CST', 'Art. 182 CST'],
                'datos_empresa_keys'   => ['horario_entrada', 'horario_salida', 'opera_en_turnos', 'numero_turnos', 'definicion_turnos', 'rotacion_turnos', 'trabaja_sabados', 'trabaja_dominicales', 'cargos_exentos_jornada', 'modalidades_jornada', 'cargos_nocturnos', 'control_asistencia'],
                'instrucciones' => implode("\n", [
                    'A. JORNADA MÁXIMA LEGAL - artículo completo con todos estos puntos:',
                    '   a) La jornada máxima legal semanal vigente. El número exacto de horas proviene del contexto jurídico proporcionado. Menciónalo explícitamente en el artículo.',
                    '   b) Definición de trabajo diurno y trabajo nocturno con los horarios que establezca el contexto jurídico proporcionado.',
                    '   c) La jornada diaria máxima sin horas extras. El límite exacto proviene del contexto jurídico proporcionado.',
                    'B. HORARIO DE LA EMPRESA - usar TABLA con los datos reales de la empresa:',
                    '   TABLA:',
                    '   ENCABEZADO: Días | Horario entrada | Descanso | Horario salida',
                    '   FILA: [días según datos] | [hora entrada] | [pausa almuerzo] | [hora salida]',
                    '   FIN_TABLA  (si trabaja sábados, agregar fila; si opera en turnos, una tabla por turno)',
                    'C. DESCANSO DOMINICAL REMUNERADO: artículo independiente sobre el derecho al descanso dominical remunerado en domingos y días festivos. El salario por ese día y las condiciones exactas provienen del contexto jurídico proporcionado.',
                    'D. TRABAJO EN DÍA DE DESCANSO OBLIGATORIO - artículo independiente obligatorio con:',
                    '   a) Recargo por trabajar excepcionalmente en día de descanso obligatorio (domingo o festivo). El porcentaje exacto del recargo proviene del contexto jurídico proporcionado.',
                    '   b) DESCANSO COMPENSATORIO: derecho del trabajador que labora en su día de descanso habitual. Las condiciones exactas provienen del contexto jurídico proporcionado.',
                    '   c) TÉCNICOS Y ESPECIALIZADOS: reglas especiales de remuneración y compensación para trabajadores de labores técnicas que requieren continuidad. Las condiciones exactas provienen del contexto jurídico proporcionado.',
                    'E. Si opera en turnos: artículo por turno con horario exacto y cargos asignados, usando TABLA.',
                    'F. Si hay cargos de dirección, manejo o confianza excluidos de jornada máxima: artículo expreso indicando qué cargos y sus condiciones. Ver contexto jurídico para la norma aplicable.',
                    'G. CONTROL DE ASISTENCIA: sistema que usa la empresa (según datos empresa).',
                ]),
            ],
            [
                'numero' => 'IV', 'titulo' => 'TRABAJO SUPLEMENTARIO, DOMINICALES Y FESTIVOS',
                'query_rag' => 'horas extras trabajo suplementario dominicales festivos recargo nocturno límite autorización',
                'codigos_obligatorios' => ['Art. 167 CST', 'Art. 168 CST', 'Art. 169 CST', 'Art. 179 CST', 'Art. 180 CST'],
                'datos_empresa_keys'   => ['politica_horas_extras', 'trabaja_dominicales', 'cargos_nocturnos'],
                'instrucciones' => implode("\n", [
                    'A. LÍMITE DE HORAS EXTRAS: artículo sobre el máximo de horas extras permitidas diaria y semanalmente, la obligación de autorización previa y escrita del empleador, y que las no autorizadas no generan pago. Los límites exactos provienen del contexto jurídico proporcionado.',
                    'B. RECARGOS POR TRABAJO SUPLEMENTARIO: artículo que enumere los diferentes recargos aplicables (hora extra diurna, hora extra nocturna, trabajo dominical o festivo, recargo nocturno ordinario). Los porcentajes exactos de cada recargo provienen del contexto jurídico proporcionado.',
                    'C. Si opera con turnos nocturnos regulares: artículo sobre el recargo nocturno aplicable.',
                    'D. REGISTRO: registro individual de trabajo suplementario firmado por ambas partes.',
                    'E. Política interna de autorización de horas extras de la empresa (según datos empresa).',
                    'IMPORTANTE: los porcentajes de recargo y los límites en horas provienen únicamente del contexto jurídico proporcionado.',
                ]),
            ],
            [
                'numero' => 'V', 'titulo' => 'REMUNERACIÓN Y FORMA DE PAGO',
                'query_rag' => 'salario remuneración forma pago periodicidad salario en especie propinas prohibición fichas',
                'codigos_obligatorios' => ['Art. 127 CST', 'Art. 128 CST', 'Art. 129 CST', 'Art. 131 CST', 'Art. 132 CST', 'Art. 133 CST', 'Art. 134 CST', 'Art. 136 CST', 'Art. 143 CST'],
                'datos_empresa_keys'   => ['forma_pago', 'periodicidad_pago', 'periodicidad_detalle', 'maneja_comisiones', 'tipo_comisiones', 'beneficios_extralegales'],
                'instrucciones' => implode("\n", [
                    'A. MODALIDADES DE SALARIO: por unidad de tiempo (jornal o sueldo), por obra o tarea, variable. Salario integral si aplica a algún cargo (ver condiciones en contexto jurídico).',
                    'B. PERÍODO Y FORMA DE PAGO - artículo que incluya explícitamente:',
                    '   a) Los períodos máximos de pago según el tipo de salario (jornal y sueldo). Los períodos exactos provienen del contexto jurídico proporcionado.',
                    '   b) Forma de pago de la empresa según datos empresa (transferencia, efectivo, etc.).',
                    '   c) Comprobante de pago discriminado con devengados y descuentos.',
                    'C. PROHIBICIÓN DE PAGO EN ESPECIE NO AUTORIZADA: artículo expreso sobre la prohibición de pagar el salario con fichas, vales, mercancías, bonos u otros sustitutos del dinero. El texto exacto proviene del contexto jurídico proporcionado.',
                    'D. SALARIO EN ESPECIE: reglas sobre cuándo es válido, porcentajes máximos permitidos y obligación de pactarlo por escrito. Los límites exactos provienen del contexto jurídico proporcionado.',
                    'E. IGUALDAD SALARIAL: artículo autónomo sobre el principio de igual salario por trabajo de igual valor, sin discriminación por razón de sexo, raza u origen. El texto y referencias exactas provienen del contexto jurídico proporcionado.',
                    'F. PROPINAS: artículo sobre la naturaleza jurídica de las propinas y que no constituyen salario. El texto exacto aplicable proviene del contexto jurídico proporcionado.',
                    'G. Si maneja comisiones: artículo sobre su naturaleza y liquidación. Beneficios extralegales de la empresa (según datos empresa), indicando si son o no factor salarial.',
                    'IMPORTANTE: los porcentajes, condiciones y referencias legales exactas provienen únicamente del contexto jurídico proporcionado.',
                ]),
            ],
            [
                'numero' => 'VI', 'titulo' => 'VACACIONES Y PERMISOS',
                'query_rag' => 'vacaciones remuneradas días hábiles registro acumulación compensación dinero permisos remunerados',
                'codigos_obligatorios' => ['Art. 186 CST', 'Art. 187 CST', 'Art. 188 CST', 'Art. 189 CST', 'Art. 190 CST'],
                'datos_empresa_keys'   => ['politica_permisos'],
                'instrucciones' => implode("\n", [
                    'A. VACACIONES ANUALES: artículo sobre el derecho a vacaciones remuneradas por año de servicio. El número exacto de días y las condiciones provienen del contexto jurídico proporcionado.',
                    'B. DISFRUTE Y REGISTRO: acuerdo entre partes para fijar la época de vacaciones; registro especial de vacaciones con datos del trabajador, fecha de salida y retorno, y saldo acumulado.',
                    'C. ACUMULACIÓN: posibilidad de acumular vacaciones por acuerdo escrito entre las partes; condiciones y límites según contexto jurídico proporcionado.',
                    'D. INTERRUPCIÓN: qué ocurre cuando durante el disfrute de vacaciones sobreviene incapacidad médica o calamidad doméstica.',
                    'E. COMPENSACIÓN EN DINERO: posibilidad de compensar parte de las vacaciones en dinero, condiciones y límites según contexto jurídico proporcionado.',
                    'F. PERMISOS REMUNERADOS: calamidad doméstica, sufragio, diligencias personales con aviso previo. Política interna de la empresa (según datos empresa).',
                    'IMPORTANTE: el número exacto de días y los límites de acumulación provienen únicamente del contexto jurídico proporcionado.',
                ]),
            ],
            [
                'numero' => 'VII', 'titulo' => 'LICENCIAS ESPECIALES',
                'query_rag' => 'licencia maternidad paternidad luto calamidad doméstica enfermedad no remunerada',
                'codigos_obligatorios' => ['Art. 236 CST', 'Art. 237 CST', 'Art. 238 CST', 'Art. 239 CST'],
                'datos_empresa_keys'   => ['tiene_licencias_especiales', 'descripcion_licencias'],
                'instrucciones' => implode("\n", [
                    'A. LICENCIA DE MATERNIDAD: derecho de la trabajadora, duración, quién la paga, prohibición de laborar durante la licencia. La duración exacta en semanas proviene del contexto jurídico proporcionado.',
                    'B. LICENCIA DE PATERNIDAD: derecho del padre trabajador, duración, condiciones. La duración exacta proviene del contexto jurídico proporcionado.',
                    'C. LICENCIA DE LUTO: duración en días hábiles, parentesco que la activa. Los días exactos y el grado de parentesco cubierto provienen del contexto jurídico proporcionado.',
                    'D. LICENCIA POR CALAMIDAD DOMÉSTICA GRAVE: eventos que la activan, duración razonable definida por la empresa.',
                    'E. LICENCIAS NO REMUNERADAS: posibilidad de acuerdo escrito para estudios, trámites u otras causas justificadas.',
                    'F. Licencias especiales propias de la empresa (según datos empresa), si las hay.',
                    'IMPORTANTE: las duraciones exactas (semanas, días) provienen únicamente del contexto jurídico proporcionado.',
                ]),
            ],
            [
                'numero' => 'VIII', 'titulo' => 'RÉGIMEN DISCIPLINARIO: CLASIFICACIÓN DE FALTAS',
                'query_rag' => 'régimen disciplinario faltas leves graves procedimiento descargos garantía debido proceso',
                'codigos_obligatorios' => ['Art. 108 CST', 'Art. 111 CST', 'Art. 112 CST', 'Art. 113 CST', 'Art. 114 CST', 'Art. 115 CST'],
                'datos_empresa_keys'   => ['sanciones_configuradas', 'faltas_leves', 'faltas_graves', 'cargos'],
                'instrucciones' => implode("\n", [
                    'A. DEFINICIÓN DE FALTA DISCIPLINARIA: incumplimiento de obligaciones o transgresión de prohibiciones del reglamento.',
                    'B. LABORES PROHIBIDAS - artículo OBLIGATORIO: indicar expresamente las labores que NO pueden ejecutar las mujeres y los menores de dieciséis (16) años en la empresa, con referencia a las normas del contexto jurídico proporcionado. Si la empresa no emplea menores ni tiene labores de alto riesgo para mujeres gestantes o en lactancia, indicarlo explícitamente.',
                    'C. CATÁLOGO DE FALTAS LEVES (mínimo 8, lista 1) 2) 3)): impuntualidad reiterada, ausentarse sin permiso, descuido en el puesto, uso personal de equipos de la empresa, incumplimiento de entregas, presentación personal inadecuada, descuido en el uso de elementos de seguridad, etc.',
                    'D. CATÁLOGO DE FALTAS GRAVES (mínimo 8): reincidencia en faltas leves, abandono injustificado del puesto, daño doloso a bienes, engaño o fraude, falta grave de respeto, divulgación de información confidencial, inasistencia injustificada, incumplimiento reiterado de instrucciones.',
                    'E. CATÁLOGO DE FALTAS MUY GRAVES (mínimo 5): hurto o apropiación indebida, agresión física, acoso laboral o sexual, presentarse bajo efectos de alcohol o sustancias psicoactivas, sabotaje.',
                    'F. PROCEDIMIENTO DISCIPLINARIO COMPLETO - artículo que reproduzca fielmente el procedimiento legal. Usar TABLA con las etapas:',
                    '   TABLA:',
                    '   ENCABEZADO: Etapa | Descripción',
                    '   FILA: 1. Comunicación de cargos | El empleador informa por escrito al trabajador los hechos y cargos imputados, con traslado de las pruebas que los sustentan.',
                    '   FILA: 2. Plazo de descargos | El trabajador tiene el plazo legal que establezca el contexto jurídico proporcionado para preparar y presentar sus descargos por escrito.',
                    '   FILA: 3. Audiencia de descargos | El trabajador expone sus argumentos verbales y escritos; puede estar asistido por representante sindical o persona de su confianza.',
                    '   FILA: 4. Decreto y práctica de pruebas | El empleador practica las pruebas solicitadas por el trabajador que resulten pertinentes, en el plazo que la norma establezca.',
                    '   FILA: 5. Decisión motivada | El empleador emite resolución escrita, motivada en los hechos probados y el derecho aplicable, imponiendo o desestimando la sanción.',
                    '   FILA: 6. Notificación y recursos | La decisión se notifica al trabajador, quien puede impugnarla ante el superior jerárquico o ante el Inspector del Trabajo.',
                    '   FIN_TABLA',
                    'PARÁGRAFO: ninguna sanción puede imponerse sin haber agotado previamente el procedimiento anterior.',
                    'IMPORTANTE: los plazos exactos (días hábiles) de cada etapa provienen ÚNICAMENTE del contexto jurídico proporcionado. Cítalos textualmente; NO uses cifras de tu entrenamiento.',
                ]),
            ],
            [
                'numero' => 'IX', 'titulo' => 'ESCALA DE SANCIONES',
                'query_rag' => 'escala sanciones disciplinarias multa suspensión terminación justa causa proporcionalidad límite días',
                'codigos_obligatorios' => ['Art. 111 CST', 'Art. 112 CST', 'Art. 113 CST', 'Art. 114 CST'],
                'datos_empresa_keys'   => ['sanciones_configuradas', 'faltas_leves', 'faltas_graves'],
                'instrucciones' => implode("\n", [
                    'Artículo introductorio sobre proporcionalidad de las sanciones. Luego TABLA obligatoria:',
                    'TABLA:',
                    'ENCABEZADO: Sanción | Concepto y límites legales | Faltas que la generan',
                    'FILA: Llamado de atención verbal | Amonestación oral privada con registro en hoja de vida. No tiene límite de días. | Faltas leves por primera vez.',
                    'FILA: Llamado de atención escrito | Notificación formal con copia a hoja de vida y firma del trabajador. | Faltas leves reiteradas o segunda ocurrencia.',
                    'FILA: Multa en dinero | Descuento salarial. El límite máximo de la multa por cada infracción y el porcentaje del salario descontable provienen del contexto jurídico proporcionado. El producto de las multas se destina al fondo de premios y regalías para los trabajadores de la empresa. | Faltas leves y graves según gravedad.',
                    'FILA: Suspensión sin remuneración | Interrupción temporal del contrato sin pago. La duración máxima de la suspensión por primera vez y la duración máxima acumulada provienen del contexto jurídico proporcionado. | Faltas graves.',
                    'FILA: Terminación con justa causa | Desvinculación definitiva por falta muy grave o reincidencia documentada en faltas graves; no hay lugar a indemnización. | Faltas muy graves o reincidencia en graves.',
                    'FIN_TABLA',
                    'Artículo adicional obligatorio sobre:',
                    '1) Prohibición de imponer dos sanciones por el mismo hecho (non bis in idem).',
                    '2) Proporcionalidad: la sanción debe ser proporcional a la gravedad de la falta, los antecedentes del trabajador y las circunstancias atenuantes o agravantes.',
                    '3) Derecho de impugnación: el trabajador puede impugnar la sanción ante el superior jerárquico y ante el Inspector del Trabajo.',
                    'Si la empresa tiene sanciones configuradas específicas (según datos empresa): incluirlas o ajustar la tabla.',
                    'IMPORTANTE: el límite máximo de la multa (porcentaje del salario diario) y el límite en días de suspensión provienen ÚNICAMENTE del contexto jurídico proporcionado. NO uses cifras de tu entrenamiento.',
                ]),
            ],
            [
                'numero' => 'X', 'titulo' => 'RECLAMOS Y PROCEDIMIENTOS',
                'query_rag' => 'reclamos peticiones trabajadores procedimiento queja instancias respuesta empleador',
                'codigos_obligatorios' => [],
                'datos_empresa_keys'   => [],
                'instrucciones' => implode("\n", [
                    'A. INSTANCIAS INTERNAS: jefe directo → área de RRHH → Gerencia. Cómo presenta el trabajador su reclamo.',
                    'B. PLAZO DE RESPUESTA: la empresa debe responder por escrito en un plazo razonable desde la recepción del reclamo. El plazo exacto se indica si aparece en el contexto jurídico proporcionado.',
                    'C. RECLAMOS CONTRA EL SUPERIOR JERÁRQUICO: procedimiento especial cuando el reclamo involucra directamente al superior.',
                    'D. ACCESO EXTERNO: si no hay acuerdo interno, el trabajador puede acudir al Inspector del Trabajo o a la jurisdicción laboral ordinaria.',
                    'E. PROHIBICIÓN DE REPRESALIAS: el empleador no puede tomar represalias contra el trabajador que presente reclamos de buena fe.',
                ]),
            ],
            [
                'numero' => 'XI', 'titulo' => 'NORMAS DE CONDUCTA Y COMPORTAMIENTO',
                'query_rag' => 'obligaciones especiales trabajador empleador prohibiciones conducta confidencialidad',
                'codigos_obligatorios' => ['Art. 57 CST', 'Art. 58 CST', 'Art. 59 CST', 'Art. 60 CST'],
                'datos_empresa_keys'   => ['politica_celular', 'usa_uniforme', 'tiene_codigo_etica', 'politica_confidencialidad', 'que_quiere_prevenir'],
                'instrucciones' => implode("\n", [
                    'A. OBLIGACIONES DEL TRABAJADOR: puntualidad, cuidado de bienes, respeto hacia compañeros/superiores/clientes, confidencialidad, obediencia a instrucciones razonables, reporte oportuno de novedades. Lista 1) 2) 3).',
                    'B. OBLIGACIONES DEL EMPLEADOR: suministrar instrumentos y condiciones de trabajo, garantizar seguridad, pagar oportunamente, respetar la dignidad del trabajador.',
                    'C. PROHIBICIONES DEL TRABAJADOR: sustracción de bienes, actividades personales durante la jornada, consumo de alcohol o sustancias psicoactivas, proselitismo político o religioso, uso ilícito de recursos de la empresa, divulgación de información confidencial.',
                    'D. POLÍTICA DE USO DE CELULARES Y DISPOSITIVOS PERSONALES: según datos empresa; uso personal restringido o prohibido durante la jornada laboral.',
                    'E. Si usa uniforme (según datos empresa): artículo sobre entrega, uso obligatorio, mantenimiento y devolución.',
                    'F. POLÍTICA DE CONFIDENCIALIDAD: información empresarial reservada; obligación vigente durante y después de la relación laboral. Según política de la empresa.',
                    'G. Mencionar específicamente qué quiere prevenir la empresa (según datos empresa).',
                ]),
            ],
            [
                'numero' => 'XII', 'titulo' => 'SEGURIDAD Y SALUD EN EL TRABAJO',
                'query_rag' => 'seguridad salud trabajo SG-SST obligaciones empleador trabajador EPP exámenes médicos accidentes laborales COPASST',
                'codigos_obligatorios' => [],
                'datos_empresa_keys'   => ['tiene_sg_sst', 'riesgos_principales', 'tiene_epp', 'epp_descripcion', 'num_trabajadores'],
                'instrucciones' => implode("\n", [
                    'Cada artículo mínimo 60 palabras:',
                    'A. POLÍTICA DE SST: compromiso de la alta dirección, recursos asignados, objetivos del sistema de gestión.',
                    'B. OBLIGACIONES DEL EMPLEADOR EN SST: afiliar a ARL, proveer EPP, garantizar condiciones seguras, realizar exámenes médicos de ingreso/periódicos/egreso, investigar accidentes y enfermedades laborales.',
                    'C. OBLIGACIONES DEL TRABAJADOR EN SST: usar correctamente el EPP, reportar condiciones inseguras, asistir a capacitaciones, no manipular equipos de seguridad sin autorización.',
                    'D. VIGÍA DE SST O COPASST: según número de trabajadores (ver datos empresa); período de gestión, reuniones y funciones de vigilancia. Los umbrales exactos de trabajadores para cada figura provienen del contexto jurídico proporcionado.',
                    'E. EXÁMENES MÉDICOS OCUPACIONALES: ingreso, periódicos y de egreso; incluir exámenes de alcoholemia y sustancias psicoactivas para cargos con riesgo para terceros (conductores, maquinaria, alturas); reserva absoluta de información médica.',
                    'F. REPORTE DE ACCIDENTES: el trabajador notifica al empleador el mismo día del accidente; la empresa notifica a la ARL dentro del plazo legal; investigación interna obligatoria.',
                    'G. USO OBLIGATORIO DE EPP: según matriz de riesgos del cargo; incumplimiento constituye falta disciplinaria. EPP de la empresa según datos empresa.',
                    'H. PROHIBICIÓN PARA CARGOS DE RIESGO: artículo expreso prohibiendo a trabajadores en cargos de riesgo para terceros (conductores, operadores de maquinaria, trabajo en alturas) presentarse o permanecer en el trabajo bajo efectos de alcohol, sustancias psicoactivas o medicamentos que alteren el estado de alerta. Calificarlo como falta muy grave. Referencias normativas exactas provienen del contexto jurídico proporcionado.',
                    'I. Riesgos principales identificados en la empresa (según datos empresa).',
                ]),
            ],
            [
                'numero' => 'XIII', 'titulo' => 'USO DE EQUIPOS, UNIFORMES Y BIENES DE LA EMPRESA',
                'query_rag' => 'equipos bienes empresa responsabilidad trabajador daños uniformes devolución activos',
                'codigos_obligatorios' => [],
                'datos_empresa_keys'   => ['usa_uniforme'],
                'instrucciones' => implode("\n", [
                    'A. ASIGNACIÓN DE EQUIPOS: procedimiento de entrega formal mediante acta con inventario detallado.',
                    'B. RESPONSABILIDAD POR DAÑOS: el trabajador responde por daños causados por negligencia, descuido o mal uso intencional; no responde por el deterioro derivado del uso normal.',
                    'C. Si usa uniforme (según datos empresa): entrega, uso obligatorio durante la jornada, mantenimiento adecuado, prohibición de uso en actividades que dañen la imagen corporativa, devolución al terminar.',
                    'D. DEVOLUCIÓN DE BIENES: obligación de devolver todos los bienes asignados al terminar el contrato, mediante acta de devolución.',
                    'E. USO DE RECURSOS TECNOLÓGICOS: los equipos de cómputo, acceso a internet y correo corporativo son para uso laboral; la empresa puede monitorear su uso para fines de seguridad.',
                ]),
            ],
            [
                'numero' => 'XIV', 'titulo' => 'COMITÉ DE CONVIVENCIA LABORAL Y PREVENCIÓN DE ACOSO',
                'query_rag' => 'acoso laboral sexual comité convivencia modalidades procedimiento queja denuncia prevención protocolo',
                'codigos_obligatorios' => [
                    // Ley 1010/2006 - artículos clave de acoso laboral
                    'Art. 1 Ley 1010', 'Art. 2 Ley 1010', 'Art. 6 Ley 1010', 'Art. 7 Ley 1010',
                    'Art. 9 Ley 1010', 'Art. 10 Ley 1010', 'Art. 11 Ley 1010', 'Art. 13 Ley 1010',
                    // Res. 652/2012 - conformación y funciones del Comité de Convivencia
                    'Art. 3 Res. 652/2012', 'Art. 5 Res. 652/2012', 'Art. 6 Res. 652/2012',
                    'Art. 7 Res. 652/2012', 'Art. 8 Res. 652/2012', 'Art. 9 Res. 652/2012',
                ],
                'datos_empresa_keys'   => ['num_trabajadores'],
                'instrucciones' => implode("\n", [
                    'Este capítulo es OBLIGATORIO. Cada artículo mínimo 80 palabras.',
                    'A. ACOSO LABORAL - DEFINICIÓN Y MODALIDADES: artículo autónomo completo que defina el acoso laboral como toda conducta persistente y demostrable ejercida sobre un empleado por parte de un empleador, jefe o superior jerárquico inmediato o mediato, un compañero de trabajo o un subalterno. Luego detallar con ejemplos concretos TODAS las modalidades de la Ley 1010: 1) Maltrato laboral (toda expresión verbal o física que lesione la integridad moral o física del trabajador); 2) Persecución laboral (conductas reiteradas para inducir la renuncia); 3) Discriminación laboral (trato diferenciado injustificado); 4) Entorpecimiento laboral (obstáculos que impidan el trabajo); 5) Inequidad laboral (asignación de tareas denigrantes o excesivas); 6) Desprotección laboral (inducir al trabajador a riesgos laborales sin los medios adecuados). Las referencias normativas provienen del contexto jurídico proporcionado.',
                    'B. COMITÉ DE CONVIVENCIA LABORAL - artículo completo que incluya:',
                    '   a) Conformación bipartita: igual número de representantes del empleador y de los trabajadores.',
                    '   b) Elección democrática de los representantes de los trabajadores mediante votación.',
                    '   c) Período de gestión según lo establezca el contexto jurídico proporcionado, con posibilidad de reelección.',
                    '   d) Reuniones ordinarias y extraordinarias cuando se requiera. La frecuencia mínima proviene del contexto jurídico proporcionado.',
                    '   e) Quorum para sesionar según el contexto jurídico proporcionado.',
                    '   f) Actas de reunión como constancia de las actuaciones.',
                    'C. FUNCIONES DEL COMITÉ - artículo completo con TODAS estas funciones: recibir y dar trámite a las quejas de acoso laboral; escuchar a las partes involucradas; promover acuerdos conciliatorios entre las partes; formular planes de mejora para convivencia laboral; hacer seguimiento a las medidas adoptadas; hacer seguimiento a las recomendaciones dadas por el Comité a las dependencias de gestión del talento humano y salud ocupacional de la empresa; reportar a la alta dirección los casos y sus resultados; hacer seguimiento trimestral de los casos atendidos.',
                    'D. PROCEDIMIENTO INTERNO DE QUEJA POR ACOSO LABORAL - artículo con pasos numerados obligatorios:',
                    '   1) Presentación escrita de la queja ante el Comité de Convivencia Laboral, con descripción de los hechos, fechas y testigos.',
                    '   2) Notificación al presunto acosador dentro de los 5 días hábiles siguientes.',
                    '   3) Investigación confidencial: el Comité escucha a ambas partes por separado y recauda las pruebas pertinentes.',
                    '   4) Audiencia de conciliación: el Comité facilita el diálogo entre las partes para llegar a un acuerdo.',
                    '   5) Informe final del Comité con las medidas correctivas, plazos y responsables.',
                    '   6) Seguimiento trimestral por parte del Comité para verificar el cumplimiento de las medidas.',
                    '   7) Si no hay acuerdo o la conducta persiste, remisión a la autoridad competente (Inspector del Trabajo, Procuraduría, Fiscalía).',
                    'E. PREVENCIÓN DEL ACOSO SEXUAL - ARTÍCULO AUTÓNOMO con mínimo 120 palabras:',
                    '   a) Definición de acoso sexual en el contexto laboral: toda conducta de naturaleza sexual no consentida, solicitudes de favores sexuales, comentarios o actos de connotación sexual que afecten la dignidad del trabajador.',
                    '   b) Tipos de conductas constitutivas: verbales (comentarios, insinuaciones, proposiciones), físicas (contacto no deseado, proximidad invasiva), digitales o virtuales (mensajes, imágenes, videos).',
                    '   c) Canal confidencial y exclusivo para denuncias de acoso sexual, separado del canal general de quejas.',
                    '   d) Protocolo de respuesta: recepción de la denuncia, investigación imparcial y confidencial, medidas de protección inmediata para la víctima, decisión motivada.',
                    '   e) Garantía absoluta de confidencialidad de la identidad de la víctima durante la investigación.',
                    '   f) Prohibición expresa de represalias contra la persona que denuncia.',
                    'F. SANCIONES POR ACOSO - artículo expreso: la conducta de acoso laboral o acoso sexual se califica como falta MUY GRAVE y puede dar lugar a terminación del contrato con justa causa, sin perjuicio de las acciones penales y administrativas a que haya lugar.',
                ]),
            ],
            [
                'numero' => 'XV', 'titulo' => 'PROTECCIÓN DE SUJETOS DE ESPECIAL PROTECCIÓN',
                'query_rag' => 'mujer embarazada maternidad paternidad discapacidad estabilidad laboral reforzada fuero sindical no discriminación',
                'codigos_obligatorios' => ['Art. 236 CST', 'Art. 238 CST', 'Art. 239 CST', 'Art. 240 CST', 'Art. 241 CST', 'Art. 241A CST'],
                'datos_empresa_keys'   => [],
                'instrucciones' => implode("\n", [
                    'A. MUJER EMBARAZADA Y EN PERÍODO DE LACTANCIA - artículo completo y detallado con todos estos elementos:',
                    '   a) PROHIBICIÓN DE DESPIDO: está prohibido despedir a la trabajadora durante el embarazo y hasta tres meses después del parto, sin la autorización previa del Inspector del Trabajo o del Alcalde Municipal. Esta prohibición opera desde el momento en que el empleador tenga conocimiento del estado de embarazo.',
                    '   b) NULIDAD DEL DESPIDO: el despido efectuado durante el embarazo o los tres meses siguientes al parto, sin autorización del Ministerio del Trabajo, se presume que es por razón del embarazo y es INEFICAZ. La trabajadora puede reclamar su reintegro y el pago de los salarios dejados de percibir.',
                    '   c) INDEMNIZACIÓN ADICIONAL: si el empleador despide a la trabajadora sin la autorización requerida, debe pagar adicionalmente, a título de indemnización, el valor establecido en el contexto jurídico proporcionado.',
                    '   d) Prohibición de exigir pruebas de embarazo como condición de ingreso o permanencia en el empleo.',
                    '   e) DESCANSOS DE LACTANCIA: el empleador concederá los descansos remunerados para amamantar según la duración y frecuencia EXACTAS establecidas en el Art. 238 del contexto jurídico proporcionado. Cita expresamente la cantidad de descansos, los minutos de cada uno y el período de edad del menor.',
                    'B. LICENCIA DE PATERNIDAD: derecho del padre trabajador a una licencia remunerada desde el nacimiento del hijo. La duración exacta en semanas o días proviene del contexto jurídico proporcionado (Art. 236). Condición: el padre debe haber cotizado al sistema de seguridad social durante el período que establezca la norma.',
                    'C. PERSONAS EN SITUACIÓN DE DISCAPACIDAD: estabilidad laboral reforzada; prohibición de despido sin autorización previa del Inspector del Trabajo o del Ministerio del Trabajo; obligación de realizar ajustes razonables en el puesto de trabajo para garantizar la inclusión laboral.',
                    'D. TRABAJADORES CON FUERO SINDICAL: prohibición de despido, traslado o desmejora de condiciones sin autorización judicial previa (fuero de retiro). El empleador que desconozca el fuero debe pagar los salarios dejados de percibir y reintegrar al trabajador. Los artículos exactos del CST provienen del contexto jurídico proporcionado.',
                    'E. NO DISCRIMINACIÓN: prohibición absoluta de discriminación por raza, sexo, edad, religión, orientación sexual, identidad de género, origen nacional o social, posición económica u otra condición. Referencias normativas exactas provienen del contexto jurídico proporcionado.',
                ]),
            ],
            [
                'numero' => 'XVI', 'titulo' => 'DISPOSICIONES FINALES',
                'query_rag' => 'disposiciones finales vigencia reglamento interno trabajo depósito Ministerio Trabajo publicación modificaciones',
                'codigos_obligatorios' => [],
                'datos_empresa_keys'   => ['domicilio'],
                'instrucciones' => implode("\n", [
                    'A. VIGENCIA: el reglamento rige desde la fecha de su publicación a los trabajadores y permanece vigente mientras no sea derogado o modificado.',
                    'B. MODIFICACIONES: procedimiento para modificar el reglamento; obligación de comunicar a los trabajadores con anticipación mínima y de depositar ante el Ministerio del Trabajo.',
                    'C. PUBLICACIÓN Y ACCESO: publicar en lugar visible de cada establecimiento y entregar copia a cada trabajador al momento de su vinculación.',
                    'D. DEPÓSITO ANTE EL MINISTERIO: plazo para depositar ante la Dirección Territorial del Ministerio del Trabajo competente según el domicilio de la empresa. El plazo exacto proviene del contexto jurídico proporcionado.',
                    'E. INCORPORACIÓN A CONTRATOS: el presente reglamento queda incorporado como parte integrante de todos los contratos individuales de trabajo.',
                    'F. ARTÍCULO FINAL DE FIRMA: ciudad, fecha de elaboración (usar fecha actual), nombre completo y cargo del representante legal.',
                ]),
            ],
        ];
    }
}
