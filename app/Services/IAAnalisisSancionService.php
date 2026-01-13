<?php

namespace App\Services;

use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IAAnalisisSancionService
{
    /**
     * Analizar el proceso disciplinario y sugerir sanciones apropiadas
     */
    public function analizarYSugerirSanciones(ProcesoDisciplinario $proceso): array
    {
        try {
            // Obtener información del contexto
            $trabajador = $proceso->trabajador;
            $empresa = $proceso->empresa;

            // Obtener historial de procesos disciplinarios del trabajador
            $historialProcesos = $this->obtenerHistorialProcesos($trabajador, $proceso->id);

            // Obtener los descargos si existen
            $contextoDescargos = $this->obtenerContextoDescargos($proceso);

            // Construir el prompt para la IA
            $prompt = $this->construirPromptAnalisisSancion(
                $proceso,
                $trabajador,
                $empresa,
                $historialProcesos,
                $contextoDescargos
            );

            // Llamar a la API de IA
            $provider = config('services.ia.provider', 'openai');
            $config = config("services.ia.{$provider}", []);
            $apiKey = $config['api_key'];
            $model = $config['model'];
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            Log::info('Analizando proceso disciplinario para sugerir sanciones', [
                'proceso_id' => $proceso->id,
                'trabajador_id' => $trabajador->id,
                'cantidad_procesos_previos' => count($historialProcesos),
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.3, // Menor temperatura para respuestas más consistentes
                    'maxOutputTokens' => 2048,
                    'topP' => 0.95,
                    'topK' => 40,
                ],
            ]);

            if (!$response->successful()) {
                throw new \Exception("Error en API de IA: " . $response->body());
            }

            $responseData = $response->json();

            if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                throw new \Exception("Respuesta de IA sin contenido válido");
            }

            $analisisTexto = $responseData['candidates'][0]['content']['parts'][0]['text'];

            // Parsear la respuesta de la IA
            $analisis = $this->parsearAnalisisIA($analisisTexto);

            Log::info('Análisis de sanciones completado', [
                'proceso_id' => $proceso->id,
                'sanciones_sugeridas' => $analisis['sanciones_disponibles'] ?? [],
                'gravedad' => $analisis['gravedad'] ?? 'desconocida',
            ]);

            return [
                'success' => true,
                'analisis' => $analisis,
            ];

        } catch (\Exception $e) {
            Log::error('Error al analizar proceso para sugerir sanciones', [
                'proceso_id' => $proceso->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Retornar opciones por defecto en caso de error
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'analisis' => $this->obtenerOpcionesPorDefecto(),
            ];
        }
    }

    /**
     * Obtener historial de procesos disciplinarios del trabajador
     */
    private function obtenerHistorialProcesos(Trabajador $trabajador, int $procesoActualId): array
    {
        $procesos = ProcesoDisciplinario::where('trabajador_id', $trabajador->id)
            ->where('id', '!=', $procesoActualId)
            ->where('estado', '!=', 'archivado')
            ->orderBy('created_at', 'desc')
            ->get();

        return $procesos->map(function ($proceso) {
            return [
                'fecha' => $proceso->created_at->format('Y-m-d'),
                'hechos' => strip_tags($proceso->hechos),
                // COMENTADO: Artículos legales - Ahora se usan Sanciones Laborales
                // 'articulos' => $proceso->articulos_legales_texto ?? 'No especificado',
                'sanciones' => $proceso->sanciones_laborales_texto ?? 'No especificado',
                'sancion' => $proceso->tipo_sancion ?? 'Sin sanción emitida',
                'estado' => $proceso->estado,
            ];
        })->toArray();
    }

    /**
     * Obtener contexto de descargos si existen
     */
    private function obtenerContextoDescargos(ProcesoDisciplinario $proceso): string
    {
        $diligencia = $proceso->diligenciaDescargo;

        if (!$diligencia) {
            return 'No se han realizado descargos aún.';
        }

        $preguntas = $diligencia->preguntas()
            ->with('respuesta')
            ->ordenadas()
            ->get();

        if ($preguntas->isEmpty()) {
            return 'No hay descargos registrados.';
        }

        $contexto = '';
        foreach ($preguntas as $index => $pregunta) {
            $respuesta = $pregunta->respuesta?->respuesta ?? 'Sin respuesta';
            $contexto .= ($index + 1) . ". {$pregunta->pregunta}\n   Respuesta: {$respuesta}\n\n";
        }

        return $contexto;
    }

    /**
     * Construir prompt para análisis de sanciones
     */
    private function construirPromptAnalisisSancion(
        ProcesoDisciplinario $proceso,
        Trabajador $trabajador,
        $empresa,
        array $historialProcesos,
        string $contextoDescargos
    ): string {
        $hechosTexto = strip_tags($proceso->hechos);
        // COMENTADO: Artículos legales - Ahora se usan Sanciones Laborales
        // $articulosLegales = $proceso->articulos_legales_texto ?? 'No especificado';
        $sancionesLaborales = $proceso->sanciones_laborales_texto ?? 'No especificado';
        $cantidadProcesosPrevios = count($historialProcesos);

        $historialTexto = '';
        if ($cantidadProcesosPrevios > 0) {
            $historialTexto = "El trabajador tiene {$cantidadProcesosPrevios} proceso(s) disciplinario(s) previo(s):\n\n";
            foreach ($historialProcesos as $index => $proc) {
                $historialTexto .= ($index + 1) . ". Fecha: {$proc['fecha']}\n";
                $historialTexto .= "   Hechos: {$proc['hechos']}\n";
                // COMENTADO: Artículos legales - Ahora se usan Sanciones Laborales
                // $historialTexto .= "   Artículos: {$proc['articulos']}\n";
                $historialTexto .= "   Sanciones del reglamento incumplidas: {$proc['sanciones']}\n";
                $historialTexto .= "   Sanción aplicada: {$proc['sancion']}\n";
                $historialTexto .= "   Estado: {$proc['estado']}\n\n";
            }
        } else {
            $historialTexto = "Este es el PRIMER proceso disciplinario del trabajador (sin antecedentes previos).\n\n";
        }

        return <<<PROMPT
Eres un experto en derecho laboral colombiano. Analiza el siguiente proceso disciplinario y determina qué tipos de sanciones son APROPIADAS según la gravedad de la falta, el Código Sustantivo del Trabajo, el reglamento interno de trabajo de la empresa y el historial del trabajador.

INFORMACIÓN DEL PROCESO:
- Empresa: {$empresa->razon_social}
- Trabajador: {$trabajador->nombre_completo}
- Cargo: {$trabajador->cargo}

HECHOS DEL CASO ACTUAL:
{$hechosTexto}

SANCIONES LABORALES DEL REGLAMENTO INCUMPLIDAS:
{$sancionesLaborales}

DESCARGOS DEL TRABAJADOR:
{$contextoDescargos}

HISTORIAL DEL TRABAJADOR:
{$historialTexto}

INSTRUCCIONES:
Analiza la gravedad de la falta según estos criterios del Código Sustantivo del Trabajo colombiano:

Solo existen DOS categorías de gravedad:

1. FALTAS LEVES:
   - Llegadas tarde ocasionales (primera o segunda vez)
   - Incumplimientos menores sin impacto grave
   - Primera vez cometiendo una falta (sin antecedentes previos)
   - Descuidos leves que no causan daño significativo

   → SANCIÓN: Solo llamado de atención (escrito o verbal)

2. FALTAS GRAVES:
   Incluye desde faltas moderadas hasta faltas que constituyen justa causa según el Art. 62 CST.

   GRAVES - NIVEL BAJO (1-8 días de suspensión):
   - Reincidencia en faltas leves (2 o más procesos previos por lo mismo)
   - Insubordinación leve o falta de respeto
   - Incumplimiento de normas de seguridad sin consecuencias graves
   - Negligencia que cause daño leve o moderado
   - Ausencias injustificadas (pocas)

   GRAVES - NIVEL ALTO (8-60 días de suspensión o terminación):
   - Hurto o fraude
   - Agresión física
   - Acoso laboral o sexual
   - Violación grave de seguridad que ponga en riesgo vidas
   - Reincidencia múltiple (3 o más procesos previos)
   - Falsificación de documentos
   - Cualquier conducta que constituya justa causa de terminación según Art. 62 CST

   → SANCIÓN según el nivel:
      • Nivel bajo: Llamado de atención O suspensión (1-8 días)
      • Nivel alto: Suspensión (8-60 días) O terminación de contrato

IMPORTANTE SOBRE SUSPENSIONES:
- Suspensión de 1-3 días: Faltas graves nivel bajo, sin reincidencia reciente
- Suspensión de 3-8 días: Faltas graves nivel bajo con reincidencia o impacto moderado
- Suspensión de 8-15 días: Faltas graves nivel alto, conductas serias
- Suspensión de 15-30 días: Faltas graves nivel alto, conductas muy serias
- Suspensión de 30-60 días: Faltas graves nivel alto, máxima gravedad (alternativa a terminación)

Responde EXACTAMENTE en este formato JSON (sin código markdown):
{
  "gravedad": "leve|grave",
  "nivel_gravedad": "ninguno|bajo|alto",
  "es_reincidencia": true/false,
  "justificacion": "Explicación clara de por qué se clasifica así y en qué nivel",
  "sanciones_disponibles": ["llamado_atencion", "suspension", "terminacion"],
  "sancion_recomendada": "llamado_atencion|suspension|terminacion",
  "dias_suspension_sugeridos": [1, 2, 3, 5, 8, 15, 30, 60],
  "razonamiento_legal": "Explicación basada en el CST y las sanciones del reglamento incumplidas",
  "consideraciones_especiales": "Información adicional relevante (historial, descargos, atenuantes, agravantes)"
}

REGLAS ESTRICTAS:
- Si es FALTA LEVE: gravedad="leve", nivel_gravedad="ninguno", sanciones=["llamado_atencion"]
- Si es FALTA GRAVE NIVEL BAJO: gravedad="grave", nivel_gravedad="bajo", sanciones=["llamado_atencion", "suspension"], dias=[1,2,3,5,8]
- Si es FALTA GRAVE NIVEL ALTO: gravedad="grave", nivel_gravedad="alto", sanciones=["suspension", "terminacion"], dias=[8,15,30,60]
- dias_suspension_sugeridos debe variar según el nivel de gravedad
- Siempre basar el análisis en la legislación laboral colombiana

Genera SOLO el JSON, sin texto adicional.
PROMPT;
    }

    /**
     * Parsear la respuesta de la IA en formato JSON
     */
    private function parsearAnalisisIA(string $analisisTexto): array
    {
        // Limpiar el texto para obtener solo el JSON
        $analisisTexto = trim($analisisTexto);
        $analisisTexto = preg_replace('/```json\s*/', '', $analisisTexto);
        $analisisTexto = preg_replace('/```\s*$/', '', $analisisTexto);
        $analisisTexto = preg_replace('/```/', '', $analisisTexto);

        try {
            $analisis = json_decode($analisisTexto, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Error al parsear JSON: ' . json_last_error_msg());
            }

            // Validar estructura
            if (!isset($analisis['gravedad']) || !isset($analisis['sanciones_disponibles'])) {
                throw new \Exception('Respuesta de IA con estructura inválida');
            }

            return $analisis;

        } catch (\Exception $e) {
            Log::warning('Error al parsear análisis de IA, usando valores por defecto', [
                'error' => $e->getMessage(),
                'respuesta_ia' => $analisisTexto,
            ]);

            return $this->obtenerOpcionesPorDefecto();
        }
    }

    /**
     * Obtener opciones por defecto en caso de error
     */
    private function obtenerOpcionesPorDefecto(): array
    {
        return [
            'gravedad' => 'grave',
            'nivel_gravedad' => 'bajo',
            'es_reincidencia' => false,
            'justificacion' => 'Análisis manual requerido - el sistema no pudo determinar automáticamente la gravedad.',
            'sanciones_disponibles' => ['llamado_atencion', 'suspension', 'terminacion'],
            'sancion_recomendada' => 'llamado_atencion',
            'dias_suspension_sugeridos' => [1, 2, 3, 5, 8],
            'razonamiento_legal' => 'Se requiere revisión manual del caso para determinar la sanción apropiada.',
            'consideraciones_especiales' => 'El análisis automático no estuvo disponible. Se recomienda revisar manualmente los hechos, artículos incumplidos y el historial del trabajador.',
        ];
    }
}