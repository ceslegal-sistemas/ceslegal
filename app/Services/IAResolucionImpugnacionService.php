<?php

namespace App\Services;

use App\Models\ProcesoDisciplinario;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IAResolucionImpugnacionService
{
    /**
     * Analizar la impugnación y recomendar decisión con fundamento jurídico
     */
    public function analizarImpugnacion(ProcesoDisciplinario $proceso): array
    {
        try {
            $impugnacion = $proceso->impugnacion;
            $trabajador = $proceso->trabajador;
            $empresa = $proceso->empresa;

            if (!$impugnacion) {
                throw new \Exception('No se encontró la impugnación para este proceso');
            }

            $prompt = $this->construirPrompt($proceso, $impugnacion, $trabajador, $empresa);

            $provider = config('services.ia.provider', 'gemini');
            $config = config("services.ia.{$provider}", []);
            $apiKey = $config['api_key'];
            $model = $config['model'];
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            Log::info('Analizando impugnación con IA', [
                'proceso_id' => $proceso->id,
                'decision_impugnada' => $proceso->tipo_sancion,
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(90)->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
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
            $analisis = $this->parsearRespuesta($analisisTexto);

            Log::info('Análisis de impugnación completado', [
                'proceso_id' => $proceso->id,
                'decision_recomendada' => $analisis['decision_recomendada'] ?? 'desconocida',
            ]);

            return [
                'success' => true,
                'analisis' => $analisis,
            ];

        } catch (\Exception $e) {
            Log::error('Error al analizar impugnación con IA', [
                'proceso_id' => $proceso->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'analisis' => $this->obtenerAnalisisPorDefecto(),
            ];
        }
    }

    private function construirPrompt(
        ProcesoDisciplinario $proceso,
        $impugnacion,
        $trabajador,
        $empresa
    ): string {
        $hechosTexto = strip_tags($proceso->hechos);
        $sancionOriginal = match ($proceso->tipo_sancion) {
            'llamado_atencion' => 'Llamado de Atención',
            'suspension' => 'Suspensión Laboral' . ($proceso->dias_suspension ? " de {$proceso->dias_suspension} día(s)" : ''),
            'terminacion' => 'Terminación de Contrato',
            default => ucfirst(str_replace('_', ' ', $proceso->tipo_sancion ?? 'N/A')),
        };

        // Contexto de descargos
        $contextoDescargos = 'No se realizaron descargos.';
        $diligencia = $proceso->diligenciaDescargo;
        if ($diligencia) {
            $preguntas = $diligencia->preguntas()->with('respuesta')->ordenadas()->get();
            if ($preguntas->isNotEmpty()) {
                $contextoDescargos = '';
                foreach ($preguntas as $i => $pregunta) {
                    $respuesta = $pregunta->respuesta?->respuesta ?? 'Sin respuesta';
                    $contextoDescargos .= ($i + 1) . ". {$pregunta->pregunta} -> {$respuesta}\n";
                }
            }
        }

        // Motivos del reglamento
        $motivosTexto = $proceso->sanciones_laborales_texto ?? 'No especificado';

        // Timeline del proceso
        $timelineTexto = '';
        $timeline = $proceso->timeline()->orderBy('created_at', 'asc')->get();
        foreach ($timeline as $evento) {
            $fecha = $evento->created_at->format('d/m/Y H:i');
            $timelineTexto .= "- [{$fecha}] {$evento->descripcion}\n";
        }
        if (empty($timelineTexto)) {
            $timelineTexto = "No hay registros de timeline.";
        }

        return <<<PROMPT
Actúa como un experto en Derecho Disciplinario y Procesal Laboral colombiano. Analiza el siguiente expediente disciplinario para asistir en la resolución de un recurso de impugnación.

EXPEDIENTE DISCIPLINARIO:
- Empresa: {$empresa->razon_social}
- Trabajador: {$trabajador->nombre_completo}
- Cargo: {$trabajador->cargo}
- Código del proceso: {$proceso->codigo}

CRONOLOGÍA DEL PROCESO:
{$timelineTexto}

HECHOS QUE MOTIVARON EL PROCESO:
{$hechosTexto}

MOTIVOS DEL REGLAMENTO INTERNO INCUMPLIDOS:
{$motivosTexto}

DESCARGOS DEL TRABAJADOR:
{$contextoDescargos}

SANCIÓN IMPUESTA EN PRIMERA INSTANCIA:
{$sancionOriginal}

IMPUGNACIÓN DEL TRABAJADOR:
Fecha: {$impugnacion->fecha_impugnacion?->format('d/m/Y')}
Motivos expuestos:
"{$impugnacion->motivos_impugnacion}"

INSTRUCCIONES DE ANÁLISIS:

1. AUDITORÍA DEL PROCESO: Analiza cronológicamente las actuaciones y verifica que se hayan respetado el debido proceso, el derecho de defensa y los términos legales.

2. CONTRASTE DE ARGUMENTOS: Identifica los puntos clave de la impugnación y contrástalos con las pruebas, normas aplicadas y los descargos del trabajador.

3. RECOMENDACIÓN DE DECISIÓN: Sugiere la decisión más sólida jurídicamente (Confirmar, Revocar o Modificar), justificando por qué esa opción minimiza riesgos de nulidad.

4. FUNDAMENTO JURÍDICO: Genera un fundamento detallado que sustente la decisión recomendada, citando el Código Sustantivo del Trabajo y principios del derecho disciplinario.

Responde EXACTAMENTE en este formato JSON (sin bloques de código markdown):
{
  "auditoria_proceso": "Resumen de la auditoría cronológica del expediente, verificando debido proceso y derecho de defensa.",
  "puntos_clave_impugnacion": ["Punto 1 del trabajador", "Punto 2"],
  "contraste_argumentos": "Análisis contrastando los argumentos del trabajador contra las pruebas y normas.",
  "decision_recomendada": "confirma_sancion|revoca_sancion|modifica_sancion",
  "confianza": "alta|media|baja",
  "justificacion_decision": "Explicación de por qué esta decisión es la más sólida jurídicamente y minimiza riesgos de nulidad.",
  "riesgos_nulidad": "Riesgos identificados si se toma una decisión diferente a la recomendada.",
  "fundamento_juridico": "Fundamento jurídico detallado para sustentar la decisión, citando CST y principios de derecho disciplinario. Este texto se incluirá directamente en el documento de resolución.",
  "modificacion_sugerida": {
    "aplica": true/false,
    "nueva_sancion": "llamado_atencion|suspension|terminacion|null",
    "dias_suspension": null,
    "justificacion": "Solo si recomienda modificar, explicar qué sanción alternativa y por qué."
  }
}

REGLAS DE FORMATO:
- Genera SOLO el JSON, sin texto adicional.
- Sé conciso: máximo 150 palabras por campo de texto.
- No uses saltos de línea dentro de los valores string del JSON.
- El campo "fundamento_juridico" debe ser un texto formal, listo para incluir en un documento legal.
PROMPT;
    }

    private function parsearRespuesta(string $texto): array
    {
        $texto = trim($texto);
        $texto = preg_replace('/```json\s*/', '', $texto);
        $texto = preg_replace('/```\s*$/', '', $texto);
        $texto = preg_replace('/```/', '', $texto);

        // Escapar caracteres de control dentro de strings JSON
        $texto = $this->escaparControlesEnStringsJson($texto);

        try {
            $analisis = json_decode($texto, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $reparado = $this->repararJsonTruncado($texto);
                $analisis = json_decode($reparado, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Error al parsear JSON: ' . json_last_error_msg());
                }
            }

            if (!isset($analisis['decision_recomendada'])) {
                throw new \Exception('Respuesta de IA con estructura inválida');
            }

            return $analisis;

        } catch (\Exception $e) {
            Log::warning('Error al parsear análisis de impugnación', [
                'error' => $e->getMessage(),
                'respuesta_ia' => mb_substr($texto, 0, 500),
            ]);

            return $this->obtenerAnalisisPorDefecto();
        }
    }

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

    private function repararJsonTruncado(string $json): string
    {
        $json = rtrim($json);
        $enString = false;
        $escape = false;
        $pilas = [];

        for ($i = 0; $i < strlen($json); $i++) {
            $char = $json[$i];
            if ($escape) { $escape = false; continue; }
            if ($char === '\\' && $enString) { $escape = true; continue; }
            if ($char === '"') { $enString = !$enString; continue; }
            if (!$enString) {
                if ($char === '{' || $char === '[') { $pilas[] = $char; }
                elseif ($char === '}' && !empty($pilas) && end($pilas) === '{') { array_pop($pilas); }
                elseif ($char === ']' && !empty($pilas) && end($pilas) === '[') { array_pop($pilas); }
            }
        }

        if ($enString) { $json .= '"'; }
        while (!empty($pilas)) {
            $abierto = array_pop($pilas);
            $json .= ($abierto === '{') ? '}' : ']';
        }

        return $json;
    }

    private function obtenerAnalisisPorDefecto(): array
    {
        return [
            'auditoria_proceso' => 'Análisis manual requerido - el sistema no pudo realizar la auditoría automáticamente.',
            'puntos_clave_impugnacion' => [],
            'contraste_argumentos' => 'Se requiere revisión manual de los argumentos.',
            'decision_recomendada' => 'confirma_sancion',
            'confianza' => 'baja',
            'justificacion_decision' => 'El análisis automático no estuvo disponible. Se recomienda revisar manualmente el expediente.',
            'riesgos_nulidad' => 'Sin información automática disponible.',
            'fundamento_juridico' => '',
            'modificacion_sugerida' => [
                'aplica' => false,
                'nueva_sancion' => null,
                'dias_suspension' => null,
                'justificacion' => null,
            ],
        ];
    }
}
