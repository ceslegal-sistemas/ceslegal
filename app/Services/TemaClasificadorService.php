<?php

namespace App\Services;

use App\Models\DocumentoLegal;
use App\Models\ReglamentoInterno;
use App\Models\TemaNormativo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemaClasificadorService
{
    /**
     * Si el texto del RIT no cambió desde la última clasificación (mismo
     * hash) y ya tiene temas asignados, no gasta una llamada IA de nuevo -
     * mismo patrón de staleness que usaba bloques_texto_hash en Plan A.
     */
    public function asegurarTemas(ReglamentoInterno $rit): void
    {
        if (empty($rit->texto_completo)) {
            return;
        }

        $hashActual = hash('sha256', $rit->texto_completo);
        if ($rit->temas_texto_hash === $hashActual && $rit->temasNormativos()->exists()) {
            return;
        }

        $temaIds = $this->clasificarTexto($rit->texto_completo);
        $rit->temasNormativos()->sync($temaIds);

        $rit->forceFill([
            'temas_texto_hash'       => $hashActual,
            'temas_clasificados_en'  => now(),
        ])->saveQuietly();
    }

    /**
     * Un DocumentoLegal no se vuelve a editar tras procesado - sin
     * staleness por hash, se clasifica una sola vez.
     */
    public function clasificarDocumento(DocumentoLegal $documento): void
    {
        $texto = $documento->fragmentos()->pluck('contenido')->implode("\n\n");
        if (empty($texto)) {
            return;
        }

        $temaIds = $this->clasificarTexto($texto);
        $documento->temasNormativos()->sync($temaIds);
    }

    /**
     * @return array<int> IDs de TemaNormativo aplicables (puede ser vacío)
     */
    private function clasificarTexto(string $texto): array
    {
        $temas = TemaNormativo::activos()->get(['id', 'nombre', 'descripcion']);
        if ($temas->isEmpty()) {
            return [];
        }

        $listaTemas = $temas->map(fn (TemaNormativo $t) => "- ID {$t->id}: {$t->nombre} — {$t->descripcion}")->implode("\n");

        $prompt = <<<PROMPT
        Eres un asistente legal especializado en derecho laboral colombiano.
        Dado el siguiente texto, identifica cuáles de estos temas normativos
        aplican realmente al contenido (puede ser ninguno, uno, o varios).
        No inventes temas que no estén en la lista. Responde ÚNICAMENTE con
        un array JSON de los IDs numéricos aplicables, sin texto adicional,
        sin markdown. Ejemplo de respuesta válida: [3, 7, 12]

        TEMAS DISPONIBLES:
        {$listaTemas}

        TEXTO A CLASIFICAR (puede estar truncado):
        {$this->truncar($texto)}
        PROMPT;

        try {
            $respuesta = $this->llamarGemini($prompt);
        } catch (\Throwable $e) {
            Log::warning('TemaClasificadorService: fallo al clasificar, se deja sin temas', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        $idsValidos = $temas->pluck('id')->all();
        $idsDetectados = $this->parsearIds($respuesta);

        return array_values(array_intersect($idsDetectados, $idsValidos));
    }

    private function truncar(string $texto): string
    {
        // Gemini 2.5 Flash soporta contexto amplio, pero se acota para no
        // desperdiciar cuota en documentos extremadamente largos (ej. la
        // Ley 2466 de 2025 real, 112k caracteres) - suficiente para que la
        // IA capte los temas generales sin necesitar el texto completo.
        return mb_substr($texto, 0, 60000);
    }

    private function parsearIds(string $respuesta): array
    {
        $limpio = trim($respuesta);
        $limpio = preg_replace('/^```json\s*|\s*```$/i', '', $limpio) ?? $limpio;

        $decodificado = json_decode($limpio, true);
        if (!is_array($decodificado)) {
            return [];
        }

        return array_map('intval', array_filter($decodificado, 'is_numeric'));
    }

    /**
     * Copiado del mismo patrón usado en SolicitudContratoIAService::llamarGemini()
     * (y otros servicios de este proyecto) - sin trait compartido, es la
     * convención ya establecida en el repo.
     */
    private function llamarGemini(string $prompt): string
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
                'temperature'     => 0.1,
                'maxOutputTokens' => 1024,
                'topP'            => 0.95,
                // Sin esto, Gemini 2.5 consume parte de maxOutputTokens en
                // "thinking" interno antes de responder, y la respuesta
                // real queda truncada a mitad del JSON (bug real
                // encontrado al probar: "[25, 27, 19, 21, 10," sin cerrar,
                // json_decode() devolvía null, 0 temas siempre) - mismo
                // fix ya usado en otro punto de este proyecto para el
                // mismo problema (pipeline de 3 agentes de descargos).
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ];

        $lastError = null;

        foreach ($modelosCascada as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            for ($intento = 1; $intento <= 2; $intento++) {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(60)
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
                Log::warning('TemaClasificadorService: fallo en intento', [
                    'model' => $model, 'intento' => $intento, 'status' => $status,
                ]);
                $lastError = $response->body();

                if (in_array($status, [429, 503], true) && $intento < 2) {
                    sleep(10);
                }
            }
        }

        throw new \RuntimeException('No se pudo clasificar el texto con IA: ' . $lastError);
    }
}
