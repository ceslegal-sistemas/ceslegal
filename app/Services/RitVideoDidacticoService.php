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
 * Reglamento (no por trabajador) - los capítulos quedan guardados en el
 * propio registro del RIT y todos los trabajadores que lo socialicen ven
 * los mismos archivos. Costo real confirmado 2026-09-29 por el usuario en
 * https://ai.studio/spend: ~1.170 COP por clip de ~10s a 360p.
 *
 * REDISEÑO 2026-09-30 (capítulos independientes, no encadenados): la
 * primera versión encadenaba clips con "extend" (previous_interaction_id)
 * para simular un solo video largo, pero Gemini tiene un límite DURO de
 * ~30-40s totales por video extendido - error real de producción, "Videos
 * longer than 30s are not supported for extension." Eso topaba la
 * cobertura a 3-4 temas de los hasta 27 que puede tener un RIT. La
 * solución: en vez de UN video largo, se genera UN CLIP INDEPENDIENTE por
 * cada tema (o cambio) - cada llamada es una interacción nueva de Gemini
 * (sin previous_interaction_id), así que el límite de 30-40s no aplica
 * nunca, sin importar cuántos capítulos tenga el RIT. El trabajador los ve
 * como una serie con reproducción automática en secuencia (ver
 * `socializacion-rit.blade.php`). Efecto secundario positivo: el logo de
 * la empresa se adjunta de nuevo en CADA capítulo (no solo en el primero),
 * así que se mantiene consistente sin depender de que el modelo "recuerde"
 * un clip anterior.
 */
class RitVideoDidacticoService
{
    /**
     * @param array $cambios Diff de RitDiffService::compararDocumentos() (opcional) -
     *   si se pasa, el video se enfoca en "qué cambió" en vez de temas genéricos.
     */
    public function generar(ReglamentoInterno $rit, array $cambios = []): void
    {
        $empresa = $rit->empresa;

        $cambiosReales = array_values(array_filter($cambios, fn ($c) => $c['tipo'] !== 'igual'));

        $capitulos = !empty($cambiosReales)
            ? $this->capitulosDeCambios($cambiosReales)
            : $this->capitulosDeTemas($rit);

        if (empty($capitulos)) {
            throw new \RuntimeException('El Reglamento no tiene temas clasificados ni cambios para explicar en el video.');
        }

        $logoBase64 = $this->logoBase64($empresa);
        $generados = [];

        foreach (array_values($capitulos) as $indice => $capitulo) {
            $input = [];
            if ($logoBase64) {
                $input[] = ['type' => 'image', 'data' => $logoBase64['data'], 'mime_type' => $logoBase64['mime']];
            }
            $input[] = [
                'type' => 'text',
                'text' => $this->promptCapitulo($empresa, $capitulo['texto'], $indice === 0, (bool) $logoBase64),
            ];

            [, $videoBase64] = $this->crearInteraccion($input);

            $ruta = "rit-videos/{$rit->empresa_id}/video_{$rit->id}_cap" . ($indice + 1) . '_' . Str::random(8) . '.mp4';
            Storage::disk('local')->put($ruta, base64_decode($videoBase64));

            $generados[] = ['titulo' => $capitulo['titulo'], 'path' => $ruta];
        }

        $rit->update([
            'video_didactico_path' => null,
            'video_didactico_capitulos' => $generados,
            'video_didactico_estado' => 'completado',
            'video_didactico_error' => null,
            'video_didactico_generado_en' => now(),
        ]);
    }

    /**
     * Cada capítulo es un clip INDEPENDIENTE (no una extensión) - por eso
     * solo el primero se presenta como apertura de la serie; los siguientes
     * van directo al contenido, sin repetir "bienvenidos". El recordatorio
     * de logo y de no mostrar texto en pantalla se repite en TODOS los
     * capítulos porque cada uno es una petición nueva a Gemini.
     */
    /**
     * Prompt reforzado (2026-09-30): en producción, algunos capítulos SÍ
     * mostraron subtítulos con letras deformadas pese a la instrucción
     * original, y el logo no siempre se mantuvo idéntico entre capítulos -
     * el usuario exigió que esto no vuelva a pasar "por nada en el mundo".
     * Gemini no garantiza obediencia perfecta a ninguna instrucción de
     * prompt (esto sigue siendo un modelo generativo probabilístico, no
     * hay forma de "forzar" el resultado al 100%), pero se repite la
     * prohibición en varias formas explícitas para maximizar la
     * probabilidad de cumplimiento.
     */
    private function promptCapitulo(?Empresa $empresa, string $contenido, bool $esPrimero, bool $conLogo): string
    {
        $prompt = $esPrimero
            ? "Video didáctico y profesional en español, para explicarle a un trabajador colombiano el Reglamento Interno de Trabajo de la empresa \"{$empresa?->nombre_completo}\". "
            : "Video didáctico y profesional en español, uno de varios capítulos cortos que explican el Reglamento Interno de Trabajo de la empresa \"{$empresa?->nombre_completo}\". ";

        $prompt .= "Un presentador habla directamente a cámara, con tono cercano y claro, sin lenguaje jurídico complicado. "
            . "PROHIBIDO POR COMPLETO: no muestres NINGÚN texto, palabra, letra, número, viñeta, subtítulo, caption ni rótulo escrito en pantalla en NINGÚN momento del video, bajo ninguna circunstancia - ni siquiera fragmentos, ni siquiera borrosos. "
            . "El video debe ser 100% imagen del presentador hablando, SOLO narración hablada, cero texto en pantalla. "
            . "Explica en voz este punto:\n{$contenido}\n"
            . "Narración en español neutro colombiano. No incluya música con derechos de autor reconocibles.";

        if ($conLogo) {
            $prompt = "El video muestra el logo de la empresa <IMAGE_REF_0> en una esquina fija durante todo el clip, EXACTAMENTE igual a la imagen de referencia - mismo tamaño, misma posición, mismos colores, sin ninguna variación, distorsión ni animación del logo. " . $prompt;
        }

        return $prompt;
    }

    /**
     * Un capítulo por cada tema YA clasificado del RIT (la misma lista que
     * se muestra en "Temas que cubre su Reglamento") - sin tope artificial,
     * ver docblock de la clase.
     *
     * @return array<int, array{titulo: string, texto: string}>
     */
    private function capitulosDeTemas(ReglamentoInterno $rit): array
    {
        return $rit->temasNormativos()
            ->activos()
            ->get(['temas_normativos.id', 'temas_normativos.nombre', 'temas_normativos.descripcion'])
            ->map(fn ($tema) => [
                'titulo' => $tema->nombre,
                'texto' => $tema->nombre . ': ' . ($tema->pivot->resumen_simple ?: $tema->descripcion),
            ])
            ->all();
    }

    /**
     * Reconstruye, en lenguaje humano, cada cambio real de un diff de
     * RitDiffService::compararDocumentos() - un bloque "modificado" trae un
     * diff de PALABRAS (no un texto plano), así que se reconstruye el texto
     * nuevo (palabras 'igual' + 'agregado', descartando las 'eliminado').
     * Un capítulo por cada cambio real, sin tope artificial.
     *
     * @return array<int, array{titulo: string, texto: string}>
     */
    private function capitulosDeCambios(array $cambios): array
    {
        return collect($cambios)
            ->values()
            ->map(function (array $c, int $i) {
                $titulo = 'Actualización ' . ($i + 1);

                if ($c['tipo'] === 'agregado') {
                    return ['titulo' => $titulo, 'texto' => 'Se agregó: ' . Str::limit(trim($c['texto']), 200)];
                }
                if ($c['tipo'] === 'eliminado') {
                    return ['titulo' => $titulo, 'texto' => 'Se eliminó: ' . Str::limit(trim($c['texto']), 200)];
                }
                $textoNuevo = collect($c['palabras'] ?? [])
                    ->whereIn('tipo', ['igual', 'agregado'])
                    ->pluck('texto')
                    ->implode('');
                return ['titulo' => $titulo, 'texto' => 'Se modificó: ahora dice "' . Str::limit(trim($textoNuevo), 200) . '"'];
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
            throw new \RuntimeException('Gemini respondió sin un id de interacción: ' . $response->body());
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
