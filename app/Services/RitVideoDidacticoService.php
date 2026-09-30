<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Segunda socialización" pedida en la reunión con el equipo (2026-09-28):
 * un video corto y didáctico que explica el Reglamento Interno en lenguaje
 * sencillo, en vez de una presentación de diapositivas. Genera el video con
 * Gemini Omni Flash (`gemini-omni-1.1-flash`, confirmado accesible con la
 * cuenta de este proyecto - ver config('services.ia.gemini.model_video')).
 *
 * Disparo SIEMPRE manual (nunca automático) y se genera UNA SOLA VEZ por
 * Reglamento (no por trabajador) - el video queda guardado en el propio
 * registro del RIT y todos los trabajadores que lo socialicen ven el mismo
 * archivo. Costo real confirmado 2026-09-29 por el usuario en
 * https://ai.studio/spend: ~1.170 COP por clip de ~10s a 360p - lo
 * suficientemente barato para encadenar varias llamadas de "extend" y
 * cubrir más contenido, en vez de limitarse a un solo clip corto.
 */
class RitVideoDidacticoService
{
    /**
     * Un solo generateContent de Gemini Omni produce un clip de ~10s. La API
     * permite extenderlo con llamadas adicionales de "extend"
     * (previous_interaction_id), cada una cubriendo UN punto (cambio o tema)
     * del reglamento. Subido de 4 a 8 (2026-09-29) tras ver el primer video
     * real: con solo 4 temas el video se sentía incompleto frente a los hasta
     * 27 temas normativos que puede tener un RIT (ver
     * rit-taxonomia-temas-implementada). 8 duplica la cobertura (~80s de
     * contenido + despedida) sin acercarse al costo de cubrir el reglamento
     * completo.
     */
    private const MAX_SEGMENTOS = 8;

    /**
     * @param array $cambios Diff de RitDiffService::compararDocumentos() (opcional) -
     *   si se pasa, el video se enfoca en "qué cambió" en vez de temas genéricos.
     */
    public function generar(ReglamentoInterno $rit, array $cambios = []): void
    {
        $empresa = $rit->empresa;

        $cambiosReales = array_values(array_filter($cambios, fn ($c) => $c['tipo'] !== 'igual'));

        $segmentos = !empty($cambiosReales)
            ? $this->segmentosDeCambios($cambiosReales)
            : $this->segmentosDeTemas($rit);

        if (empty($segmentos)) {
            throw new \RuntimeException('El Reglamento no tiene temas clasificados ni cambios para explicar en el video.');
        }

        $logoBase64 = $this->logoBase64($empresa);

        $inputInicial = [];
        if ($logoBase64) {
            $inputInicial[] = ['type' => 'image', 'data' => $logoBase64['data'], 'mime_type' => $logoBase64['mime']];
        }
        $inputInicial[] = ['type' => 'text', 'text' => $this->promptInicial($empresa, $segmentos[0], (bool) $logoBase64)];

        [$interactionId, $videoBase64] = $this->crearInteraccion($inputInicial);

        foreach (array_slice($segmentos, 1) as $segmento) {
            [$interactionId, $videoBase64] = $this->extenderInteraccion($interactionId, $this->promptExtension($segmento));
        }

        // Cierre pedido por el usuario tras ver el primer video real
        // (2026-09-29): agradecimiento y despedida breve, siempre al final,
        // sin importar el modo (cambios o temas).
        [$interactionId, $videoBase64] = $this->extenderInteraccion($interactionId, $this->promptDespedida($empresa));

        $ruta = "rit-videos/{$rit->empresa_id}/video_{$rit->id}_" . Str::random(8) . '.mp4';
        Storage::disk('local')->put($ruta, base64_decode($videoBase64));

        $rit->update([
            'video_didactico_path' => $ruta,
            'video_didactico_estado' => 'completado',
            'video_didactico_error' => null,
            'video_didactico_generado_en' => now(),
        ]);
    }

    /**
     * Narración hablada únicamente, SIN texto/viñetas en pantalla - reportado
     * en producción (2026-09-29): el texto que Gemini renderiza sobre el
     * video sale ilegible/deformado, mientras que la voz narrada sí se
     * entiende bien.
     */
    private function promptInicial(?Empresa $empresa, string $primerSegmento, bool $conLogo): string
    {
        $prompt = "Video didáctico y profesional en español, para explicarle a un trabajador colombiano "
            . "el Reglamento Interno de Trabajo de la empresa \"{$empresa?->nombre_completo}\". "
            . "Un presentador habla directamente a cámara, con tono cercano y claro, sin lenguaje jurídico complicado. "
            . "No muestres texto, viñetas ni subtítulos escritos en pantalla en ningún momento del video - "
            . "solo narración hablada. "
            . "Empieza explicando en voz este punto:\n{$primerSegmento}\n"
            . "Narración en español neutro colombiano. No incluya música con derechos de autor reconocibles.";

        if ($conLogo) {
            $prompt = "El video abre mostrando el logo de la empresa <IMAGE_REF_0> como carta de presentación, "
                . "y lo mantiene discretamente en una esquina durante el resto del video. " . $prompt;
        }

        return $prompt;
    }

    private function promptExtension(string $segmento): string
    {
        return "Continúa el video: el mismo presentador sigue hablando a cámara, con el mismo tono y estilo, "
            . "explicando en voz (sin mostrar texto, viñetas ni subtítulos escritos en pantalla) "
            . "este siguiente punto del reglamento:\n{$segmento}\n"
            . 'Transición suave, sin corte abrupto de escena.';
    }

    private function promptDespedida(?Empresa $empresa): string
    {
        return "Continúa el video: el mismo presentador cierra agradeciendo brevemente al trabajador de "
            . "\"{$empresa?->nombre_completo}\" por conocer su Reglamento Interno, y se despide. "
            . 'Sin texto en pantalla, solo voz. Transición suave.';
    }

    /** @return array<int, string> Hasta MAX_SEGMENTOS viñetas, una por tema. */
    private function segmentosDeTemas(ReglamentoInterno $rit): array
    {
        return $rit->temasNormativos()
            ->activos()
            ->get(['temas_normativos.id', 'temas_normativos.nombre', 'temas_normativos.descripcion'])
            ->take(self::MAX_SEGMENTOS)
            ->map(fn ($tema) => '- ' . $tema->nombre . ': ' . ($tema->pivot->resumen_simple ?: $tema->descripcion))
            ->all();
    }

    /**
     * Reconstruye, en lenguaje humano, los cambios más relevantes de un diff
     * de RitDiffService::compararDocumentos() - un bloque "modificado" trae
     * un diff de PALABRAS (no un texto plano), así que se reconstruye el
     * texto nuevo (palabras 'igual' + 'agregado', descartando las 'eliminado').
     *
     * @return array<int, string> Hasta MAX_SEGMENTOS viñetas, una por cambio.
     */
    private function segmentosDeCambios(array $cambios): array
    {
        return collect($cambios)
            ->take(self::MAX_SEGMENTOS)
            ->map(function (array $c) {
                if ($c['tipo'] === 'agregado') {
                    return '- Se agregó: ' . Str::limit(trim($c['texto']), 200);
                }
                if ($c['tipo'] === 'eliminado') {
                    return '- Se eliminó: ' . Str::limit(trim($c['texto']), 200);
                }
                $textoNuevo = collect($c['palabras'] ?? [])
                    ->whereIn('tipo', ['igual', 'agregado'])
                    ->pluck('texto')
                    ->implode('');
                return '- Se modificó: ahora dice "' . Str::limit(trim($textoNuevo), 200) . '"';
            })
            ->all();
    }

    /** @return array{data: string, mime: string}|null */
    private function logoBase64(?Empresa $empresa): ?array
    {
        if (! $empresa?->logo_path) {
            return null;
        }

        $rutaAbsoluta = Storage::disk('local')->path($empresa->logo_path);
        if (! is_file($rutaAbsoluta)) {
            return null;
        }

        return [
            'data' => base64_encode(file_get_contents($rutaAbsoluta)),
            'mime' => mime_content_type($rutaAbsoluta) ?: 'image/png',
        ];
    }

    /** @return array{0: string, 1: string} [interaction_id, video_base64] */
    private function crearInteraccion(array $input): array
    {
        return $this->llamarGemini(['input' => $input]);
    }

    /** @return array{0: string, 1: string} [interaction_id, video_base64] */
    private function extenderInteraccion(string $previousInteractionId, string $texto): array
    {
        return $this->llamarGemini([
            'previous_interaction_id' => $previousInteractionId,
            'input' => $texto,
        ]);
    }

    /** @return array{0: string, 1: string} [interaction_id, video_base64] */
    private function llamarGemini(array $camposExtra): array
    {
        $apiKey = config('services.ia.gemini.api_key');
        $modelo = config('services.ia.gemini.model_video');

        $payload = array_merge([
            'model' => $modelo,
            'response_format' => [
                'type' => 'video',
                'resolution' => config('services.ia.gemini.video_resolution'),
            ],
        ], $camposExtra);

        // 180s no alcanzó en producción (cURL error 28, confirmado
        // 2026-09-29 con el log real: "Operation timed out after 180002
        // milliseconds") - la generación de video real tarda más que eso.
        // Corre dentro de un job en cola, no de una petición web, así que
        // un timeout generoso no bloquea a ningún usuario esperando.
        $response = Http::timeout(300)->post(
            "https://generativelanguage.googleapis.com/v1beta/interactions?key={$apiKey}",
            $payload
        );

        if (! $response->successful()) {
            throw new \RuntimeException('Error generando el video didáctico con Gemini: ' . $response->body());
        }

        $data = $response->json();
        $videoBase64 = $this->extraerVideoBase64($data);

        if (! $videoBase64) {
            throw new \RuntimeException('Gemini respondió sin un video en el resultado: ' . $response->body());
        }

        $interactionId = $data['id'] ?? null;
        if (! $interactionId) {
            throw new \RuntimeException('Gemini respondió sin un id de interacción, no se puede extender: ' . $response->body());
        }

        return [$interactionId, $videoBase64];
    }

    /**
     * Extrae el video en base64 de la estructura cruda de /v1beta/interactions -
     * el campo de conveniencia `output_video` solo existe en los SDKs, la REST
     * cruda trae el resultado dentro de `steps[].content[]` (ver docs pegadas
     * por el usuario, 2026-09-28).
     */
    private function extraerVideoBase64(array $respuesta): ?string
    {
        foreach (($respuesta['steps'] ?? []) as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }
            foreach (($step['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'video' && ! empty($content['data'])) {
                    return $content['data'];
                }
            }
        }

        return null;
    }
}
