<?php

namespace App\Services;

use App\Models\FragmentoReglamento;
use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
     * La extracción de texto es opcional - si falla, el registro se crea de
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
            // La extracción de texto falla con gracia - el RIT aún se registra
            Log::warning('ReglamentoInternoService: no se pudo extraer texto del documento', [
                'empresa_id' => $empresaId,
                'archivo'    => basename($rutaArchivo),
                'error'      => $e->getMessage(),
            ]);
        }

        // Conjunto de días hábiles detectado del RIT (soporta domingo y 24/7).
        $diasHabiles = \App\Support\DiasHabiles::detectar($texto);

        $campos = [
            'nombre'         => $nombreOriginal,
            'texto_completo' => $texto ?: null,
            'activo'         => true,
            'fuente'         => 'subido',
            // Legado (binario) - se conserva por compatibilidad; null si no se detecta.
            'dias_laborales' => $this->detectarDiasLaborales($texto),
            // Nuevo: conjunto de días ISO (null si no se detecta → se confirma luego).
            'dias_habiles'   => $diasHabiles,
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

        // Extraer sanciones y generar las conductas sancionables si hay texto.
        if (!empty($texto)) {
            $this->extraerYPersistirSanciones($reglamento);
            $this->generarConductasSancionables($reglamento);
        }

        return $reglamento;
    }

    /**
     * Extrae el texto plano de un archivo (PDF/DOCX/TXT) a partir de su RUTA ABSOLUTA.
     * Reutilizable sin persistir un RIT (p. ej. auditoría en el wizard de registro).
     */
    public function extraerTextoDeArchivo(string $rutaArchivo): string
    {
        $extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf'  => $this->extraerTextoPdf($rutaArchivo),
            default => $this->extraerTextoDocx($rutaArchivo),
        };
    }

    /**
     * Identifica el motivo real por el que no se pudo extraer texto de un documento,
     * para dar un aviso preciso al usuario. Intenta leer el archivo y clasifica:
     *   'protegido' → PDF cifrado o con restricciones (no se puede leer su contenido).
     *   'ilegible'  → se leyó pero no hay texto seleccionable (escaneado como imagen).
     *   'corrupto'  → el archivo está dañado o en un formato no compatible.
     */
    public function motivoTextoVacio(string $rutaArchivo): string
    {
        if (! is_file($rutaArchivo)) {
            return 'corrupto';
        }

        $extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));

        if ($extension === 'pdf' && $this->pdfEstaCifrado($rutaArchivo)) {
            return 'protegido';
        }

        try {
            $extension === 'pdf'
                ? $this->extraerTextoPdf($rutaArchivo)
                : $this->extraerTextoDocx($rutaArchivo);

            // Se pudo leer el archivo pero no arrojó texto → sin texto seleccionable.
            return 'ilegible';
        } catch (\Throwable $e) {
            // pdfparser lanza "Secured pdf file are currently not supported" en cifrados.
            return str_contains(strtolower($e->getMessage()), 'secured')
                ? 'protegido'
                : 'corrupto';
        }
    }

    /** Mensaje al usuario según el motivo devuelto por motivoTextoVacio(). */
    public function mensajeTextoVacio(string $motivo): string
    {
        return match ($motivo) {
            'protegido' => 'El PDF está protegido o cifrado (tiene restricciones), por eso no se puede leer su contenido para detectar las faltas y sanciones. Vuelva a subirlo en Word (.docx) o exporte el PDF sin protección.',
            'corrupto'  => 'No se pudo leer el archivo: puede estar dañado o en un formato no compatible. Verifíquelo y vuelva a subirlo en Word (.docx) o en un PDF con texto seleccionable.',
            default     => 'El PDF no tiene texto seleccionable (parece escaneado como imagen), por eso no se puede leer su contenido para detectar las faltas y sanciones. Vuelva a subirlo en Word (.docx) o en un PDF con texto seleccionable.',
        };
    }

    /** ¿El PDF tiene diccionario /Encrypt (cifrado o con restricciones)? */
    private function pdfEstaCifrado(string $rutaArchivo): bool
    {
        $cabeza = (string) file_get_contents($rutaArchivo, false, null, 0, 4096);

        return str_contains($cabeza . $this->colaArchivo($rutaArchivo), '/Encrypt');
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
        // ── Caso 1: wizard (construido_ia) - datos ya estructurados ───────────
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

        // ── Caso 3: documento subido sin extracción - extraer con IA y persistir
        if (empty($rit->texto_completo)) {
            return [];
        }

        return $this->extraerYPersistirSanciones($rit);
    }

    /**
     * Devuelve las filas EXACTAS de la tabla de sanciones por gravedad (leve/grave/muy grave),
     * con la conducta y la sanción tal como las define el RIT del cliente - sin heurística cuando
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
     * Construye el LISTADO DE CONDUCTAS SANCIONABLES del RIT por gravedad
     * (leve, grave, gravísima), con su medida disciplinaria y base legal.
     * Reemplaza el catálogo estático como fuente de conductas por empresa.
     * Persiste el resultado en $rit->conductas_sancionables y lo devuelve.
     *
     * IMPORTANTE (corregido tras encontrar el problema en producción): cuando
     * hay un RIT real (subido o construido), el texto de cada "conducta" NUNCA
     * se le pide a la IA que lo redacte de nuevo - eso abría la puerta a
     * parafraseo o, peor, a alucinación (inventar una falta que no está en el
     * RIT). En su lugar se reutiliza LITERALMENTE la extracción ya validada de
     * extraerYPersistirSanciones() (la misma que usa el botón "Re-extraer
     * sanciones"), sustituyendo el texto directamente por código - así se
     * garantiza, no solo se le pide por instrucción, que (a) el texto es
     * exactamente el del RIT y (b) no se omite ninguna falta real, porque se
     * itera sobre TODO lo que esa extracción ya encontró, sin filtrar nada.
     */
    public function generarConductasSancionables(ReglamentoInterno $rit): array
    {
        if (!empty($rit->texto_completo)) {
            $conductas = $this->conductasDesdeExtraccionLiteral($rit);
        } elseif (!empty($rit->respuestas_cuestionario['sanciones_configuradas'])) {
            // Ya estructurado por el propio wizard "Construir RIT" (conducta +
            // gravedad + sanción ya definidos) - conversión directa, tampoco
            // hace falta otra llamada a la IA ni hay nada que "extraer".
            $conductas = $this->conductasDesdeSancionesConfiguradas($rit->respuestas_cuestionario['sanciones_configuradas']);
        } else {
            // Sin RIT propio de referencia (empresa aún sin reglamento): aquí sí
            // corresponde síntesis con IA, conforme al CST base - no hay texto
            // fuente que copiar.
            $conductas = $this->generarConductasSinFuenteReal();
        }

        if (!empty($conductas['leve']) || !empty($conductas['grave']) || !empty($conductas['gravisima'])) {
            $rit->conductas_sancionables = $conductas;
            $rit->saveQuietly();
        }

        return $conductas;
    }

    /**
     * Devuelve el organigrama (cargos + instancia_sancionatoria) vigente de una
     * empresa, en la MISMA forma que produce el Repeater 'cargos' del wizard
     * "Construir RIT" (['nombre_cargo' => ..., 'instancia_sancionatoria' => ...]),
     * sin importar si el RIT se construyó con el wizard o se subió/redactó
     * libremente. Consumido por SolicitudContratoResource (select de Cargo) -
     * mismo criterio de "una sola fuente de verdad por empresa" que ya usa
     * conductasSancionablesDeEmpresa(). Nunca llama a la IA aquí (evita costo
     * en cada carga del formulario) - si el RIT es de texto libre y todavía no
     * se generó el organigrama, devuelve vacío; el llamador decide el
     * respaldo (ver generarOrganigramaAction() en MiReglamentoInterno).
     */
    public function cargosDeEmpresa(int $empresaId): array
    {
        $rit = ReglamentoInterno::where('empresa_id', $empresaId)
            ->orderByDesc('activo')
            ->orderByDesc('updated_at')
            ->first();

        if (!$rit) {
            return [];
        }

        // Wizard "Construir RIT": ya viene estructurado, es la fuente más
        // confiable (el propio cliente lo escribió campo por campo).
        $cargosWizard = $rit->respuestas_cuestionario['cargos'] ?? [];
        if (!empty($cargosWizard)) {
            return $cargosWizard;
        }

        // RIT subido/redactado libremente: organigrama ya extraído con IA
        // (ver generarOrganigrama()), persistido para no repetir el llamado
        // en cada carga.
        return $rit->organigrama ?? [];
    }

    /**
     * Extrae con IA el organigrama (cargos + si tienen facultad disciplinaria)
     * del texto del RIT y lo persiste en reglamentos_internos.organigrama.
     * Solo aplica a RIT SUBIDO o redactado libremente - un RIT construido con
     * el wizard "Construir RIT" ya tiene esta información estructurada por el
     * propio cliente (respuestas_cuestionario['cargos']), no hace falta
     * extraerla de nuevo.
     *
     * Mismo criterio que generarConductasSancionables()/extraerYPersistirSanciones():
     * se le pide a la IA EXTRAER (no redactar) los cargos tal como aparecen
     * en el texto - nunca inventar un cargo que la empresa no mencionó.
     */
    public function generarOrganigrama(ReglamentoInterno $rit): array
    {
        if (empty($rit->texto_completo)) {
            return [];
        }

        $prompt = <<<PROMPT
Eres un asistente legal. Lee el siguiente Reglamento Interno de Trabajo (RIT)
de una empresa colombiana y EXTRAE (no inventes) el organigrama: los cargos
que existen en la empresa, tal como aparecen mencionados en el texto (en el
capítulo de organigrama/estructura si existe, o en cualquier parte del
documento donde se nombren cargos con funciones o facultades).

Para cada cargo, determina su facultad disciplinaria SOLO si el texto lo dice
explícitamente (quién impone sanciones, quién resuelve apelaciones):
- "primera_instancia": impone la sanción directamente.
- "segunda_instancia": resuelve apelaciones/recursos contra una sanción.
- "ninguna": no tiene facultad disciplinaria mencionada, o no se menciona.

Si el RIT no menciona ningún cargo de forma explícita, responde con un arreglo
vacío - no inventes cargos genéricos.

TEXTO DEL RIT:
{$rit->texto_completo}

Responde ÚNICAMENTE con un JSON válido: un arreglo con esta estructura exacta:
[
  {"nombre_cargo": "nombre exacto tal como aparece en el texto", "instancia_sancionatoria": "ninguna|primera_instancia|segunda_instancia"}
]
PROMPT;

        try {
            $datos = $this->parsearJSON($this->llamarGeminiJSON($prompt));
            $lista = array_is_list($datos) ? $datos : ($datos['organigrama'] ?? $datos['cargos'] ?? []);

            $organigrama = [];
            foreach (array_slice(is_array($lista) ? $lista : [], 0, 60) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $nombre = trim((string) ($item['nombre_cargo'] ?? ''));
                if ($nombre === '') {
                    continue;
                }
                $instancia = $item['instancia_sancionatoria'] ?? 'ninguna';
                $organigrama[] = [
                    'nombre_cargo' => $nombre,
                    'instancia_sancionatoria' => in_array($instancia, ['ninguna', 'primera_instancia', 'segunda_instancia'], true)
                        ? $instancia
                        : 'ninguna',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('ReglamentoInternoService: error generando organigrama del RIT', [
                'reglamento_id' => $rit->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (!empty($organigrama)) {
            $rit->organigrama = $organigrama;
            $rit->saveQuietly();
        }

        return $organigrama;
    }

    /**
     * Construye el catálogo a partir de la extracción LITERAL ya existente
     * (o la genera si aún no se ha corrido) - ver la nota en
     * generarConductasSancionables(). El número de artículo se detecta por
     * código (buscando el "ARTÍCULO N." más cercano hacia atrás en el propio
     * texto del capítulo), nunca por la IA.
     */
    private function conductasDesdeExtraccionLiteral(ReglamentoInterno $rit): array
    {
        $sanciones = $rit->sanciones_extraidas;
        if (empty($sanciones)) {
            $sanciones = $this->extraerYPersistirSanciones($rit);
        }

        $vacio = ['leve' => [], 'grave' => [], 'gravisima' => []];
        if (empty($sanciones)) {
            return $vacio;
        }

        $capitulo = $this->extraerCapituloDisciplinario($rit->texto_completo ?? '');

        $mapa = [
            'leve'      => ['faltas' => $sanciones['faltas_leves']      ?? [], 'sancion' => $sanciones['sancion_leve']      ?? ''],
            'grave'     => ['faltas' => $sanciones['faltas_graves']     ?? [], 'sancion' => $sanciones['sancion_grave']     ?? ''],
            'gravisima' => ['faltas' => $sanciones['faltas_muy_graves'] ?? [], 'sancion' => $sanciones['sancion_muy_grave'] ?? ''],
        ];

        $conductas = $vacio;
        foreach ($mapa as $gravedad => $datos) {
            foreach ($datos['faltas'] as $faltaTexto) {
                $faltaTexto = trim((string) $faltaTexto);
                if ($faltaTexto === '') {
                    continue;
                }

                [$tipo, $dias] = $this->inferirTipoYDiasDeSancion($datos['sancion'], $gravedad);
                $articulo = $this->articuloQuePrecedeEnTexto($faltaTexto, $capitulo);

                $conductas[$gravedad][] = [
                    'conducta'        => $faltaTexto, // literal, tal cual la extracción - nunca reescrito
                    'medida'          => $datos['sancion'] ?: $this->medidaPorDefecto($gravedad),
                    'tipo'            => $tipo,
                    'dias_suspension' => $dias,
                    'base_legal'      => $articulo ?? 'RIT de la empresa',
                ];
            }
        }

        return $conductas;
    }

    /** Convierte sanciones_configuradas (del wizard) directamente, sin IA. */
    private function conductasDesdeSancionesConfiguradas(array $config): array
    {
        $conductas    = ['leve' => [], 'grave' => [], 'gravisima' => []];
        $mapaGravedad = ['leve' => 'leve', 'grave' => 'grave', 'muy_grave' => 'gravisima'];
        $tiposValidos = ['llamado_atencion', 'suspension', 'multa', 'terminacion'];

        foreach ($config as $s) {
            $gravedad = $mapaGravedad[$s['tipo_falta'] ?? ''] ?? null;
            $nombre   = trim((string) ($s['nombre'] ?? ''));
            if (!$gravedad || $nombre === '') {
                continue;
            }

            $tipo = in_array($s['tipo_sancion'] ?? null, $tiposValidos, true)
                ? $s['tipo_sancion']
                : $this->inferirTipoYDiasDeSancion(null, $gravedad)[0];
            $dias = ($tipo === 'suspension' && is_numeric($s['dias_suspension'] ?? null))
                ? (int) $s['dias_suspension']
                : null;

            $conductas[$gravedad][] = [
                'conducta'        => $nombre, // literal, tal cual lo definió el wizard
                'medida'          => $dias ? "Suspensión hasta {$dias} día(s)" : $this->medidaPorDefecto($gravedad),
                'tipo'            => $tipo,
                'dias_suspension' => $dias,
                'base_legal'      => 'RIT de la empresa (constructor)',
            ];
        }

        return $conductas;
    }

    /** Único caso donde SÍ corresponde síntesis con IA: sin ningún RIT propio de referencia. */
    private function generarConductasSinFuenteReal(): array
    {
        $prompt = <<<PROMPT
Eres un abogado laboralista colombiano. Genera el LISTADO DE CONDUCTAS SANCIONABLES para el Reglamento Interno de Trabajo (RIT) de una empresa que aún no tiene reglamento propio, clasificadas por gravedad (leve, grave, gravísima), conforme al Código Sustantivo del Trabajo (CST). Este contenido será PÚBLICO dentro del RIT: debe ser claro, concreto y jurídicamente correcto.

Responde ÚNICAMENTE con un JSON válido, sin texto adicional, con esta estructura exacta:
{
  "leve":      [{"conducta": "descripción concreta", "medida": "medida disciplinaria legible", "tipo": "llamado_atencion|suspension|multa|terminacion", "dias_suspension": null, "base_legal": "artículo del CST que la sustenta"}],
  "grave":     [{"conducta": "...", "medida": "...", "tipo": "...", "dias_suspension": null, "base_legal": "..."}],
  "gravisima": [{"conducta": "...", "medida": "...", "tipo": "...", "dias_suspension": null, "base_legal": "..."}]
}

Reglas:
- Genera un catálogo base razonable, entre 5 y 12 conductas CONCRETAS por gravedad (no genéricas ni repetidas), conforme al CST.
- Proporcionalidad y gradualidad: leve → llamado de atención; grave → suspensión; gravísima → terminación del contrato con justa causa.
- dias_suspension: entero SOLO cuando el tipo es "suspension"; en los demás casos null.
- base_legal: cita el artículo del CST (p. ej. Art. 58, 60, 62 CST). NO inventes normas.
- No incluyas conductas discriminatorias ni que violen derechos fundamentales.
- Español colombiano, claro y sin tecnicismos innecesarios.
PROMPT;

        try {
            $datos = $this->parsearJSON($this->llamarGeminiJSON($prompt));
            return $this->normalizarConductas($datos);
        } catch (\Throwable $e) {
            Log::warning('ReglamentoInternoService: error generando conductas sancionables (sin fuente real)', [
                'error' => $e->getMessage(),
            ]);
            return ['leve' => [], 'grave' => [], 'gravisima' => []];
        }
    }

    /** Deriva tipo/días de la sanción EXTRAÍDA del RIT (texto libre) - sin IA. */
    private function inferirTipoYDiasDeSancion(?string $sancion, string $gravedad): array
    {
        $texto = mb_strtolower($sancion ?? '');
        if ($texto === '') {
            return match ($gravedad) {
                'gravisima' => ['terminacion', null],
                'grave'     => ['suspension', null],
                default     => ['llamado_atencion', null],
            };
        }

        if (str_contains($texto, 'terminaci')) {
            return ['terminacion', null];
        }
        if (str_contains($texto, 'multa')) {
            return ['multa', null];
        }
        if (str_contains($texto, 'suspensi')) {
            if (preg_match('/(\d+)\s*d[ií]as?/u', $texto, $m)) {
                return ['suspension', (int) $m[1]];
            }
            return ['suspension', null];
        }

        return ['llamado_atencion', null];
    }

    private function medidaPorDefecto(string $gravedad): string
    {
        return match ($gravedad) {
            'gravisima' => 'Terminación del contrato con justa causa',
            'grave'     => 'Suspensión del trabajo',
            default     => 'Llamado de atención escrito',
        };
    }

    /**
     * Busca el fragmento (literal, o por su prefijo si el texto completo no
     * calza) dentro del capítulo disciplinario y devuelve el "ARTÍCULO N." más
     * cercano que lo precede - determinístico, sin IA. Null si no se
     * encuentra, en cuyo caso el llamador usa un base_legal genérico.
     */
    private function articuloQuePrecedeEnTexto(string $fragmento, string $texto): ?string
    {
        if ($texto === '' || $fragmento === '') {
            return null;
        }

        $pos = mb_stripos($texto, $fragmento);
        if ($pos === false) {
            $palabras = preg_split('/\s+/u', trim($fragmento)) ?: [];
            if (count($palabras) > 5) {
                $prefijo = implode(' ', array_slice($palabras, 0, 5));
                $pos = mb_stripos($texto, $prefijo);
            }
        }
        if ($pos === false) {
            return null;
        }

        $antes = mb_substr($texto, 0, $pos);
        // Solo encabezados reales de artículo ("ARTÍCULO 45." al inicio de
        // línea) - NO cualquier mención de "artículo" dentro de una oración,
        // como "conforme al Artículo 112 del Código Sustantivo del Trabajo"
        // (una referencia al CST dentro del párrafo, no el encabezado del RIT
        // - encontrado como bug real: sin este anclaje, las faltas graves de
        // este RIT quedaban atribuidas al "Artículo 112" en vez del 46).
        if (preg_match_all('/^ART[IÍ]CULO\s+(\d+)\./mu', $antes, $matches) && !empty($matches[1])) {
            return 'Artículo ' . end($matches[1]) . ' RIT';
        }

        return null;
    }

    /** Sanea y limita la estructura de conductas por gravedad devuelta por la IA. */
    private function normalizarConductas(array $datos): array
    {
        $tiposValidos = ['llamado_atencion', 'suspension', 'multa', 'terminacion'];

        $limpiar = function ($item) use ($tiposValidos) {
            $tipo = in_array($item['tipo'] ?? null, $tiposValidos, true) ? $item['tipo'] : 'llamado_atencion';
            $dias = ($tipo === 'suspension' && is_numeric($item['dias_suspension'] ?? null))
                ? (int) $item['dias_suspension']
                : null;

            return [
                'conducta'        => trim((string) ($item['conducta'] ?? '')),
                'medida'          => trim((string) ($item['medida'] ?? '')),
                'tipo'            => $tipo,
                'dias_suspension' => $dias,
                'base_legal'      => trim((string) ($item['base_legal'] ?? '')),
            ];
        };

        // Sin tope artificial de cantidad - antes se cortaba en 12 por gravedad,
        // omitiendo conductas reales del RIT en reglamentos con listas largas. El
        // límite de 200 es solo salvaguarda contra una respuesta corrupta/repetida
        // de la IA, nunca pensado para recortar contenido legítimo.
        $out = [];
        foreach (['leve', 'grave', 'gravisima'] as $g) {
            $items   = array_slice(is_array($datos[$g] ?? null) ? $datos[$g] : [], 0, 200);
            $out[$g] = array_values(array_filter(array_map($limpiar, $items), fn($i) => $i['conducta'] !== ''));
        }

        return $out;
    }

    /**
     * Listado base de conductas sancionables derivadas del CST, para empresas que NO
     * están obligadas a tener RIT (o aún no lo tienen). Es un respaldo razonable, no
     * sustituye el análisis del caso concreto.
     */
    public function conductasCstBase(): array
    {
        return [
            'leve' => [
                ['conducta' => 'Llegadas tarde reiteradas sin justificación',        'medida' => 'Llamado de atención escrito', 'tipo' => 'llamado_atencion', 'dias_suspension' => null, 'base_legal' => 'Art. 58 CST'],
                ['conducta' => 'Ausentarse del puesto por corto tiempo sin permiso',  'medida' => 'Llamado de atención escrito', 'tipo' => 'llamado_atencion', 'dias_suspension' => null, 'base_legal' => 'Art. 58 CST'],
                ['conducta' => 'Descuido leve en el uso de herramientas o elementos',  'medida' => 'Llamado de atención escrito', 'tipo' => 'llamado_atencion', 'dias_suspension' => null, 'base_legal' => 'Art. 58 CST'],
            ],
            'grave' => [
                ['conducta' => 'Faltar un día al trabajo sin justa causa ni aviso',    'medida' => 'Suspensión hasta 8 días',      'tipo' => 'suspension',       'dias_suspension' => 8,    'base_legal' => 'Art. 60 CST'],
                ['conducta' => 'Incumplir órdenes legítimas relacionadas con el cargo', 'medida' => 'Suspensión hasta 8 días',      'tipo' => 'suspension',       'dias_suspension' => 8,    'base_legal' => 'Art. 58 y 60 CST'],
                ['conducta' => 'Reincidir en faltas leves ya sancionadas',             'medida' => 'Suspensión laboral',           'tipo' => 'suspension',       'dias_suspension' => 5,    'base_legal' => 'Art. 60 CST'],
            ],
            'gravisima' => [
                ['conducta' => 'Agresión física a un compañero o superior en el trabajo', 'medida' => 'Terminación del contrato con justa causa', 'tipo' => 'terminacion', 'dias_suspension' => null, 'base_legal' => 'Art. 62 CST'],
                ['conducta' => 'Grave violación de las obligaciones o prohibiciones',     'medida' => 'Terminación del contrato con justa causa', 'tipo' => 'terminacion', 'dias_suspension' => null, 'base_legal' => 'Art. 62 CST'],
                ['conducta' => 'Daño material grave e intencional a bienes de la empresa', 'medida' => 'Terminación del contrato con justa causa', 'tipo' => 'terminacion', 'dias_suspension' => null, 'base_legal' => 'Art. 62 CST'],
            ],
        ];
    }

    /**
     * Conductas sancionables EFECTIVAS de una empresa: del RIT activo si existen; si no,
     * el respaldo base del CST. Fuente única para el flujo de descargos y análisis.
     */
    public function conductasSancionablesDeEmpresa(int $empresaId): array
    {
        // Incluye fuente=mejora_ia a propósito: si el cliente ya adoptó un RIT
        // mejorado por la IA, ES su reglamento vigente - excluirlo aquí hacía
        // que este método volviera silenciosamente a las conductas del RIT
        // ANTERIOR (ya reemplazado, todavía en BD con activo=false), en vez de
        // las del RIT que realmente rige hoy (caso real: RENBEL 2.0).
        $rit = ReglamentoInterno::where('empresa_id', $empresaId)
            ->orderByDesc('activo')
            ->orderByDesc('updated_at')
            ->first();

        $conductas = $rit?->conductas_sancionables;

        if (is_array($conductas) && (!empty($conductas['leve']) || !empty($conductas['grave']) || !empty($conductas['gravisima']))) {
            return $conductas;
        }

        return $this->conductasCstBase();
    }

    /**
     * Genera con IA el régimen disciplinario para el CONSTRUCTOR de RIT (wizard), a
     * partir del contexto de la empresa (actividad, cargos). Devuelve filas con la
     * forma del repeater 'sanciones_configuradas' (nombre, tipo_falta, tipo_sancion,
     * dias_suspension). Reemplaza el catálogo estático de Sanciones Laborales.
     */
    public function generarConductasParaWizard(array $contexto): array
    {
        $actividad  = trim((string) ($contexto['actividad'] ?? ''));
        $cargos     = trim((string) ($contexto['cargos'] ?? ''));
        $riesgos    = trim((string) ($contexto['riesgos'] ?? ''));
        $prevenir   = trim((string) ($contexto['prevenir'] ?? ''));
        $existentes = array_values(array_filter(array_map(
            fn($n) => trim((string) $n),
            $contexto['existentes'] ?? []
        )));

        $ctx = '';
        if ($actividad !== '') {
            $ctx .= "- Actividad económica: {$actividad}\n";
        }
        if ($cargos !== '') {
            $ctx .= "- Cargos de la empresa: {$cargos}\n";
        }
        if ($riesgos !== '') {
            $ctx .= "- Principales riesgos identificados en la empresa: {$riesgos}\n";
        }
        if ($prevenir !== '') {
            $ctx .= "- Situaciones que la empresa quiere prevenir especialmente: {$prevenir}\n";
        }

        // Pedido explícito del usuario: al darle de nuevo al botón para
        // conseguir MÁS conductas, la IA no debe repetir/parafrasear las que
        // ya están en el listado (antes se reemplazaba todo, así que esto
        // nunca importó; ahora se agrega al final).
        $instruccionExistentes = '';
        if (!empty($existentes)) {
            $listaExistentes = implode("\n", array_map(fn($n) => "- {$n}", $existentes));
            $instruccionExistentes = <<<TXT


            CONDUCTAS QUE YA ESTÁN EN EL LISTADO (el usuario pidió MÁS conductas para agregar, no reemplazarlas):
            {$listaExistentes}

            PROHIBIDO repetir o parafrasear ninguna de las conductas de arriba - genera SOLO conductas NUEVAS y distintas a esas.
            TXT;
        }

        $reglaCantidad = empty($existentes)
            ? 'Entre 15 y 30 conductas en total, repartidas entre leve, grave y muy_grave.'
            : 'Entre 10 y 20 conductas NUEVAS adicionales (sin contar las ya existentes), repartidas entre leve, grave y muy_grave.';

        $prompt = <<<PROMPT
Eres un abogado laboralista colombiano. Genera el RÉGIMEN DISCIPLINARIO (conductas sancionables y su medida) para el Reglamento Interno de Trabajo de una empresa colombiana, conforme al Código Sustantivo del Trabajo (CST). Este contenido será PÚBLICO dentro del RIT.

CONTEXTO DE LA EMPRESA:
{$ctx}{$instruccionExistentes}
Responde ÚNICAMENTE con un JSON válido: un arreglo de conductas con esta estructura exacta:
[
  {"nombre": "descripción concreta de la conducta", "tipo_falta": "leve|grave|muy_grave", "tipo_sancion": "llamado_atencion|suspension|terminacion", "dias_suspension": null}
]

Reglas:
- {$reglaCantidad}
- Proporcionalidad y gradualidad: leve → llamado_atencion; grave → suspension; muy_grave → terminacion.
- dias_suspension: entero SOLO cuando tipo_sancion es "suspension" (máximo 8 días la primera vez); en los demás casos null.
- Incluye conductas propias del sector/actividad, de los cargos y de los riesgos indicados (ej. si hay riesgo mecánico, eléctrico o de alturas, incluye conductas de seguridad relacionadas con ese riesgo).
- No inventes normas ni incluyas conductas discriminatorias. Español colombiano, claro y concreto.
PROMPT;

        try {
            $datos = $this->parsearJSON($this->llamarGeminiJSON($prompt));
            $lista = array_is_list($datos) ? $datos : ($datos['conductas'] ?? $datos['regimen'] ?? []);

            return $this->filtrarConductasDuplicadas(
                $this->normalizarConductasWizard(is_array($lista) ? $lista : []),
                $existentes
            );
        } catch (\Throwable $e) {
            Log::warning('ReglamentoInternoService: error generando conductas para el wizard', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /** Sanea el listado de conductas a la forma del repeater del wizard. */
    private function normalizarConductasWizard(array $lista): array
    {
        $faltas    = ['leve', 'grave', 'muy_grave'];
        $sanciones = ['llamado_atencion', 'suspension', 'terminacion'];

        $out = [];
        foreach (array_slice($lista, 0, 40) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $nombre = trim((string) ($item['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }

            $falta   = in_array($item['tipo_falta'] ?? null, $faltas, true) ? $item['tipo_falta'] : 'leve';
            $sancion = in_array($item['tipo_sancion'] ?? null, $sanciones, true)
                ? $item['tipo_sancion']
                : match ($falta) {
                    'muy_grave' => 'terminacion',
                    'grave'     => 'suspension',
                    default     => 'llamado_atencion',
                };
            $dias = ($sancion === 'suspension' && is_numeric($item['dias_suspension'] ?? null))
                ? min(8, max(1, (int) $item['dias_suspension']))
                : null;

            $out[] = [
                'nombre'          => $nombre,
                'tipo_falta'      => $falta,
                'tipo_sancion'    => $sancion,
                'dias_suspension' => $dias,
            ];
        }

        return $out;
    }

    /**
     * Segunda capa de defensa contra duplicados: la instrucción del prompt
     * ("PROHIBIDO repetir o parafrasear") no es garantía - se compara cada
     * conducta nueva contra las existentes de forma normalizada (sin
     * tildes/mayúsculas/puntuación/espacios extra) para atrapar la
     * repetición literal (incluida la que solo cambia signos de puntuación
     * o mayúsculas). NO se usa similitud de texto (similar_text) como
     * segundo filtro: se probó empíricamente y dos conductas de SST
     * legítimamente distintas ("...zonas de riesgo mecánico" vs "...zonas
     * de riesgo eléctrico") dan 94.9% de similitud - más alto que el propio
     * caso de repetición que se quería atrapar (75.9%). Un umbral así
     * borraría conductas reales y distintas; mejor dejar pasar alguna
     * parafraseada que perder cobertura legítima.
     */
    private function filtrarConductasDuplicadas(array $nuevas, array $existentes): array
    {
        if (empty($existentes)) {
            return $nuevas;
        }

        $existentesNorm = array_flip(array_map(fn($n) => $this->normalizarTextoConducta($n), $existentes));

        return array_values(array_filter(
            $nuevas,
            fn($conducta) => ! isset($existentesNorm[$this->normalizarTextoConducta($conducta['nombre'] ?? '')])
        ));
    }

    private function normalizarTextoConducta(string $texto): string
    {
        $ascii = Str::of($texto)->lower()->ascii()->toString();

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]/', ' ', $ascii)));
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
Analiza el siguiente capítulo del Reglamento Interno de Trabajo de una empresa colombiana y extrae LITERALMENTE la lista de faltas laborales que ya están redactadas en el texto - esto va a una tabla legal que se envía al trabajador, así que debe coincidir palabra por palabra con el Reglamento, no con tu propia interpretación.

TEXTO DEL REGLAMENTO:
{$fragmento}

Responde ÚNICAMENTE con un JSON válido, sin texto adicional, con esta estructura exacta:
{
  "faltas_leves": ["texto literal de la falta 1 tal como aparece en el RIT", "texto literal de la falta 2"],
  "faltas_graves": ["texto literal de la falta 1 tal como aparece en el RIT", "texto literal de la falta 2"],
  "faltas_muy_graves": ["texto literal de la falta 1 tal como aparece en el RIT"],
  "sancion_leve": "la sanción EXACTA que el RIT asigna a las faltas LEVES",
  "sancion_grave": "la sanción EXACTA que el RIT asigna a las faltas GRAVES",
  "sancion_muy_grave": "la sanción EXACTA que el RIT asigna a las faltas MUY GRAVES (o '' si no las distingue)",
  "sanciones": ["Llamado de Atención Verbal", "Suspensión hasta X días", "Terminación del Contrato"]
}

Reglas:
- PROHIBIDO resumir, parafrasear, acortar o "poner algo similar" a cada falta - copia el texto EXACTO
  de cada conducta/falta tal como está redactada literalmente en el Reglamento (numeral, artículo o
  viñeta completa). Si el RIT enumera una falta en una frase larga, cópiala completa - no la recortes
  para que quepa en pocos caracteres.
- faltas_leves, faltas_graves y faltas_muy_graves: SIN LÍMITE de cantidad ni de caracteres - incluye
  TODAS y cada una de las faltas que el texto describa para esa gravedad, así sean 3 o sean 40. El
  largo y la cantidad los define el propio texto del RIT, nunca un resumen ni un recorte tuyo. Omitir
  una falta real porque "ya hay suficientes" está PROHIBIDO - esta lista se usa después para decidir
  sanciones reales y ninguna falta puede faltar.
- sancion_leve / sancion_grave / sancion_muy_grave: copia EXACTA (textual) de la sanción que el RIT
  asigna a cada nivel de gravedad. Si el RIT no separa "muy graves", deja faltas_muy_graves vacío y
  sancion_muy_grave en "". NO inventes la sanción: si no está clara, deja la cadena vacía.
- sanciones: lista legible de TODAS las sanciones que menciona el RIT (respaldo)
- Si el texto no tiene información clara de faltas, devuelve arrays vacíos - NUNCA inventes una falta
  ni la aproximes con conocimiento general de derecho laboral si no está literalmente en este RIT.
- No listes artículos del CST genéricos; solo lo que describe concretamente este RIT
- PROHIBIDO incluir cláusulas de "Condiciones de Admisión" o "Período de Prueba" (ej. terminar el
  contrato con justa causa si el aspirante mintió en la hoja de vida, o si no completó satisfactoriamente
  el período de prueba) - esos son casos especiales de CONTRATACIÓN, no faltas disciplinarias generales
  que apliquen a cualquier incidente durante la relación laboral ya vigente. Solo incluye conductas que
  describan comportamiento del trabajador YA VINCULADO (asistencia, disciplina, seguridad, uso de
  recursos, relación con superiores/compañeros, etc.).
PROMPT;

        try {
            $respuesta = $this->llamarGeminiJSON($prompt);
            $datos     = $this->parsearJSON($respuesta);

            $nz = fn($v) => (is_string($v) && trim($v) !== '') ? trim($v) : null;

            // Sin tope artificial de cantidad - antes se cortaba en 10 por gravedad,
            // omitiendo faltas reales del RIT en reglamentos con listas largas. El
            // límite de 200 es solo una salvaguarda contra una respuesta corrupta/
            // repetida de la IA, nunca pensado para recortar contenido legítimo.
            return [
                'faltas_leves'      => array_slice($datos['faltas_leves']  ?? [], 0, 200),
                'faltas_graves'     => array_slice($datos['faltas_graves'] ?? [], 0, 200),
                'faltas_muy_graves' => array_slice($datos['faltas_muy_graves'] ?? [], 0, 200),
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
        // 5000 truncaba capítulos disciplinarios largos ANTES de que la IA los viera -
        // un RIT con muchas faltas enumeradas (20-40+, común en reglamentos detallados)
        // fácilmente supera esa cifra, así que las últimas faltas nunca llegaban a
        // extraerSancionesConIA()/generarConductasSancionables(). El requisito es no
        // omitir ninguna falta real del RIT, así que el techo sube a un valor muy por
        // encima de cualquier capítulo disciplinario real (el contexto de Gemini
        // soporta esto sin problema); sigue existiendo solo como salvaguarda contra un
        // capítulo mal delimitado que termine abarcando el documento entero.
        $maxChars = 60000;

        $capitulosRef  = ['RÉGIMEN DISCIPLINARIO', 'REGIMEN DISCIPLINARIO', 'FALTAS', 'SANCIONES', 'ESCALA DE SANCIONES'];
        $palabrasClave = ['falta', 'sanc', 'disciplin', 'descargo', 'amonestac', 'suspens', 'multa'];

        // Capítulos que NUNCA deben tratarse como régimen disciplinario aunque una
        // palabra clave suelta coincida cerca - ej. "Condiciones de Admisión" suele
        // tener una cláusula sobre terminar el contrato con justa causa si el
        // aspirante mintió en la hoja de vida; eso es un caso especial de
        // CONTRATACIÓN, no una falta disciplinaria general aplicable a cualquier
        // incidente. Caso real (SARMIENTO 2.0 S.A.): esa cláusula de admisión se
        // coló como "falta grave" en la tabla de sanciones de una citación por un
        // motivo de calidad farmacéutica, completamente ajeno.
        $capitulosExcluidos = ['ADMISI', 'PERIODO DE PRUEBA', 'PERÍODO DE PRUEBA'];

        // Mapear el rango de líneas de cada CAPÍTULO para poder excluir los que no
        // son de régimen disciplinario, sin importar qué estrategia los detecte.
        $capitulos = [];
        $actual = null;
        foreach ($lineas as $i => $linea) {
            if (preg_match('/CAP[IÍ]TULO/ui', $linea)) {
                if ($actual !== null) {
                    $actual['fin'] = $i - 1;
                    $capitulos[] = $actual;
                }
                // El título descriptivo suele venir en la MISMA línea ("CAPITULO II
                // CONDICIONES DE ADMISION") o en 1-2 líneas siguientes ("CAPITULO II" /
                // "CONDICIONES DE ADMISION" separadas) - se juntan las próximas 2 líneas
                // no vacías para no perder la exclusión por ese detalle de formato.
                $titulo = mb_strtoupper($linea);
                for ($k = $i + 1; $k <= min($i + 2, $total - 1); $k++) {
                    if (preg_match('/CAP[IÍ]TULO/ui', $lineas[$k])) break;
                    $titulo .= ' ' . mb_strtoupper($lineas[$k]);
                }
                $actual = ['inicio' => $i, 'titulo' => $titulo, 'fin' => $total - 1];
            }
        }
        if ($actual !== null) {
            $capitulos[] = $actual;
        }

        $esLineaExcluida = function (int $linea) use ($capitulos, $capitulosExcluidos): bool {
            foreach ($capitulos as $cap) {
                if ($linea < $cap['inicio'] || $linea > $cap['fin']) {
                    continue;
                }
                foreach ($capitulosExcluidos as $ex) {
                    if (str_contains($cap['titulo'], mb_strtoupper($ex))) {
                        return true;
                    }
                }
                return false;
            }
            return false;
        };

        // Estrategia 1: buscar encabezado CAPÍTULO.
        //
        // Bug real corregido (RIT tradicional de 23 capítulos, caso SARMIENTO):
        // esto antes comparaba $capitulosRef contra la línea SUELTA que
        // contenía "CAPITULO", sin usar el título ya armado en $capitulos[]
        // (que junta las 1-2 líneas siguientes - ver el bucle que arma
        // $capitulos arriba). El formato más común es "CAPITULO XVIII" en una
        // línea y "ESCALA DE FALTAS Y SANCIONES DISCIPLINARÍAS" en la
        // siguiente - con la comparación de una sola línea, esto NUNCA
        // encontraba el capítulo real (el título completo estaba en la línea
        // de al lado, no en la que tenía "CAPITULO"), así que siempre caía a
        // la Estrategia 2 (palabra suelta ±10 líneas) - mucho más frágil en
        // RITs largos: agarró "sanciones" de una frase incidental dentro de
        // OTRO capítulo ("...se aplicarán las sanciones establecidas para
        // ausencia...", dentro del capítulo de horario/descansos) muy antes
        // del capítulo disciplinario real, devolviendo contenido de horario/
        // trabajo suplementario en vez de faltas y sanciones.
        //
        // Ahora se busca sobre $capitulos[] (título completo ya unido), y solo
        // en capítulos NO excluidos - detecta el título real sin importar si
        // quedó en la misma línea o en la siguiente.
        $inicio = null;
        foreach ($capitulos as $cap) {
            $esExcluido = false;
            foreach ($capitulosExcluidos as $ex) {
                if (str_contains($cap['titulo'], mb_strtoupper($ex))) {
                    $esExcluido = true;
                    break;
                }
            }
            if ($esExcluido) continue;

            foreach ($capitulosRef as $keyword) {
                if (str_contains($cap['titulo'], mb_strtoupper($keyword))) {
                    $inicio = $cap['inicio'];
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

        // Estrategia 2: palabras clave con ±10 líneas de contexto - saltando
        // cualquier línea que caiga dentro de un capítulo excluido.
        $indices = [];
        foreach ($lineas as $i => $linea) {
            if ($esLineaExcluida($i)) continue;
            $lineaNorm = mb_strtolower($linea);
            foreach ($palabrasClave as $clave) {
                if (str_contains($lineaNorm, $clave)) {
                    for ($j = max(0, $i - 5); $j <= min($total - 1, $i + 10); $j++) {
                        if ($esLineaExcluida($j)) continue;
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
                // 2048 truncaba a mitad de JSON las respuestas más largas (ej.
                // generarConductasSancionables() pide hasta 12 conductas x 3
                // gravedades x 5 campos con oraciones completas) - el JSON
                // incompleto fallaba en parsearJSON() y el catch silencioso
                // devolvía [] sin ningún error visible. Confirmado real
                // reproduciendo la llamada: la respuesta cruda quedaba cortada
                // en medio de un objeto, nunca cerraba el JSON. Subido de nuevo
                // (8192 -> 16384) al quitar los topes de cantidad de faltas/
                // conductas por gravedad: un RIT con listas largas y sin límite
                // artificial puede generar una respuesta bastante más extensa.
                'maxOutputTokens'  => 16384,
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
    // RAG - Fragmentación, embeddings y búsqueda semántica del RIT subido
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
            $lineas[] = "--- [RIT fragmento - relevancia {$pct}%] ---";
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
            ->latest('updated_at')
            ->first();

        return $reglamento?->texto_completo;
    }

    /**
     * Extracto del capítulo de régimen disciplinario (obligaciones, prohibiciones,
     * faltas y sanciones) del RIT activo de la empresa - reutiliza
     * extraerCapituloDisciplinario(), la misma extracción que ya usa
     * generarConductasSancionables(). Pensado para mostrarlo como referencia
     * rápida cuando el clasificador de gravedad pide "aportar el capítulo del
     * RIT" en vez de hacer que el usuario lo transcriba a mano - ese dato ya
     * está en el sistema. Null si la empresa no tiene RIT activo con texto.
     */
    public function obtenerCapituloDisciplinarioTexto(int $empresaId): ?string
    {
        $texto = $this->getTextoReglamento($empresaId);
        if (!$texto) {
            return null;
        }

        $capitulo = trim($this->extraerCapituloDisciplinario($texto));
        if ($capitulo === '') {
            return null;
        }

        // Tope defensivo para la vista (caja con scroll, no necesita el capítulo
        // completo sin límite si el RIT es muy extenso) - mismo criterio de
        // truncado que obtenerContextoRIT() en IADescargoService.
        return mb_substr($capitulo, 0, 6000);
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
