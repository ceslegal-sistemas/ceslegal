<?php

namespace App\Services;

use App\Models\FragmentoReglamento;
use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class ReglamentoInternoService
{
    private const PALABRAS_POR_FRAGMENTO = 500;
    private const PALABRAS_SOLAPAMIENTO  = 60;
    private const EMBEDDING_MODEL        = 'gemini-embedding-001';
    /**
     * Procesa un archivo (.docx o .pdf), extrae el texto y lo guarda en BD.
     *
     * La extracción de texto es opcional — si falla, el registro se crea de
     * todas formas con activo=true para que la empresa quede con RIT activo.
     */
    /**
     * @param  string      $rutaArchivo    Ruta absoluta al archivo (temp o permanente)
     * @param  int         $empresaId
     * @param  string      $nombreOriginal Nombre visible del archivo
     * @param  string|null $rutaRelativa   Ruta relativa en disco 'local' para guardar en ruta_docx
     */
    public function procesarDocumento(
        string $rutaArchivo,
        int    $empresaId,
        string $nombreOriginal,
        ?string $rutaRelativa = null,
    ): ReglamentoInterno {
        $texto = '';

        try {
            $extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));

            $texto = match ($extension) {
                'pdf'  => $this->extraerTextoPdf($rutaArchivo),
                default => $this->extraerTextoDocx($rutaArchivo),
            };
        } catch (\Exception $e) {
            // La extracción de texto falla con gracia — el RIT aún se registra
            Log::warning('ReglamentoInternoService: no se pudo extraer texto del documento', [
                'empresa_id' => $empresaId,
                'archivo'    => basename($rutaArchivo),
                'error'      => $e->getMessage(),
            ]);
        }

        $campos = [
            'nombre'         => $nombreOriginal,
            'texto_completo' => $texto ?: null,
            'activo'         => true,
            'fuente'         => 'subido',
            // Días laborales inferidos del RIT subido (null si no se detecta → fallback empresa).
            'dias_laborales' => $this->detectarDiasLaborales($texto),
        ];

        // Si se proporciona una ruta permanente, guardarla para descarga directa
        if ($rutaRelativa) {
            $campos['ruta_docx'] = $rutaRelativa;
        }

        // Al subir un nuevo RIT manual, limpiar sanciones previas para forzar re-extracción
        $campos['sanciones_extraidas'] = null;
        $campos['empresa_id']          = $empresaId;

        // Desactivar todos los registros anteriores para que solo quede este como activo.
        // Se hace antes de crear el nuevo para que la relación hasOne(activo=true).latest()
        // no devuelva un registro antiguo de IA cuando coexisten varios por empresa.
        ReglamentoInterno::where('empresa_id', $empresaId)->update(['activo' => false]);

        $reglamento = ReglamentoInterno::create($campos);

        Log::info('ReglamentoInternoService: documento registrado', [
            'empresa_id' => $empresaId,
            'nombre'     => $nombreOriginal,
            'chars'      => strlen($texto),
        ]);

        // Extraer sanciones inmediatamente si hay texto disponible
        if (!empty($texto)) {
            $this->extraerYPersistirSanciones($reglamento);
        }

        return $reglamento;
    }

    /**
     * Explica por qué no se pudo extraer texto de un documento subido, para dar un
     * mensaje preciso al usuario. Devuelve:
     *   'protegido' → PDF cifrado/con restricciones (p. ej. un RIT que el propio
     *                 sistema generó, que sale con protección de edición).
     *   'ilegible'  → PDF sin texto seleccionable (escaneado como imagen) u otro caso.
     */
    public function motivoTextoVacio(string $rutaArchivo): string
    {
        if (strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION)) === 'pdf'
            && is_file($rutaArchivo)
            && str_contains((string) file_get_contents($rutaArchivo, false, null, 0, 4096) . $this->colaArchivo($rutaArchivo), '/Encrypt')
        ) {
            return 'protegido';
        }

        return 'ilegible';
    }

    /** Últimos bytes del archivo (el diccionario /Encrypt suele ir cerca del trailer). */
    private function colaArchivo(string $ruta): string
    {
        $tam = @filesize($ruta) ?: 0;
        if ($tam <= 4096) {
            return '';
        }

        return (string) file_get_contents($ruta, false, null, max(0, $tam - 4096), 4096);
    }

    /**
     * Detecta los días laborales a partir del texto del RIT subido.
     * Devuelve 'lunes_sabado' | 'lunes_viernes' | null (no detectado → fallback empresa).
     */
    private function detectarDiasLaborales(?string $texto): ?string
    {
        if (! $texto) {
            return null;
        }

        $t = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($texto));

        if (preg_match('/lunes\s+a\s+sabado|hasta\s+el\s+sabado|inclu[ií]d[oa]s?\s+(el\s+|los\s+)?sabado|seis\s*\(?\s*6\s*\)?\s*d[ií]as/u', $t)) {
            return 'lunes_sabado';
        }
        if (preg_match('/lunes\s+a\s+viernes|cinco\s*\(?\s*5\s*\)?\s*d[ií]as/u', $t)) {
            return 'lunes_viernes';
        }

        return null;
    }

    /**
     * Extrae faltas y sanciones del RIT para el correo de citación y procesos disciplinarios.
     *
     * - RIT wizard (respuestas_cuestionario) → devuelve datos ya estructurados, sin IA.
     * - RIT manual con sanciones_extraidas   → devuelve datos ya guardados en BD.
     * - RIT manual sin sanciones_extraidas   → extrae con Gemini y persiste en BD.
     * - Sin texto_completo                   → array vacío (tabla no se mostrará).
     *
     * Siempre retorna strings legibles en español.
     * Estructura: ['faltas_leves' => [...], 'faltas_graves' => [...], 'sanciones' => [...]]
     */
    public function extraerSancionesParaEmail(ReglamentoInterno $rit): array
    {
        // ── Caso 1: wizard (construido_ia) — datos ya estructurados ───────────
        // Solo se usa si la fuente activa es el wizard; si se subió un documento
        // posterior, respuestas_cuestionario puede seguir existiendo pero no es
        // la fuente vigente.
        if ($rit->fuente !== 'subido') {
            $cuestionario = $rit->respuestas_cuestionario ?? [];
            if (!empty($cuestionario['faltas_leves']) || !empty($cuestionario['faltas_graves'])) {
                $mapa         = $this->mapaClavesSanciones();
                $sancionesRaw = $cuestionario['sanciones_contempladas'] ?? $cuestionario['sanciones'] ?? [];
                return [
                    'faltas_leves'  => $cuestionario['faltas_leves']  ?? [],
                    'faltas_graves' => $cuestionario['faltas_graves'] ?? [],
                    'sanciones'     => array_map(fn($s) => $mapa[$s] ?? $s, $sancionesRaw),
                ];
            }
        }

        // ── Caso 2: documento subido con extracción ya guardada en BD ──────────
        if (!empty($rit->sanciones_extraidas)) {
            return $rit->sanciones_extraidas;
        }

        // ── Caso 3: documento subido sin extracción — extraer con IA y persistir
        if (empty($rit->texto_completo)) {
            return [];
        }

        return $this->extraerYPersistirSanciones($rit);
    }

    /**
     * Devuelve las filas EXACTAS de la tabla de sanciones por gravedad (leve/grave/muy grave),
     * con la conducta y la sanción tal como las define el RIT del cliente — sin heurística cuando
     * el dato existe. Normaliza ambas fuentes:
     *   - Wizard: respuestas_cuestionario.sanciones_configuradas (conducta + gravedad + sanción).
     *   - Subido: faltas + sanción por gravedad extraídas por IA (o heurística si es dato viejo).
     *
     * @return array<int, array{gravedad:string, conductas:array<string>, sancion:string}>
     */
    public function filasTablaSanciones(ReglamentoInterno $rit): array
    {
        $etiqueta = ['leve' => 'Leve', 'grave' => 'Grave', 'muy_grave' => 'Muy grave'];

        // ── Fuente exacta del wizard: sanciones_configuradas ─────────────────────
        $config = ($rit->fuente !== 'subido')
            ? ($rit->respuestas_cuestionario['sanciones_configuradas'] ?? [])
            : [];

        if (!empty($config) && is_array($config)) {
            $fmt = fn(array $s): string => match ($s['tipo_sancion'] ?? '') {
                'llamado_atencion' => 'Llamado de atención',
                'suspension'       => 'Suspensión' . (!empty($s['dias_suspension']) ? ' hasta ' . $s['dias_suspension'] . ' día(s)' : ''),
                'terminacion'      => 'Terminación del contrato con justa causa',
                'no_sancion'       => 'No aplica sanción',
                default            => ucfirst(str_replace('_', ' ', (string) ($s['tipo_sancion'] ?? ''))),
            };

            $grupos = ['leve' => ['c' => [], 's' => []], 'grave' => ['c' => [], 's' => []], 'muy_grave' => ['c' => [], 's' => []]];
            foreach ($config as $s) {
                $g = $s['tipo_falta'] ?? '';
                if (!isset($grupos[$g])) continue;
                if (!empty($s['nombre'])) $grupos[$g]['c'][] = $s['nombre'];
                $grupos[$g]['s'][] = $fmt($s);
            }

            $filas = [];
            foreach (['leve', 'grave', 'muy_grave'] as $g) {
                if (empty($grupos[$g]['c'])) continue;
                $filas[] = [
                    'gravedad'  => $etiqueta[$g],
                    'conductas' => array_values(array_unique($grupos[$g]['c'])),
                    'sancion'   => implode(' / ', array_values(array_unique(array_filter($grupos[$g]['s'])))),
                ];
            }
            if (!empty($filas)) return $filas;
        }

        // ── Fuente subida/extraída: faltas + sanción por gravedad (IA o heurística)
        $datos = $this->extraerSancionesParaEmail($rit);
        $heuristica = function (bool $esLeve) use ($datos): string {
            $sanciones = $datos['sanciones'] ?? [];
            if ($esLeve) {
                return collect($sanciones)->filter(fn($s) => preg_match('/llamado|atenci[oó]n|advertencia|verbal|escrito/i', (string) $s))->join(' / ') ?: 'Llamado de Atención';
            }
            return collect($sanciones)->filter(fn($s) => preg_match('/suspensi[oó]n|terminaci[oó]n|despido/i', (string) $s))->join(' / ') ?: 'Suspensión / Terminación del contrato';
        };

        $mapa = [
            'leve'      => [$etiqueta['leve'],      $datos['faltas_leves']      ?? [], $datos['sancion_leve']      ?? null, true],
            'grave'     => [$etiqueta['grave'],     $datos['faltas_graves']     ?? [], $datos['sancion_grave']     ?? null, false],
            'muy_grave' => [$etiqueta['muy_grave'], $datos['faltas_muy_graves'] ?? [], $datos['sancion_muy_grave'] ?? null, false],
        ];

        $filas = [];
        foreach ($mapa as [$label, $faltas, $sancionExacta, $esLeve]) {
            if (empty($faltas)) continue;
            $filas[] = [
                'gravedad'  => $label,
                'conductas' => array_values($faltas),
                'sancion'   => $sancionExacta ?: $heuristica($esLeve),
            ];
        }

        return $filas;
    }

    /**
     * Extrae sanciones con IA y las guarda en reglamentos_internos.sanciones_extraidas.
     * Retorna el array resultante (vacío si falla).
     */
    public function extraerYPersistirSanciones(ReglamentoInterno $rit): array
    {
        $datos = $this->extraerSancionesConIA($rit->texto_completo ?? '');

        if (!empty($datos)) {
            $rit->sanciones_extraidas = $datos;
            $rit->saveQuietly(); // sin disparar eventos/observers
        }

        return $datos;
    }

    /**
     * Extrae el capítulo disciplinario del texto y solicita a Gemini la estructura de faltas.
     */
    private function extraerSancionesConIA(string $textoRIT): array
    {
        $fragmento = $this->extraerCapituloDisciplinario($textoRIT);

        if (empty($fragmento)) {
            Log::info('ReglamentoInternoService: capítulo disciplinario no encontrado en texto del RIT');
            return [];
        }

        $prompt = <<<PROMPT
Analiza el siguiente capítulo del Reglamento Interno de Trabajo de una empresa colombiana y extrae la lista de faltas laborales.

TEXTO DEL REGLAMENTO:
{$fragmento}

Responde ÚNICAMENTE con un JSON válido, sin texto adicional, con esta estructura exacta:
{
  "faltas_leves": ["descripción concreta de falta 1", "descripción concreta de falta 2"],
  "faltas_graves": ["descripción concreta de falta 1", "descripción concreta de falta 2"],
  "faltas_muy_graves": ["descripción concreta de falta 1"],
  "sancion_leve": "la sanción EXACTA que el RIT asigna a las faltas LEVES",
  "sancion_grave": "la sanción EXACTA que el RIT asigna a las faltas GRAVES",
  "sancion_muy_grave": "la sanción EXACTA que el RIT asigna a las faltas MUY GRAVES (o '' si no las distingue)",
  "sanciones": ["Llamado de Atención Verbal", "Suspensión hasta X días", "Terminación del Contrato"]
}

Reglas:
- faltas_leves, faltas_graves y faltas_muy_graves: máximo 8 items cada uno, máximo 100 caracteres por item
- sancion_leve / sancion_grave / sancion_muy_grave: copia EXACTA (textual) de la sanción que el RIT
  asigna a cada nivel de gravedad. Si el RIT no separa "muy graves", deja faltas_muy_graves vacío y
  sancion_muy_grave en "". NO inventes la sanción: si no está clara, deja la cadena vacía.
- sanciones: lista legible de TODAS las sanciones que menciona el RIT (respaldo)
- Si el texto no tiene información clara de faltas, devuelve arrays vacíos
- No listes artículos del CST genéricos; solo lo que describe concretamente este RIT
PROMPT;

        try {
            $respuesta = $this->llamarGeminiJSON($prompt);
            $datos     = $this->parsearJSON($respuesta);

            $nz = fn($v) => (is_string($v) && trim($v) !== '') ? trim($v) : null;

            return [
                'faltas_leves'      => array_slice($datos['faltas_leves']  ?? [], 0, 10),
                'faltas_graves'     => array_slice($datos['faltas_graves'] ?? [], 0, 10),
                'faltas_muy_graves' => array_slice($datos['faltas_muy_graves'] ?? [], 0, 10),
                'sancion_leve'      => $nz($datos['sancion_leve'] ?? null),
                'sancion_grave'     => $nz($datos['sancion_grave'] ?? null),
                'sancion_muy_grave' => $nz($datos['sancion_muy_grave'] ?? null),
                'sanciones'         => $datos['sanciones'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('ReglamentoInternoService: error extrayendo sanciones con IA', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Extrae el fragmento de régimen disciplinario del texto completo del RIT.
     * Estrategia 1: por encabezado CAPÍTULO (captura dos capítulos consecutivos: faltas + sanciones).
     * Estrategia 2: palabras clave con contexto (fallback).
     */
    private function extraerCapituloDisciplinario(string $textoRIT): string
    {
        $lineas   = explode("\n", $textoRIT);
        $total    = count($lineas);
        $maxChars = 5000;

        $capitulosRef  = ['RÉGIMEN DISCIPLINARIO', 'REGIMEN DISCIPLINARIO', 'FALTAS', 'SANCIONES', 'ESCALA DE SANCIONES'];
        $palabrasClave = ['falta', 'sanc', 'disciplin', 'descargo', 'amonestac', 'suspens', 'multa'];

        // Estrategia 1: buscar encabezado CAPÍTULO
        $inicio = null;
        foreach ($lineas as $i => $linea) {
            if (!preg_match('/CAP[IÍ]TULO/ui', $linea)) continue;
            $lineaUp = mb_strtoupper($linea);
            foreach ($capitulosRef as $keyword) {
                if (str_contains($lineaUp, mb_strtoupper($keyword))) {
                    $inicio = $i;
                    break 2;
                }
            }
        }

        if ($inicio !== null) {
            $fin = $total;
            $chapterCount = 0;
            for ($i = $inicio + 1; $i < $total; $i++) {
                if (preg_match('/CAP[IÍ]TULO/ui', $lineas[$i])) {
                    $chapterCount++;
                    if ($chapterCount >= 2) { $fin = $i; break; }
                }
            }
            $fragmento = implode("\n", array_slice($lineas, $inicio, $fin - $inicio));
            if (!empty(trim($fragmento))) {
                return mb_substr(trim($fragmento), 0, $maxChars);
            }
        }

        // Estrategia 2: palabras clave con ±10 líneas de contexto
        $indices = [];
        foreach ($lineas as $i => $linea) {
            $lineaNorm = mb_strtolower($linea);
            foreach ($palabrasClave as $clave) {
                if (str_contains($lineaNorm, $clave)) {
                    for ($j = max(0, $i - 5); $j <= min($total - 1, $i + 10); $j++) {
                        $indices[$j] = true;
                    }
                    break;
                }
            }
        }

        if (empty($indices)) return '';

        ksort($indices);
        $fragmento = '';
        $prev = -2;
        foreach (array_keys($indices) as $i) {
            if ($i > $prev + 1) $fragmento .= "\n";
            $fragmento .= $lineas[$i] . "\n";
            $prev = $i;
        }

        return mb_substr(trim($fragmento), 0, $maxChars);
    }

    /** Llama a Gemini solicitando JSON puro; cascada flash → flash-lite. */
    private function llamarGeminiJSON(string $prompt): string
    {
        $apiKey  = config('services.ia.gemini.api_key', '');
        $modelos = ['gemini-2.5-flash', 'gemini-2.5-flash-lite'];

        $payload = [
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'      => 0.1,
                'maxOutputTokens'  => 2048,
                'responseMimeType' => 'application/json',
                'thinkingConfig'   => ['thinkingBudget' => 0],
            ],
        ];

        foreach ($modelos as $model) {
            $url      = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(45)
                ->post($url, $payload);

            if ($response->successful()) {
                $parts = $response->json('candidates.0.content.parts', []);
                foreach (array_reverse($parts) as $part) {
                    if (empty($part['thought']) && !empty($part['text'])) {
                        return $part['text'];
                    }
                }
                return $response->json('candidates.0.content.parts.0.text', '');
            }

            Log::warning("ReglamentoInternoService: Gemini {$response->status()} con modelo {$model}");

            if (!in_array($response->status(), [429, 503, 500, 502, 504])) {
                throw new \RuntimeException('Error Gemini (' . $response->status() . '): ' . $response->body());
            }
        }

        throw new \RuntimeException('Todos los modelos Gemini fallaron al extraer sanciones del RIT');
    }

    /** Parsea la respuesta JSON de Gemini, tolerando bloques de código markdown. */
    private function parsearJSON(string $texto): array
    {
        $texto = preg_replace('/^```(?:json)?\s*/i', '', trim($texto));
        $texto = preg_replace('/\s*```$/m', '', $texto);
        $datos = json_decode(trim($texto), true);
        return is_array($datos) ? $datos : [];
    }

    /** Mapa de claves internas del wizard a texto legible en español. */
    private function mapaClavesSanciones(): array
    {
        return [
            'llamado_verbal'  => 'Llamado de Atención Verbal',
            'llamado_escrito' => 'Llamado de Atención Escrito',
            'suspension_1_8'  => 'Suspensión 1 a 8 días sin sueldo',
            'suspension_1_15' => 'Suspensión 1 a 15 días sin sueldo',
            'suspension_1_30' => 'Suspensión 1 a 30 días sin sueldo',
            'suspension_1_40' => 'Suspensión 1 a 40 días sin sueldo',
            'suspension_1_60' => 'Suspensión 1 a 60 días sin sueldo',
            'terminacion'     => 'Terminación del Contrato con Justa Causa',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // RAG — Fragmentación, embeddings y búsqueda semántica del RIT subido
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Busca en el RIT usando RAG: fragmenta y embebe si aún no existe caché,
     * luego recupera los K fragmentos más relevantes para la query dada.
     * Retorna texto listo para incluir en el prompt (vacío si no hay resultado).
     */
    public function buscarEnRIT(ReglamentoInterno $rit, string $query, int $limite = 4, float $umbral = 0.48): string
    {
        if (empty($rit->texto_completo)) {
            return '';
        }

        // Generar fragmentos y embeddings la primera vez (lazy)
        if ($rit->fragmentos()->whereNotNull('embedding')->count() === 0) {
            $this->generarFragmentosRIT($rit);
        }

        $queryEmbedding = $this->obtenerEmbedding($query, 'RETRIEVAL_QUERY');
        if (empty($queryEmbedding)) {
            return '';
        }

        $fragmentos = $rit->fragmentos()->whereNotNull('embedding')->get();
        if ($fragmentos->isEmpty()) {
            return '';
        }

        $scored = $fragmentos
            ->map(fn ($f) => [
                'fragmento' => $f,
                'score'     => $this->cosineSimilaridad($queryEmbedding, $f->embedding),
            ])
            ->filter(fn ($item) => $item['score'] >= $umbral)
            ->sortByDesc('score')
            ->take($limite)
            ->values();

        if ($scored->isEmpty()) {
            return '';
        }

        $lineas = [];
        foreach ($scored as $item) {
            $pct = number_format($item['score'] * 100, 0);
            $lineas[] = "--- [RIT fragmento — relevancia {$pct}%] ---";
            $lineas[] = trim($item['fragmento']->contenido);
            $lineas[] = '';
        }

        return trim(implode("\n", $lineas));
    }

    /**
     * Divide el texto en fragmentos de ~500 palabras con solapamiento de 60.
     * Respeta límites de párrafo para no cortar oraciones a la mitad.
     */
    public function chunkear(string $texto): array
    {
        $parrafos = preg_split('/\n{2,}/', $texto);
        $parrafos = array_values(array_filter($parrafos, fn ($p) => str_word_count($p) >= 5));

        $fragmentos = [];
        $buffer     = [];
        $palabras   = 0;
        $cola       = []; // palabras de solapamiento del fragmento anterior

        foreach ($parrafos as $parrafo) {
            $pw = str_word_count($parrafo);

            if ($palabras + $pw > self::PALABRAS_POR_FRAGMENTO && $palabras >= 20) {
                $fragmentos[] = implode("\n\n", $buffer);
                // Solapamiento: últimas PALABRAS_SOLAPAMIENTO palabras
                $todoTexto = implode(' ', $buffer);
                $allWords  = explode(' ', $todoTexto);
                $cola      = array_slice($allWords, -self::PALABRAS_SOLAPAMIENTO);
                $buffer    = [implode(' ', $cola)];
                $palabras  = count($cola);
            }

            $buffer[] = $parrafo;
            $palabras += $pw;
        }

        if (!empty($buffer) && $palabras >= 20) {
            $fragmentos[] = implode("\n\n", $buffer);
        }

        return $fragmentos;
    }

    /**
     * Genera (o regenera) los fragmentos con embeddings del RIT en BD.
     * Limpia fragmentos anteriores antes de generar los nuevos.
     */
    public function generarFragmentosRIT(ReglamentoInterno $rit): int
    {
        if (empty($rit->texto_completo)) {
            return 0;
        }

        // Limpiar fragmentos anteriores
        $rit->fragmentos()->delete();

        $fragmentos = $this->chunkear($rit->texto_completo);
        $guardados  = 0;

        foreach ($fragmentos as $orden => $contenido) {
            $embedding = $this->obtenerEmbedding($contenido, 'RETRIEVAL_DOCUMENT');

            FragmentoReglamento::create([
                'reglamento_interno_id' => $rit->id,
                'orden'                 => $orden + 1,
                'contenido'             => $contenido,
                'embedding'             => $embedding ?: null,
            ]);

            $guardados++;
        }

        Log::info('ReglamentoInternoService: fragmentos RAG generados', [
            'reglamento_id' => $rit->id,
            'empresa_id'    => $rit->empresa_id,
            'fragmentos'    => $guardados,
        ]);

        return $guardados;
    }

    /**
     * Llama a la API de Gemini Embeddings y retorna el vector.
     * Cachea 24 h por MD5 del texto para evitar llamadas repetidas.
     */
    private function obtenerEmbedding(string $texto, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        $cacheKey = 'rit_emb_' . md5($taskType . $texto);

        return Cache::remember($cacheKey, 86400, function () use ($texto, $taskType) {
            $apiKey = config('services.ia.gemini.api_key', '');
            $url    = "https://generativelanguage.googleapis.com/v1beta/models/"
                    . self::EMBEDDING_MODEL
                    . ":embedContent?key={$apiKey}";

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post($url, [
                    'model'   => 'models/' . self::EMBEDDING_MODEL,
                    'content' => ['parts' => [['text' => mb_substr($texto, 0, 8000)]]],
                    'taskType' => $taskType,
                ]);

            if (!$response->successful()) {
                Log::warning('ReglamentoInternoService: error embedding', [
                    'status' => $response->status(),
                ]);
                return [];
            }

            return $response->json('embedding.values', []);
        });
    }

    /**
     * Similitud coseno entre dos vectores.
     */
    private function cosineSimilaridad(array $a, array $b): float
    {
        $dot = $magA = $magB = 0.0;
        $n   = min(count($a), count($b));

        for ($i = 0; $i < $n; $i++) {
            $dot  += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }

        $mag = sqrt($magA) * sqrt($magB);
        return $mag > 0 ? $dot / $mag : 0.0;
    }

    /**
     * Devuelve el texto completo del reglamento activo para una empresa, o null si no existe.
     */
    public function getTextoReglamento(int $empresaId): ?string
    {
        $reglamento = ReglamentoInterno::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->latest()
            ->first();

        return $reglamento?->texto_completo;
    }

    /**
     * Extrae texto plano de un .docx usando PhpWord.
     */
    private function extraerTextoDocx(string $rutaArchivo): string
    {
        $phpWord = IOFactory::load($rutaArchivo);
        $lineas  = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $lineas[] = $this->elementoATexto($element);
            }
        }

        return trim(implode("\n", array_filter($lineas)));
    }

    /**
     * Extrae texto plano de un .pdf usando smalot/pdfparser.
     */
    private function extraerTextoPdf(string $rutaArchivo): string
    {
        $parser = new PdfParser();
        $pdf    = $parser->parseFile($rutaArchivo);

        return trim($pdf->getText());
    }

    /**
     * Convierte un elemento PhpWord a texto plano recursivamente.
     */
    private function elementoATexto(mixed $element): string
    {
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            $partes = [];
            foreach ($element->getElements() as $child) {
                $partes[] = $this->elementoATexto($child);
            }
            return implode('', $partes);
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
            return $element->getText();
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Paragraph) {
            $partes = [];
            foreach ($element->getElements() as $child) {
                $partes[] = $this->elementoATexto($child);
            }
            return implode('', $partes);
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            $filas = [];
            foreach ($element->getRows() as $row) {
                $celdas = [];
                foreach ($row->getCells() as $cell) {
                    $contenido = [];
                    foreach ($cell->getElements() as $child) {
                        $contenido[] = $this->elementoATexto($child);
                    }
                    $celdas[] = implode(' ', $contenido);
                }
                $filas[] = implode(' | ', $celdas);
            }
            return implode("\n", $filas);
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\ListItem) {
            return '- ' . $this->elementoATexto($element->getTextObject());
        }

        return '';
    }
}
