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
            ])->timeout(90)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => max((int) ($config['max_tokens'] ?? 4096), 8192),
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
     * Obtener los motivos de descargos seleccionados con detalle
     */
    private function obtenerMotivosDescargosDetalle(ProcesoDisciplinario $proceso): string
    {
        $sancionesLaborales = $proceso->sancionesLaborales;

        if ($sancionesLaborales->isEmpty()) {
            return "No se han seleccionado motivos de descargos del reglamento interno.\n";
        }

        $detalle = "";
        foreach ($sancionesLaborales as $index => $sancion) {
            $numero = $index + 1;
            $tipoFalta = strtoupper($sancion->tipo_falta);
            $emoji = $sancion->tipo_falta === 'leve' ? '🟢' : '🔴';
            $tipoSancion = $sancion->tipo_sancion_texto;

            $detalle .= "{$numero}. {$emoji} [{$tipoFalta}] {$sancion->nombre_claro}\n";
            $detalle .= "   Descripción: {$sancion->descripcion}\n";
            $detalle .= "   Sanción del reglamento: {$tipoSancion}\n";

            if ($sancion->tipo_sancion === 'suspension' && $sancion->dias_suspension_texto) {
                $detalle .= "   Días de suspensión según reglamento: {$sancion->dias_suspension_texto}\n";
            }

            // Verificar si es reincidencia
            if ($sancion->esReincidencia()) {
                $detalle .= "   ⚠️ NOTA: Este motivo es una REINCIDENCIA (no es la primera vez)\n";
            }

            $detalle .= "\n";
        }

        return $detalle;
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

        // Obtener los motivos de descargos seleccionados con detalle
        $motivosDescargosDetalle = $this->obtenerMotivosDescargosDetalle($proceso);

        // Verificar si hay "otro motivo"
        $otroMotivo = $proceso->otro_motivo_descargos;
        $tieneOtroMotivo = !empty($otroMotivo);

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

        // Construir sección de otro motivo
        $seccionOtroMotivo = '';
        if ($tieneOtroMotivo) {
            $seccionOtroMotivo = "\n\nOTRO MOTIVO ADICIONAL (REQUIERE ANÁLISIS ESPECIAL):\n";
            $seccionOtroMotivo .= "El empleador seleccionó \"Otro\" como motivo adicional y describió lo siguiente:\n";
            $seccionOtroMotivo .= "\"{$otroMotivo}\"\n\n";
            $seccionOtroMotivo .= "IMPORTANTE: Debes analizar este motivo adicional y:\n";
            $seccionOtroMotivo .= "1. Determinar si es una falta LEVE o GRAVE\n";
            $seccionOtroMotivo .= "2. Recomendar qué tipo de sanción aplicaría para este motivo específico\n";
            $seccionOtroMotivo .= "3. Si es grave, indicar el nivel (bajo o alto) y los días de suspensión recomendados\n";
            $seccionOtroMotivo .= "4. Proporcionar una justificación clara para ayudar al cliente a tomar la mejor decisión\n";
        }

        return <<<PROMPT
Eres un experto en derecho laboral colombiano. Analiza el siguiente proceso disciplinario y determina qué tipos de sanciones son APROPIADAS según la gravedad de la falta, el Código Sustantivo del Trabajo, el reglamento interno de trabajo de la empresa y el historial del trabajador.

INFORMACIÓN DEL PROCESO:
- Empresa: {$empresa->razon_social}
- Trabajador: {$trabajador->nombre_completo}
- Cargo: {$trabajador->cargo}

HECHOS DEL CASO ACTUAL:
{$hechosTexto}

═══════════════════════════════════════════════════════════════════
MOTIVOS DE LOS DESCARGOS SELECCIONADOS (del reglamento interno):
═══════════════════════════════════════════════════════════════════
{$motivosDescargosDetalle}

RESUMEN SANCIONES LABORALES INCUMPLIDAS:
{$sancionesLaborales}
{$seccionOtroMotivo}
═══════════════════════════════════════════════════════════════════

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
  "consideraciones_especiales": "Información adicional relevante (historial, descargos, atenuantes, agravantes)",
  "motivos_analizados": [
    {
      "motivo": "Nombre del motivo seleccionado",
      "tipo_falta": "leve|grave",
      "sancion_asociada": "llamado_atencion|suspension|terminacion",
      "observacion": "Breve observación sobre este motivo"
    }
  ],
  "analisis_otro_motivo": {
    "aplica": true/false,
    "descripcion_analizada": "Descripción del otro motivo",
    "tipo_falta_determinado": "leve|grave",
    "nivel_gravedad": "ninguno|bajo|alto",
    "sancion_recomendada": "llamado_atencion|suspension|terminacion",
    "dias_suspension_recomendados": null o número,
    "justificacion": "Explicación detallada de por qué se recomienda esta sanción para el otro motivo"
  },
  "recomendacion_final": {
    "sancion_sugerida": "llamado_atencion|suspension|terminacion",
    "dias_suspension": null o número,
    "confianza": "alta|media|baja",
    "mensaje_para_decision": "Mensaje claro para ayudar al cliente a tomar la mejor decisión, explicando las opciones y sus consecuencias"
  }
}

REGLAS ESTRICTAS:
- Si es FALTA LEVE: gravedad="leve", nivel_gravedad="ninguno", sanciones=["llamado_atencion", "suspension"], dias=[1,2,3,5]
- Si es FALTA GRAVE NIVEL BAJO: gravedad="grave", nivel_gravedad="bajo", sanciones=["llamado_atencion", "suspension"], dias=[1,2,3,5,8]
- Si es FALTA GRAVE NIVEL ALTO: gravedad="grave", nivel_gravedad="alto", sanciones=["suspension", "terminacion"], dias=[8,15,30,60]
- dias_suspension_sugeridos debe variar según el nivel de gravedad
- Siempre basar el análisis en la legislación laboral colombiana
- En "motivos_analizados" incluye CADA motivo seleccionado del reglamento con su análisis individual
- Si hay "otro motivo", analisis_otro_motivo.aplica=true y completa TODOS los campos
- Si NO hay "otro motivo", analisis_otro_motivo.aplica=false y los demás campos pueden ser null
- La "recomendacion_final" debe ser un resumen claro que ayude al cliente a decidir

REGLAS DE FORMATO:
- Genera SOLO el JSON, sin texto adicional ni bloques de código markdown.
- Sé CONCISO: máximo 150 palabras por campo de texto (justificacion, razonamiento_legal, consideraciones_especiales, mensaje_para_decision).
- No repitas la misma información en campos diferentes.
- No uses saltos de línea dentro de los valores string del JSON.
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

        // Escapar caracteres de control dentro de strings JSON
        // (Gemini retorna newlines literales dentro de valores string que json_decode rechaza)
        $analisisTexto = $this->escaparControlesEnStringsJson($analisisTexto);

        try {
            $analisis = json_decode($analisisTexto, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Intentar reparar JSON truncado y reintentar
                $reparado = $this->repararJsonTruncado($analisisTexto);
                $analisis = json_decode($reparado, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Error al parsear JSON: ' . json_last_error_msg());
                }

                Log::info('JSON de IA reparado exitosamente (respuesta truncada)');
            }

            // Validar estructura
            if (!isset($analisis['gravedad']) || !isset($analisis['sanciones_disponibles'])) {
                throw new \Exception('Respuesta de IA con estructura inválida');
            }

            return $analisis;

        } catch (\Exception $e) {
            Log::warning('Error al parsear análisis de IA, usando valores por defecto', [
                'error' => $e->getMessage(),
                'respuesta_ia' => mb_substr($analisisTexto, 0, 500),
            ]);

            return $this->obtenerOpcionesPorDefecto();
        }
    }

    /**
     * Escapar caracteres de control (newlines, tabs) dentro de strings JSON.
     * json_decode rechaza caracteres de control literales (0x00-0x1F) dentro de strings.
     */
    private function escaparControlesEnStringsJson(string $json): string
    {
        $resultado = '';
        $enString = false;
        $escape = false;
        $len = strlen($json);

        for ($i = 0; $i < $len; $i++) {
            $char = $json[$i];
            $ord = ord($char);

            if ($escape) {
                $resultado .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\' && $enString) {
                $resultado .= $char;
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $enString = !$enString;
                $resultado .= $char;
                continue;
            }

            if ($enString && $ord < 32) {
                switch ($ord) {
                    case 10: $resultado .= '\\n'; break;
                    case 13: $resultado .= '\\r'; break;
                    case 9:  $resultado .= '\\t'; break;
                    default: $resultado .= ' '; break;
                }
            } else {
                $resultado .= $char;
            }
        }

        return $resultado;
    }

    /**
     * Intentar reparar un JSON truncado cerrando strings y estructuras abiertas.
     */
    private function repararJsonTruncado(string $json): string
    {
        $json = rtrim($json);

        // Determinar si estamos dentro de un string al final del texto
        $enString = false;
        $escape = false;
        $pilas = [];

        for ($i = 0; $i < strlen($json); $i++) {
            $char = $json[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($char === '\\' && $enString) {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $enString = !$enString;
                continue;
            }

            if (!$enString) {
                if ($char === '{' || $char === '[') {
                    $pilas[] = $char;
                } elseif ($char === '}' && !empty($pilas) && end($pilas) === '{') {
                    array_pop($pilas);
                } elseif ($char === ']' && !empty($pilas) && end($pilas) === '[') {
                    array_pop($pilas);
                }
            }
        }

        // Si terminó dentro de un string, cerrarlo
        if ($enString) {
            $json .= '"';
        }

        // Cerrar todas las estructuras abiertas (arrays y objetos)
        while (!empty($pilas)) {
            $abierto = array_pop($pilas);
            $json .= ($abierto === '{') ? '}' : ']';
        }

        return $json;
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
            'motivos_analizados' => [],
            'analisis_otro_motivo' => [
                'aplica' => false,
                'descripcion_analizada' => null,
                'tipo_falta_determinado' => null,
                'nivel_gravedad' => null,
                'sancion_recomendada' => null,
                'dias_suspension_recomendados' => null,
                'justificacion' => null,
            ],
            'recomendacion_final' => [
                'sancion_sugerida' => 'llamado_atencion',
                'dias_suspension' => null,
                'confianza' => 'baja',
                'mensaje_para_decision' => 'El análisis automático no estuvo disponible. Se recomienda revisar manualmente el caso antes de tomar una decisión. Considere los hechos, los motivos seleccionados, el historial del trabajador y los descargos presentados.',
            ],
        ];
    }
}