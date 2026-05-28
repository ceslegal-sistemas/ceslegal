<?php

namespace App\Services;

use App\Models\AuditoriaRIT;
use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Genera un RIT mejorado (v+1) a partir de una auditoría completada.
 *
 * Flujo:
 * 1. Obtiene el texto del RIT auditado.
 * 2. Parsea el texto original en sus 16 capítulos.
 * 3. Por cada capítulo: inyecta artículos del scraper + RAG + hallazgos de auditoría.
 * 4. Llama a Gemini capítulo por capítulo (cascade flash → flash-lite).
 * 5. Ensambla el texto completo y crea un nuevo ReglamentoInterno (version+1).
 * 6. Genera PDF permanente con DomPDF.
 *
 * REGLA ANTI-ALUCINACIÓN: ningún artículo, ley, porcentaje ni plazo se hardcodea
 * en los prompts. Todo contenido legal proviene de:
 *   a) articulosObligatorios → tabla articulos_legales (scraper leyes.co)
 *   b) rag → tabla fragmentos_documento (biblioteca RAG)
 */
class RITMejoradoService
{
    /**
     * Mapeo capítulo (número romano) → claves de secciones en AuditoriaRIT::secciones.
     * Capítulos sin hallazgos específicos reciben lista vacía.
     */
    private const CAPITULO_A_SECCION = [
        'I'    => [],
        'II'   => ['admision'],
        'III'  => ['jornada'],
        'IV'   => ['jornada'],
        'V'    => ['salario'],
        'VI'   => ['descansos'],
        'VII'  => ['descansos'],
        'VIII' => ['disciplina'],
        'IX'   => ['disciplina'],
        'X'    => [],
        'XI'   => [],
        'XII'  => ['sst'],
        'XIII' => [],
        'XIV'  => ['acoso'],
        'XV'   => ['grupos_protegidos'],
        'XVI'  => [],
    ];

    private string $modeloUsado = 'gemini-2.5-flash';

    public function __construct(
        private BibliotecaLegalService $biblioteca,
        private RITGeneratorService    $ritGenerator,
    ) {}

    // ── Punto de entrada ──────────────────────────────────────────────────────

    /**
     * Genera el RIT mejorado capítulo a capítulo, persiste archivos y actualiza auditoría.
     *
     * @param  \Closure|null  $onProgress  fn(int $cap, int $total, string $titulo): void
     * @throws \RuntimeException si el texto base está vacío o todos los modelos fallan.
     */
    public function generar(AuditoriaRIT $auditoria, ?\Closure $onProgress = null): ReglamentoInterno
    {
        $empresa = $auditoria->empresa;

        // ── 1. Mejorar capítulo por capítulo ──────────────────────────────────
        $textoMejorado = $this->mejorarCapitulosRIT($auditoria, $onProgress);

        // ── 2. Determinar versión ─────────────────────────────────────────────
        $ritOrigen       = $auditoria->reglamento;
        $siguienteVersion = ($ritOrigen?->version ?? 1) + 1;
        $nombreMejorado  = ($ritOrigen?->nombre ?? 'Reglamento Interno de Trabajo')
            . " (v{$siguienteVersion})";

        // ── 3. Crear nuevo ReglamentoInterno ──────────────────────────────────
        $ritMejorado = ReglamentoInterno::create([
            'empresa_id'              => $empresa->id,
            'nombre'                  => $nombreMejorado,
            'texto_completo'          => $textoMejorado,
            'ruta_docx'               => null,
            'activo'                  => false,
            'respuestas_cuestionario' => $ritOrigen?->respuestas_cuestionario,
            'fuente'                  => 'mejora_ia',
            'version'                 => $siguienteVersion,
            'auditoria_origen_id'     => $auditoria->id,
            'reglamento_origen_id'    => $ritOrigen?->id,
        ]);

        // ── 4. PDF permanente ─────────────────────────────────────────────────
        $rutaPdf = $this->generarPDFPermanente($textoMejorado, $empresa, $ritMejorado->id, $siguienteVersion);
        if ($rutaPdf) {
            $ritMejorado->update(['ruta_pdf' => $rutaPdf]);
        }

        Log::info('RITMejoradoService: RIT mejorado generado', [
            'auditoria_id'    => $auditoria->id,
            'empresa_id'      => $empresa->id,
            'rit_mejorado_id' => $ritMejorado->id,
            'version'         => $siguienteVersion,
            'modelo'          => $this->modeloUsado,
        ]);

        // ── 5. Actualizar auditoría ───────────────────────────────────────────
        $auditoria->update([
            'estado_mejora'          => 'completado',
            'reglamento_mejorado_id' => $ritMejorado->id,
        ]);

        return $ritMejorado;
    }

    // ── Mejora capítulo por capítulo ──────────────────────────────────────────

    /**
     * Parsea el RIT original en capítulos, mejora cada uno con Gemini
     * inyectando artículos del scraper + RAG + hallazgos de la auditoría.
     */
    public function mejorarCapitulosRIT(AuditoriaRIT $auditoria, ?\Closure $onProgress = null): string
    {
        $textoOriginal      = $this->obtenerTextoOriginal($auditoria);
        $capitulosOriginales = $this->parsearCapitulos($textoOriginal);
        $secciones          = $auditoria->secciones ?? [];
        $empresa            = $auditoria->empresa;

        $capitulos    = RITGeneratorService::getCapitulos();
        $total        = count($capitulos);
        $articuloInicio = 1;
        $bloques      = [];

        foreach ($capitulos as $idx => $cap) {
            $capOriginal      = $capitulosOriginales[$cap['numero']] ?? '';
            $hallazgos        = $this->hallazgosParaCapitulo($cap, $secciones);
            $rag              = $this->biblioteca->buscarFragmentos($cap['query_rag'], limite: 4, umbral: 0.30) ?? '';
            $articulosLegales = $this->ritGenerator->obtenerArticulosObligatorios($cap['codigos_obligatorios']);

            $prompt = $this->construirPromptMejoraCapitulo(
                $cap,
                $capOriginal,
                $hallazgos,
                $articulosLegales,
                $rag,
                $articuloInicio,
                $empresa->nombre_completo,
            );

            $textoCap = $this->llamarGemini($prompt, $empresa->id);
            $bloques[]      = $textoCap;
            $articuloInicio += max(1, preg_match_all('/^ARTÍCULO\s+\d+/m', $textoCap));

            if ($onProgress) {
                $onProgress($idx + 1, $total, $cap['titulo']);
            }
        }

        return implode("\n\n", $bloques);
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    /**
     * Extrae el texto del RIT a partir de la auditoría.
     * Prioriza texto_auditado; fallback al reglamento vinculado.
     */
    private function obtenerTextoOriginal(AuditoriaRIT $auditoria): string
    {
        $texto = trim($auditoria->texto_auditado ?? '');
        if (empty($texto)) {
            $texto = trim($auditoria->reglamento?->texto_completo ?? '');
        }
        if (empty($texto)) {
            Log::error('RITMejoradoService: texto vacío', [
                'auditoria_id' => $auditoria->id,
            ]);
            throw new \RuntimeException('No hay texto del RIT disponible para generar la versión mejorada.');
        }
        return $texto;
    }

    /**
     * Divide el texto del RIT en bloques por capítulo.
     * Retorna array ['I' => 'CAPÍTULO I\n...', 'II' => ..., ...]
     */
    private function parsearCapitulos(string $texto): array
    {
        // Dividir en el patrón "CAPÍTULO [ROMAN]" al inicio de línea
        $partes = preg_split('/(?=^CAPÍTULO\s+[IVXLCDM]+\b)/m', $texto, -1, PREG_SPLIT_NO_EMPTY);

        $capitulos = [];
        foreach ($partes as $bloque) {
            if (preg_match('/^CAPÍTULO\s+([IVXLCDM]+)\b/m', $bloque, $m)) {
                $capitulos[$m[1]] = trim($bloque);
            }
        }

        return $capitulos;
    }

    /**
     * Extrae y formatea los hallazgos de auditoría relevantes para un capítulo.
     */
    private function hallazgosParaCapitulo(array $cap, array $secciones): string
    {
        $claves = self::CAPITULO_A_SECCION[$cap['numero']] ?? [];
        $lineas = [];

        foreach ($claves as $clave) {
            $seccion = $secciones[$clave] ?? null;
            if (!$seccion) {
                continue;
            }

            $score     = $seccion['score'] ?? 100;
            $titulo    = $seccion['titulo'] ?? $clave;
            $hallazgos = $seccion['hallazgos'] ?? [];
            $recs      = $seccion['recomendaciones'] ?? [];

            if ($score >= 100 && empty($hallazgos) && empty($recs)) {
                continue;
            }

            $lineas[] = "Sección auditada: {$titulo} (puntuación: {$score}/100)";
            foreach ($hallazgos as $h) {
                $lineas[] = "  - HALLAZGO: {$h}";
            }
            foreach ($recs as $r) {
                $lineas[] = "  - CORRECCIÓN REQUERIDA: {$r}";
            }
        }

        return empty($lineas)
            ? 'Este capítulo no presentó hallazgos en la auditoría. Mantén la estructura, mejora la redacción si está incompleta.'
            : implode("\n", $lineas);
    }

    /**
     * Construye el prompt de mejora para un capítulo concreto.
     * REGLA: ningún artículo, ley ni porcentaje se hardcodea aquí;
     *        todo contenido legal viene de $articulosLegales o $rag.
     */
    private function construirPromptMejoraCapitulo(
        array  $cap,
        string $capituloOriginal,
        string $hallazgos,
        string $articulosLegales,
        string $rag,
        int    $articuloInicio,
        string $razonSocial,
    ): string {
        $numero      = $cap['numero'];
        $titulo      = $cap['titulo'];
        $instrucciones = $cap['instrucciones'];

        $seccionArticulos = $articulosLegales
            ? "\nTEXTO OFICIAL DE ARTÍCULOS DEL CST (fuente: base de datos interna — ÚNICA fuente válida para citas):\n"
              . $articulosLegales . "\n"
            : '';

        $seccionRag = $rag
            ? "\nFRAGMENTOS DE LA BIBLIOTECA JURÍDICA (fuente autorizada para citas adicionales):\n"
              . $rag . "\n"
            : '';

        $seccionOriginal = $capituloOriginal
            ? "\nCAPÍTULO ORIGINAL A MEJORAR:\n" . $capituloOriginal . "\n"
            : "\nNo se encontró el capítulo original. Redáctalo desde cero siguiendo las instrucciones de contenido.\n";

        return <<<PROMPT
Eres un abogado laboral colombiano experto en Reglamentos Internos de Trabajo.

TAREA: Reescribir y mejorar el CAPÍTULO {$numero} ({$titulo}) del RIT de "{$razonSocial}".

REGLA FUNDAMENTAL — CITAS LEGALES:
- Números de artículo, nombres de ley, porcentajes y plazos legales: SOLO los que aparezcan
  textualmente en el contexto jurídico inyectado más abajo.
- PROHIBIDO inventar o recordar artículos, leyes, porcentajes o plazos de tu entrenamiento.
- Si el contexto jurídico no trae una cifra o referencia, redacta el concepto sin citar fuente.
{$seccionArticulos}{$seccionRag}
HALLAZGOS DE LA AUDITORÍA PARA ESTE CAPÍTULO (CORRÍGELOS TODOS):
{$hallazgos}
{$seccionOriginal}
INSTRUCCIONES DE CONTENIDO PARA ESTE CAPÍTULO:
{$instrucciones}

INSTRUCCIONES DE FORMATO:
- Los artículos de este capítulo se numeran desde ARTÍCULO {$articuloInicio}.
- Primera línea del capítulo: CAPÍTULO {$numero}
- Segunda línea: {$titulo}
- Cada artículo: párrafo completo de mínimo 60 palabras en su propia línea.
- NUNCA uses guiones (-), asteriscos (*) ni almohadillas (#) al inicio de línea.
- Para listas internas usa: "1) texto 2) texto" en líneas separadas.
- TABLAS cuando aplique: TABLA: / ENCABEZADO: col1 | col2 / FILA: v1 | v2 / FIN_TABLA

Devuelve ÚNICAMENTE el texto del capítulo mejorado, sin comentarios ni explicaciones.
PROMPT;
    }

    /**
     * Llama a Gemini con cascade flash → flash-lite.
     * Misma lógica que RITGeneratorService::llamarGemini().
     */
    private function llamarGemini(string $prompt, int $empresaId = 0): string
    {
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? '';

        // Limpiar bytes UTF-8 inválidos
        $prompt = preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $prompt
        ) ?? iconv('UTF-8', 'UTF-8//IGNORE', $prompt);

        $modelos = [
            'gemini-2.5-flash'      => ['budget' => 0,    'timeout' => 120],
            'gemini-2.5-flash-lite' => ['budget' => 0,    'timeout' => 90],
        ];
        $lastError = '';

        foreach ($modelos as $model => $cfg) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            Log::info('RITMejoradoService: llamando Gemini por capítulo', [
                'model'      => $model,
                'empresa_id' => $empresaId,
            ]);

            $payload = [
                'contents'         => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature'     => 0.25,
                    'maxOutputTokens' => 8192,
                    'topP'            => 0.95,
                    'thinkingConfig'  => ['thinkingBudget' => $cfg['budget']],
                ],
            ];

            for ($intento = 1; $intento <= 2; $intento++) {
                try {
                    $response = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->timeout($cfg['timeout'])
                        ->post($url, $payload);
                } catch (\Illuminate\Http\Client\ConnectionException $ce) {
                    Log::warning('RITMejoradoService: timeout, cascade al siguiente modelo', [
                        'model' => $model, 'error' => $ce->getMessage(),
                    ]);
                    break;
                }

                if ($response->successful()) {
                    $parts = $response->json('candidates.0.content.parts', []);
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
                    if (!empty($texto)) {
                        $this->modeloUsado = $model;
                        return trim($texto);
                    }
                    break;
                }

                $status    = $response->status();
                $lastError = $response->body();

                if ($status === 400 && str_contains($lastError, 'thinking')) {
                    break; // cascade al siguiente modelo
                }
                if (in_array($status, [429, 503]) && $intento < 2) {
                    sleep(15);
                } elseif (in_array($status, [500, 502, 504]) && $intento < 2) {
                    sleep(10);
                } else {
                    break;
                }
            }
        }

        throw new \RuntimeException('Error en API Gemini (todos los modelos intentados): ' . $lastError);
    }

    /**
     * Genera el PDF permanente y lo guarda en storage/app/private/.
     */
    private function generarPDFPermanente(
        string $textoMejorado,
        \App\Models\Empresa $empresa,
        int $ritId,
        int $version
    ): ?string {
        try {
            $tmpPath = $this->ritGenerator->generarPDFTemp($textoMejorado, $empresa);

            $directorio   = "private/rits/{$empresa->id}";
            $rutaRelativa = "{$directorio}/rit_v{$version}_{$ritId}.pdf";

            Storage::makeDirectory($directorio);
            Storage::put($rutaRelativa, file_get_contents($tmpPath));
            @unlink($tmpPath);

            return $rutaRelativa;

        } catch (\Throwable $e) {
            Log::warning('RITMejoradoService: no se pudo generar PDF permanente', [
                'empresa_id' => $empresa->id,
                'rit_id'     => $ritId,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }
}
