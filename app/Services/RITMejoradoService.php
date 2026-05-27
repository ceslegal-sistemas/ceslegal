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
 * 1. Extrae texto del RIT auditado (campo texto_auditado de la auditoría).
 * 2. Recopila todos los hallazgos y recomendaciones por sección.
 * 3. Consulta la biblioteca legal (RAG) para las secciones deficientes.
 * 4. Llama a Gemini (cascade 2.5-pro → 2.5-flash) con el RIT original + correcciones + normativa.
 * 5. Crea un nuevo registro ReglamentoInterno con version+1.
 * 6. Genera PDF permanente con DomPDF y lo guarda en storage/app/private/.
 * 7. Actualiza la auditoría con reglamento_mejorado_id y estado_mejora = 'completado'.
 */
class RITMejoradoService
{
    // Secciones de auditoría con sus queries RAG (espejo de AuditoriaRITService::SECCIONES)
    private const QUERIES_RAG = [
        'admision'         => 'admisión trabajadores período de prueba requisitos contrato Art. 76 80 CST',
        'jornada'          => 'jornada laboral horas extras trabajo nocturno dominicales festivos Art. 158 168 179 CST',
        'descansos'        => 'descanso remunerado vacaciones compensación permisos Art. 186 192 CST',
        'salario'          => 'salario remuneración forma periodicidad pago deducciones Art. 127 149 CST',
        'disciplina'       => 'régimen disciplinario faltas leves graves sanciones descargos procedimiento Art. 105 113 CST',
        'sst'              => 'seguridad salud trabajo SG-SST riesgos profesionales accidentes enfermedades laborales obligaciones empleador',
        'acoso'            => 'acoso laboral sexual prevención Ley 1010 2006 Ley 2365 2024 comité convivencia',
        'grupos_protegidos' => 'mujer embarazada maternidad paternidad discapacidad fuero circunstancial trabajadores protegidos',
    ];

    public function __construct(
        private BibliotecaLegalService $biblioteca,
        private RITGeneratorService    $ritGenerator,
    ) {}

    /**
     * Punto de entrada principal.
     * Genera el RIT mejorado, persiste los archivos y actualiza la auditoría.
     *
     * @throws \RuntimeException si el texto del RIT está vacío o la IA falla.
     */
    public function generar(AuditoriaRIT $auditoria): ReglamentoInterno
    {
        $empresa  = $auditoria->empresa;
        $secciones = $auditoria->secciones ?? [];

        // ── 1. Texto base del RIT ──────────────────────────────────────────────
        $textoOriginal = $auditoria->texto_auditado ?? '';
        if (empty(trim($textoOriginal))) {
            // Fallback: intentar desde el reglamento vinculado
            $textoOriginal = $auditoria->reglamento?->texto_completo ?? '';
        }
        if (empty(trim($textoOriginal))) {
            Log::error('RITMejoradoService: texto vacío, no se puede generar RIT mejorado', [
                'auditoria_id'         => $auditoria->id,
                'fuente'               => $auditoria->fuente,
                'tiene_texto_auditado' => !empty($auditoria->texto_auditado),
                'tiene_reglamento'     => $auditoria->reglamento_interno_id !== null,
            ]);
            throw new \RuntimeException('No hay texto del RIT disponible para generar la versión mejorada.');
        }

        // ── 2. Resumen de hallazgos por sección ───────────────────────────────
        $hallazgosPorSeccion = $this->resumirHallazgos($secciones);

        // ── 3. RAG: normativa para las secciones con problemas ────────────────
        $contextoBiblioteca = $this->obtenerNormativaParaMejoras($secciones);

        // ── 4. Llamar a Gemini ────────────────────────────────────────────────
        $textoMejorado = $this->llamarGemini($textoOriginal, $hallazgosPorSeccion, $contextoBiblioteca, $empresa->nombre_completo);

        // ── 5. Determinar versión ─────────────────────────────────────────────
        $ritOrigen = $auditoria->reglamento;
        $versionBase = $ritOrigen?->version ?? 1;
        $siguienteVersion = $versionBase + 1;

        // ── 6. Crear nuevo registro ReglamentoInterno ─────────────────────────
        $nombreMejorado = ($ritOrigen?->nombre ?? 'Reglamento Interno de Trabajo')
            . " (v{$siguienteVersion})";

        $ritMejorado = ReglamentoInterno::create([
            'empresa_id'          => $empresa->id,
            'nombre'              => $nombreMejorado,
            'texto_completo'      => $textoMejorado,
            'ruta_docx'           => null,
            'activo'              => false,  // el administrador decide si activarlo
            'respuestas_cuestionario' => $ritOrigen?->respuestas_cuestionario,
            'fuente'              => 'mejora_ia',
            'version'             => $siguienteVersion,
            'auditoria_origen_id' => $auditoria->id,
            'reglamento_origen_id'=> $ritOrigen?->id,
        ]);

        // ── 7. Generar PDF permanente ─────────────────────────────────────────
        $rutaPdf = $this->generarPDFPermanente($textoMejorado, $empresa, $ritMejorado->id, $siguienteVersion);
        if ($rutaPdf) {
            $ritMejorado->update(['ruta_pdf' => $rutaPdf]);
        }

        Log::info('RITMejoradoService: RIT mejorado generado', [
            'auditoria_id'    => $auditoria->id,
            'empresa_id'      => $empresa->id,
            'rit_mejorado_id' => $ritMejorado->id,
            'version'         => $siguienteVersion,
            'ruta_pdf'        => $rutaPdf,
        ]);

        // ── 8. Actualizar auditoría ───────────────────────────────────────────
        $auditoria->update([
            'estado_mejora'        => 'completado',
            'reglamento_mejorado_id' => $ritMejorado->id,
        ]);

        return $ritMejorado;
    }

    // ── Privados ──────────────────────────────────────────────────────────────

    /**
     * Construye un resumen de texto con los hallazgos y recomendaciones de cada sección.
     */
    private function resumirHallazgos(array $secciones): string
    {
        if (empty($secciones)) {
            return 'No hay hallazgos específicos disponibles. Mejora el documento en general.';
        }

        $lineas = [];
        foreach ($secciones as $clave => $seccion) {
            $score       = $seccion['score'] ?? 100;
            $titulo      = $seccion['titulo'] ?? $clave;
            $hallazgos   = $seccion['hallazgos'] ?? [];
            $recs        = $seccion['recomendaciones'] ?? [];

            if ($score >= 100 || (empty($hallazgos) && empty($recs))) {
                continue;
            }

            $lineas[] = "### {$titulo} (score: {$score}/100)";
            if (!empty($hallazgos)) {
                $lineas[] = 'Hallazgos:';
                foreach ($hallazgos as $h) {
                    $lineas[] = "  - {$h}";
                }
            }
            if (!empty($recs)) {
                $lineas[] = 'Recomendaciones:';
                foreach ($recs as $r) {
                    $lineas[] = "  - {$r}";
                }
            }
            $lineas[] = '';
        }

        return empty($lineas)
            ? 'Todas las secciones obtuvieron score perfecto. Mejora redacción y completitud general.'
            : implode("\n", $lineas);
    }

    /**
     * Consulta la biblioteca legal para las secciones con score < 100.
     * Devuelve el bloque de texto de fragmentos relevantes para inyectar en el prompt.
     */
    private function obtenerNormativaParaMejoras(array $secciones): string
    {
        $fragmentosPorTema = [];
        $yaVisto = [];

        foreach ($secciones as $clave => $seccion) {
            $score = $seccion['score'] ?? 100;
            if ($score >= 100) {
                continue;
            }

            $query = self::QUERIES_RAG[$clave] ?? null;
            if (!$query) {
                continue;
            }

            $resultado = $this->biblioteca->buscarFragmentos($query, limite: 4, umbral: 0.30);
            if ($resultado && !in_array(md5($resultado), $yaVisto)) {
                $fragmentosPorTema[] = $resultado;
                $yaVisto[] = md5($resultado);
            }
        }

        if (empty($fragmentosPorTema)) {
            // Fallback: consulta genérica si la biblioteca no tiene documentos
            $general = $this->biblioteca->buscarFragmentos(
                'reglamento interno de trabajo Colombia CST obligaciones empleador trabajador',
                limite: 5,
                umbral: 0.25
            );
            if ($general) {
                $fragmentosPorTema[] = $general;
            }
        }

        return implode("\n\n---\n\n", array_filter($fragmentosPorTema));
    }

    /**
     * Llama a Gemini con cascade flash → flash-lite para generar el RIT mejorado.
     */
    private function llamarGemini(
        string $textoOriginal,
        string $hallazgos,
        string $contextoBiblioteca,
        string $razonSocial
    ): string {
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? '';

        $prompt = $this->construirPrompt($textoOriginal, $hallazgos, $contextoBiblioteca, $razonSocial);

        // Limpiar bytes UTF-8 inválidos
        $prompt = preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $prompt
        ) ?? iconv('UTF-8', 'UTF-8//IGNORE', $prompt);

        // Cascade con configuración específica por modelo:
        // - gemini-2.5-pro requiere thinking mode (budget >= 1); usamos 2048 como mínimo razonable
        // - gemini-2.5-flash soporta thinkingBudget:0 → respuesta inmediata, más veloz
        $modelosConfig = [
            'gemini-2.5-pro'   => ['budget' => 2048, 'timeout' => 250],
            'gemini-2.5-flash' => ['budget' => 0,    'timeout' => 200],
        ];
        $modelosCascada = array_keys($modelosConfig);
        $lastError      = '';

        $genConfigBase = [
            'temperature'     => 0.25,
            'maxOutputTokens' => 16384,
            'topP'            => 0.95,
        ];

        foreach (array_values($modelosCascada) as $idx => $model) {
            $url      = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $esUltimo = ($idx === count($modelosCascada) - 1);
            $cfg      = $modelosConfig[$model];

            Log::info('RITMejoradoService: generando texto con Gemini', [
                'model'          => $model,
                'intento_modelo' => $idx + 1,
            ]);

            $genConfig = array_merge($genConfigBase, [
                'thinkingConfig' => ['thinkingBudget' => $cfg['budget']],
            ]);

            $payload = [
                'contents'         => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => $genConfig,
            ];

            $sobrecarga = false;

            for ($intento = 1; $intento <= 2; $intento++) {
                try {
                    $response = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->timeout($cfg['timeout'])
                        ->post($url, $payload);
                } catch (\Illuminate\Http\Client\ConnectionException $ce) {
                    Log::warning('RITMejoradoService: timeout de conexión, cascade al siguiente modelo', [
                        'model'   => $model,
                        'intento' => $intento,
                        'error'   => $ce->getMessage(),
                    ]);
                    $sobrecarga = true;
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
                    if (empty($texto)) {
                        throw new \RuntimeException('Gemini devolvió respuesta sin contenido válido.');
                    }

                    return trim($texto);
                }

                $status    = $response->status();
                $lastError = $response->body();

                Log::warning('RITMejoradoService: fallo en intento', [
                    'model'   => $model,
                    'intento' => $intento,
                    'status'  => $status,
                ]);

                // Error de thinkingBudget incompatible → cascade al siguiente modelo
                if ($status === 400 && str_contains($lastError, 'thinking')) {
                    Log::warning("RITMejoradoService: {$model} rechazó thinkingBudget, cascadeando");
                    $sobrecarga = true;
                    break;
                }

                if (in_array($status, [429, 503])) {
                    if ($intento < 2) {
                        sleep(15);
                    } else {
                        $sobrecarga = true;
                        break;
                    }
                } elseif (in_array($status, [500, 502, 504]) && $intento < 2) {
                    sleep(10);
                } else {
                    throw new \RuntimeException('Error en API Gemini: ' . $lastError);
                }
            }

            if ($sobrecarga && !$esUltimo) {
                Log::warning('RITMejoradoService: modelo saturado/incompatible, cambiando al siguiente', [
                    'model_fallido' => $model,
                    'model_next'    => $modelosCascada[$idx + 1] ?? 'ninguno',
                ]);
                continue;
            }

            break;
        }

        throw new \RuntimeException('Error en API Gemini (todos los modelos intentados): ' . $lastError);
    }

    /**
     * Construye el prompt de mejora para Gemini.
     */
    private function construirPrompt(
        string $textoOriginal,
        string $hallazgos,
        string $contextoBiblioteca,
        string $razonSocial
    ): string {
        $seccionBiblioteca = $contextoBiblioteca
            ? "FRAGMENTOS DE LA BIBLIOTECA JURÍDICA (usa SOLO estos para correcciones normativas):\n"
              . "REGLA: Cita artículos y leyes ÚNICAMENTE si aparecen en estos fragmentos.\n\n"
              . $contextoBiblioteca . "\n"
            : "ADVERTENCIA: La biblioteca legal no devolvió fragmentos. Usa el conocimiento del prompt para mejorar el RIT.\n";

        $textoOriginalAEnviar = $textoOriginal;

        return <<<PROMPT
Eres un abogado laboral colombiano experto en Reglamentos Internos de Trabajo.

Tu tarea es REESCRIBIR el RIT de "{$razonSocial}" corrigiendo todos los problemas identificados en la auditoría.

═══════════════════════════════════════════════════════════════
PROBLEMAS IDENTIFICADOS EN LA AUDITORÍA (CORRÍGELOS TODOS):
═══════════════════════════════════════════════════════════════
{$hallazgos}

═══════════════════════════════════════════════════════════════
{$seccionBiblioteca}
═══════════════════════════════════════════════════════════════
RIT ORIGINAL (base para la reescritura):
═══════════════════════════════════════════════════════════════
{$textoOriginalAEnviar}

═══════════════════════════════════════════════════════════════
INSTRUCCIONES PARA LA REESCRITURA:
═══════════════════════════════════════════════════════════════
1. Reproduce TODOS los capítulos y artículos del original.
2. Corrige CADA UNO de los hallazgos listados arriba.
3. Mantén los datos específicos de la empresa: nombre, NIT, cargos, horarios, sanciones propias, etc.
4. Usa los fragmentos de la biblioteca jurídica para las correcciones normativas.
5. NO uses corchetes ni placeholders. Los datos de la empresa están en el original.
6. Conserva el mismo formato: CAPÍTULO en mayúsculas, ARTÍCULO numerado consecutivamente.
7. Cada artículo debe ser un párrafo completo de mínimo 60 palabras.
8. NUNCA uses guiones (-), asteriscos (*), almohadillas (#) al inicio de línea.
9. Para listas dentro de artículos usa "1) ... 2) ..." integrado en el párrafo.
10. Si el original omite secciones obligatorias del CST, agrégalas completas.

Devuelve ÚNICAMENTE el texto del RIT reescrito, sin comentarios ni explicaciones previas o posteriores.
PROMPT;
    }

    /**
     * Genera el PDF permanente y lo guarda en storage/app/private/.
     * Retorna la ruta relativa (desde storage/app/) o null si falla.
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
