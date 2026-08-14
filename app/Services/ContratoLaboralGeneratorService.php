<?php

namespace App\Services;

use App\Models\ContratoLaboral;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContratoLaboralGeneratorService
{
    public function __construct(
        private readonly RITGeneratorService $ritGeneratorService,
    ) {}

    /**
     * Redacta Objeto, Duración y Terminación del contrato con IA, ancladas en
     * los artículos del CST que correspondan al tipo de contrato elegido.
     * Persiste el resultado en $contrato->clausulas_generadas y
     * $contrato->articulos_cst_citados (trazabilidad de qué se usó).
     */
    public function generarClausulas(ContratoLaboral $contrato): string
    {
        $temaCst = match ($contrato->tipo) {
            'fijo'       => 'contrato trabajo término fijo duración renovación',
            'indefinido' => 'contrato trabajo término indefinido',
            'obra_labor' => 'contrato trabajo obra labor determinada',
            'ocasional'  => 'contrato trabajo ocasional accidental transitorio',
        };

        $articulosCst = $this->ritGeneratorService->buscarArticulosPorTema($temaCst, limite: 6);

        $prompt = $this->construirPrompt($contrato, $articulosCst);
        $clausulas = $this->llamarGemini($prompt, $contrato->empresa_id);

        // Trazabilidad: qué códigos de artículo aparecen realmente citados en
        // el texto generado (no se confía en que la IA declare una lista aparte).
        preg_match_all('/Art[íi]culo\s+(\d+)/iu', $clausulas, $m);
        $codigosCitados = array_values(array_unique($m[1] ?? []));

        $contrato->update([
            'clausulas_generadas'   => $clausulas,
            'articulos_cst_citados' => $codigosCitados,
        ]);

        return $clausulas;
    }

    private function construirPrompt(ContratoLaboral $contrato, string $articulosCst): string
    {
        $trabajador = $contrato->trabajador;
        $empresa    = $contrato->empresa;

        return <<<PROMPT
        Eres un abogado laboralista colombiano redactando las cláusulas de
        Objeto, Duración y Terminación de un contrato de trabajo, con base
        ÚNICAMENTE en los datos provistos y los artículos del Código
        Sustantivo del Trabajo (CST) listados abajo.

        PROHIBICIÓN ABSOLUTA: Solo puedes citar artículos del CST que
        aparezcan en la sección "ARTÍCULOS DEL CST DISPONIBLES" de abajo. Si
        ninguno aplica exactamente, redacta sin citar número de artículo en
        vez de inventar uno.

        PROHIBICIÓN ABSOLUTA: No inventes salario, fechas, funciones ni
        ningún dato que no esté explícitamente en "DATOS DEL CONTRATO" abajo.

        DATOS DEL CONTRATO:
        - Empresa: {$empresa->nombre_completo}
        - Trabajador: {$trabajador->nombre_completo}, cargo: {$trabajador->cargo}
        - Tipo de contrato: {$contrato->tipo}
        - Salario: {$contrato->salario} ({$contrato->periodicidad_pago})
        - Funciones del cargo: {$contrato->funciones_cargo}
        - Fecha de inicio: {$contrato->fecha_inicio?->format('Y-m-d')}
        - Fecha de fin: {$contrato->fecha_fin?->format('Y-m-d')}
        - Descripción de obra/labor: {$contrato->descripcion_obra}

        ARTÍCULOS DEL CST DISPONIBLES:
        {$articulosCst}

        Redacta EXACTAMENTE 3 secciones, cada una con su título en mayúsculas
        seguido de dos puntos, sin markdown ni asteriscos:

        OBJETO: [1-2 párrafos describiendo el objeto del contrato y las
        funciones del cargo]

        DURACIÓN: [1 párrafo describiendo la duración según el tipo de
        contrato, citando el artículo del CST si aplica]

        TERMINACIÓN: [1 párrafo describiendo las causales de terminación
        aplicables a este tipo de contrato, citando el artículo del CST si
        aplica]
        PROMPT;
    }

    /**
     * Copiado del mismo patrón usado en RITGeneratorService::llamarGemini()
     * (y otros 6 servicios de este proyecto) - sin trait compartido, es la
     * convención ya establecida en el repo.
     */
    private function llamarGemini(string $prompt, int $empresaId = 0): string
    {
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? '';

        $modelosCascada = ['gemini-2.5-flash', 'gemini-2.5-flash-lite'];

        $prompt = preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $prompt
        ) ?? iconv('UTF-8', 'UTF-8//IGNORE', $prompt);

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 8192,
                'topP'            => 0.95,
            ],
        ];

        $lastError = null;

        foreach ($modelosCascada as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            for ($intento = 1; $intento <= 2; $intento++) {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(90)
                    ->post($url, $payload);

                if ($response->successful()) {
                    $data  = $response->json();
                    $parts = $data['candidates'][0]['content']['parts'] ?? [];
                    $texto = $parts[0]['text'] ?? '';

                    if (!empty($texto)) {
                        return trim($texto);
                    }
                }

                $status = $response->status();
                Log::warning('ContratoLaboralGeneratorService: fallo en intento', [
                    'empresa_id' => $empresaId, 'model' => $model,
                    'intento' => $intento, 'status' => $status,
                ]);
                $lastError = $response->body();

                if (in_array($status, [429, 503], true) && $intento < 2) {
                    sleep(10);
                }
            }
        }

        throw new \RuntimeException('No se pudo generar las cláusulas con IA: ' . $lastError);
    }
}
