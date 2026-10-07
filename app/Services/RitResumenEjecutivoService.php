<?php

namespace App\Services;

use App\Models\ReglamentoInterno;
use App\Models\ResumenEjecutivoRitCambio;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pedido de Andrés Sarmiento (reunión 2026-10-05): el trabajador no debe
 * tener que leer el redline técnico (agregado/eliminado/modificado) para
 * entender qué cambió de verdad - un botón "Resumen ejecutivo" debe
 * explicarlo en lenguaje simple con enfoque de "legal design". Cita
 * literal: "cuando estés creando ese prompt la palabra legal design es
 * muy importante que la uses porque [el Dr. Ernesto] entiende que es
 * legal design".
 *
 * Se cachea por hash del diff exacto (no por RIT) porque dos trabajadores
 * en esta misma actualización pueden tener un "antes" distinto según cuál
 * fue la última versión que cada uno aceptó - ver
 * SocializacionRit::prepararPresentacionRit() - así que dos trabajadores
 * no necesariamente comparten el mismo $cambiosRit, pero cuando sí lo
 * comparten (el caso típico: todos aceptando la misma actualización) no
 * se paga una llamada a Gemini por cada uno.
 */
class RitResumenEjecutivoService
{
    public function obtenerOGenerar(ReglamentoInterno $rit, array $cambios): string
    {
        $hash = hash('sha256', json_encode($cambios));

        $cacheado = ResumenEjecutivoRitCambio::where('hash_comparacion', $hash)->first();
        if ($cacheado) {
            return $cacheado->resumen;
        }

        $textoCambios = $this->extraerTextoCambios($cambios);
        if (trim($textoCambios) === '') {
            return 'No se modificó nada en este Reglamento.';
        }

        try {
            $resumen = $this->generarConIA($textoCambios);
        } catch (\Throwable $e) {
            Log::warning('RitResumenEjecutivoService: fallo al generar resumen ejecutivo', [
                'error' => $e->getMessage(),
            ]);

            // Fail-open: no se cachea el fallo, para que un reintento
            // posterior (el mismo trabajador u otro) sí pueda lograrlo.
            return 'No fue posible generar el resumen ejecutivo en este momento. Puedes revisar el detalle de los cambios arriba.';
        }

        ResumenEjecutivoRitCambio::create([
            'reglamento_interno_id' => $rit->id,
            'hash_comparacion' => $hash,
            'resumen' => $resumen,
        ]);

        return $resumen;
    }

    private function extraerTextoCambios(array $cambios): string
    {
        $partes = [];

        foreach ($cambios as $c) {
            if ($c['tipo'] === 'agregado') {
                $partes[] = "AGREGADO: {$c['texto']}";
            } elseif ($c['tipo'] === 'eliminado') {
                $partes[] = "ELIMINADO: {$c['texto']}";
            } elseif ($c['tipo'] === 'modificado') {
                $antes = collect($c['palabras'])->where('tipo', '!=', 'agregado')->pluck('texto')->implode('');
                $despues = collect($c['palabras'])->where('tipo', '!=', 'eliminado')->pluck('texto')->implode('');
                $partes[] = "MODIFICADO - ANTES: {$antes}\nMODIFICADO - AHORA: {$despues}";
            }
        }

        return implode("\n\n", $partes);
    }

    private function generarConIA(string $textoCambios): string
    {
        $prompt = <<<PROMPT
        Eres un abogado laboral especializado en "legal design": explicar
        documentos legales en lenguaje simple y cotidiano, sin tecnicismos,
        para que cualquier trabajador sin formación jurídica entienda
        exactamente qué cambió para él en la práctica.

        A continuación tienes los cambios exactos (agregados, eliminados y
        modificados) de un Reglamento Interno de Trabajo. Redacta un
        resumen ejecutivo en viñetas cortas, en español, que explique cada
        cambio relevante en términos prácticos y concretos - NO cites el
        texto legal textual, tradúcelo a lo que significa en el día a día
        del trabajador. Por ejemplo, en vez de citar el artículo, escribe
        algo como "ahora ya no entra a las dos sino a las cinco".

        Si un cambio es puramente de redacción o formato, sin efecto
        práctico real para el trabajador, omítelo del resumen.

        No agregues introducción ni conclusión, solo las viñetas (cada una
        inicia con "-"). Máximo 8 viñetas.

        CAMBIOS:
        {$textoCambios}
        PROMPT;

        return $this->llamarGemini($prompt, 1024);
    }

    /**
     * Mismo patrón que TemaClasificadorService::llamarGemini() (y otros
     * servicios de este proyecto) - sin trait compartido, es la
     * convención ya establecida en el repo.
     */
    private function llamarGemini(string $prompt, int $maxOutputTokens = 1024): string
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
                'temperature'     => 0.2,
                'maxOutputTokens' => $maxOutputTokens,
                'topP'            => 0.95,
                // Sin esto, Gemini 2.5 consume parte de maxOutputTokens en
                // "thinking" interno antes de responder - mismo fix ya
                // usado en otros puntos de este proyecto para el mismo
                // problema.
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
                Log::warning('RitResumenEjecutivoService: fallo en intento', [
                    'model' => $model, 'intento' => $intento, 'status' => $status,
                ]);
                $lastError = $response->body();

                if (in_array($status, [429, 503], true) && $intento < 2) {
                    sleep(10);
                }
            }
        }

        throw new \RuntimeException('No se pudo generar el resumen ejecutivo con IA: ' . $lastError);
    }
}
