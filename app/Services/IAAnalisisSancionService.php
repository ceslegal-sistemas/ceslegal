<?php

namespace App\Services;

use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Services\ReglamentoInternoService;
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

            // Obtener contexto del RIT: array estructurado (wizard) o fragmentos RAG (subido)
            [$sancionesRIT, $contextoRITRag] = $this->obtenerContextoRIT($empresa, $proceso);

            // Construir el prompt para la IA
            $prompt = $this->construirPromptAnalisisSancion(
                $proceso,
                $trabajador,
                $empresa,
                $historialProcesos,
                $contextoDescargos,
                $sancionesRIT,
                $contextoRITRag
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
     * Retorna [$sancionesRIT, $contextoRITRag]:
     * - Wizard (construido_ia): $sancionesRIT = array estructurado, $contextoRITRag = ''.
     * - Subido (DOCX/PDF):      $sancionesRIT = [],  $contextoRITRag = fragmentos RAG relevantes.
     */
    private function obtenerContextoRIT($empresa, ProcesoDisciplinario $proceso): array
    {
        $rit = $empresa->reglamentoInterno;
        if (!$rit) {
            return [[], ''];
        }

        try {
            $service = app(ReglamentoInternoService::class);

            // Wizard: datos ya estructurados desde el cuestionario
            if ($rit->fuente === 'construido_ia') {
                return [$service->extraerSancionesParaEmail($rit), ''];
            }

            // Documento subido: usar RAG sobre el texto completo
            $query    = $this->construirQueryRIT($proceso);
            $contexto = $service->buscarEnRIT($rit, $query);

            return [[], $contexto];

        } catch (\Throwable $e) {
            Log::warning('IAAnalisisSancionService: error obteniendo contexto RIT', [
                'empresa_id' => $empresa->id,
                'fuente'     => $rit->fuente ?? 'desconocida',
                'error'      => $e->getMessage(),
            ]);
            return [[], ''];
        }
    }

    /**
     * Construye la query de búsqueda RAG combinando los motivos del proceso con
     * términos disciplinarios clave para maximizar la recuperación de fragmentos relevantes.
     */
    private function construirQueryRIT(ProcesoDisciplinario $proceso): string
    {
        $partes = ['faltas leves graves sanciones disciplinarias suspensión terminación llamado atención reglamento disciplinario'];

        $nombres = $proceso->sancionesLaborales->pluck('nombre_claro')->filter()->join(' ');
        if ($nombres) {
            $partes[] = $nombres;
        }

        if ($proceso->hechos) {
            $partes[] = mb_substr(strip_tags($proceso->hechos), 0, 300);
        }

        return implode(' ', $partes);
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
                $detalle .= "   NOTA: Este motivo es una REINCIDENCIA (no es la primera vez)\n";
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
        string $contextoDescargos,
        array $sancionesRIT = [],
        string $contextoRITRag = ''
    ): string {
        $hechosTexto = strip_tags($proceso->hechos);
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

        // Construir sección del RIT de la empresa
        $seccionRIT = '';

        // Caso A: fragmentos RAG del documento subido (texto real del RIT)
        if (!empty($contextoRITRag)) {
            $seccionRIT  = "\n═══════════════════════════════════════════════════════════════════\n";
            $seccionRIT .= "EXTRACTOS DEL REGLAMENTO INTERNO DE {$empresa->nombre_completo} (RAG):\n";
            $seccionRIT .= "═══════════════════════════════════════════════════════════════════\n";
            $seccionRIT .= $contextoRITRag . "\n";
            $seccionRIT .= "INSTRUCCIÓN: Estos son fragmentos reales del RIT de la empresa. Úsalos para\n";
            $seccionRIT .= "determinar qué conductas son faltas, qué sanciones contempla y sus límites.\n";
            $seccionRIT .= "No sugieras sanciones que el RIT no prevea explícitamente.\n";

        // Caso B: datos estructurados del wizard (construido_ia)
        } elseif (!empty($sancionesRIT['faltas_leves']) || !empty($sancionesRIT['faltas_graves'])) {
            $seccionRIT  = "\n═══════════════════════════════════════════════════════════════════\n";
            $seccionRIT .= "RÉGIMEN DISCIPLINARIO DEL RIT DE {$empresa->nombre_completo}:\n";
            $seccionRIT .= "═══════════════════════════════════════════════════════════════════\n";

            if (!empty($sancionesRIT['faltas_leves'])) {
                $seccionRIT .= "FALTAS LEVES definidas en el RIT:\n";
                foreach ($sancionesRIT['faltas_leves'] as $f) {
                    $seccionRIT .= "  - {$f}\n";
                }
            }
            if (!empty($sancionesRIT['faltas_graves'])) {
                $seccionRIT .= "FALTAS GRAVES definidas en el RIT:\n";
                foreach ($sancionesRIT['faltas_graves'] as $f) {
                    $seccionRIT .= "  - {$f}\n";
                }
            }
            if (!empty($sancionesRIT['sanciones'])) {
                $seccionRIT .= "SANCIONES CONTEMPLADAS en el RIT:\n";
                foreach ($sancionesRIT['sanciones'] as $s) {
                    $seccionRIT .= "  - {$s}\n";
                }
            }
            $seccionRIT .= "INSTRUCCIÓN: Las sanciones disponibles para tu recomendación final deben respetar\n";
            $seccionRIT .= "lo que el RIT de la empresa contempla. No sugiera sanciones que el RIT no prevea.\n";
        }

        return <<<PROMPT
Eres un abogado laboralista colombiano con amplia experiencia en procesos disciplinarios. Analiza el siguiente proceso y determina la sanción apropiada basándote EXCLUSIVAMENTE en tres fuentes, en este orden de prioridad:

1. EL REGLAMENTO INTERNO DE TRABAJO (RIT) DE LA EMPRESA — es la fuente primaria: define qué conductas son faltas leves o graves y qué sanciones contempla.
2. EL CÓDIGO SUSTANTIVO DEL TRABAJO (CST) — establece los límites legales: Art. 112 (suspensión: máximo 8 días primera vez, hasta 2 meses en caso de reincidencia), Art. 62 (causales de terminación con justa causa).
3. EL HISTORIAL DISCIPLINARIO DEL TRABAJADOR — determina reincidencia y agravantes.

INSTRUCCIÓN CRÍTICA: No inventes rangos de días ni categorías de faltas. Deriva TODO de lo que el RIT de esta empresa específicamente contempla. Si el RIT dice "suspensión hasta 8 días", no puedes sugerir 30 días. Si el RIT no contempla terminación, no la sugieras.

INFORMACIÓN DEL PROCESO:
- Empresa: {$empresa->nombre_completo}
- Trabajador: {$trabajador->nombre_completo}
- Cargo: {$trabajador->cargo}

HECHOS DEL CASO ACTUAL:
{$hechosTexto}
{$seccionRIT}
═══════════════════════════════════════════════════════════════════
MOTIVOS DE LOS DESCARGOS SELECCIONADOS:
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

PROCESO DE ANÁLISIS:
1. Clasifica la conducta como LEVE o GRAVE según lo que el RIT de la empresa define. Si el RIT no tiene esa conducta, usa el CST como referencia.
2. Verifica si hay reincidencia en el historial — agrava la sanción conforme al RIT y Art. 112 CST.
3. Evalúa los descargos del trabajador — considera atenuantes y argumentos de defensa.
4. De las sanciones que el RIT contempla, selecciona la apropiada. Si el RIT no aporta datos, aplica solo lo que el CST permite.
5. Para suspensiones: indica únicamente días dentro del rango que el RIT establece, respetando el límite del Art. 112 CST.

Responde EXACTAMENTE en este formato JSON (sin código markdown, sin texto adicional):
{
  "gravedad": "leve|grave",
  "nivel_gravedad": "ninguno|bajo|alto",
  "es_reincidencia": true/false,
  "justificacion": "Por qué la conducta es leve o grave, citando el RIT o CST aplicable",
  "sanciones_disponibles": ["llamado_atencion", "suspension", "terminacion"],
  "sancion_recomendada": "llamado_atencion|suspension|terminacion",
  "dias_suspension_sugeridos": [],
  "razonamiento_legal": "Fundamento en el RIT de la empresa y el CST. Citar artículo o capítulo del RIT si aplica",
  "consideraciones_especiales": "Historial, descargos del trabajador, atenuantes o agravantes relevantes",
  "motivos_analizados": [
    {
      "motivo": "Nombre del motivo",
      "tipo_falta": "leve|grave",
      "sancion_asociada": "llamado_atencion|suspension|terminacion",
      "observacion": "Análisis breve de este motivo específico"
    }
  ],
  "analisis_otro_motivo": {
    "aplica": true/false,
    "descripcion_analizada": "Descripción del otro motivo",
    "tipo_falta_determinado": "leve|grave",
    "nivel_gravedad": "ninguno|bajo|alto",
    "sancion_recomendada": "llamado_atencion|suspension|terminacion",
    "dias_suspension_recomendados": null,
    "justificacion": "Análisis de este motivo según RIT y CST"
  },
  "recomendacion_final": {
    "sancion_sugerida": "llamado_atencion|suspension|terminacion",
    "dias_suspension": null,
    "confianza": "alta|media|baja",
    "mensaje_para_decision": "Mensaje para el empleador explicando la recomendación, sus fundamentos y las opciones disponibles"
  }
}

REGLAS ESTRICTAS:
- sanciones_disponibles: incluye SOLO las sanciones que el RIT de esta empresa contempla. Si no hay datos del RIT, aplica las que permite el CST según la gravedad.
- dias_suspension_sugeridos: array con los días posibles DENTRO del rango que especifica el RIT (ej: si RIT dice "1 a 8 días", pon [1,2,3,5,8]). Array vacío si no aplica suspensión.
- dias_suspension (recomendacion_final): un número concreto dentro del rango del RIT, o null si no hay suspensión.
- Si es FALTA LEVE: nivel_gravedad="ninguno", sanciones solo incluye lo que el RIT contemple para faltas leves.
- Si es FALTA GRAVE: nivel_gravedad="bajo" o "alto" según el impacto real de la conducta y el RIT.
- Confianza "alta": el RIT clasifica explícitamente esta conducta. "media": se infiere del RIT. "baja": no hay datos del RIT, se aplica solo el CST.
- En "motivos_analizados": incluye CADA motivo seleccionado con su análisis individual.
- Si hay "otro motivo": analisis_otro_motivo.aplica=true y completa TODOS sus campos.
- Si NO hay "otro motivo": analisis_otro_motivo.aplica=false y los demás campos son null.
- Máximo 150 palabras por campo de texto. No uses saltos de línea dentro de strings JSON.
- Genera SOLO el JSON, sin markdown ni texto fuera del objeto JSON.
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