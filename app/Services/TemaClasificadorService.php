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
     * Genera un resumen simple y ESPECÍFICO (no la descripción genérica del
     * tema) de lo que el texto REAL de este RIT dice sobre cada uno de sus
     * temas ya clasificados - pensado para un trabajador sin formación
     * jurídica, "para cualquiera", una sola llamada IA para todos los temas
     * a la vez. Se calcula al GUARDAR el RIT (mismo momento que
     * asegurarTemas(), vía ReglamentoInternoObserver), nunca durante el
     * registro del trabajador - así nadie espera a la IA en vivo.
     */
    public function asegurarResumenesSimples(ReglamentoInterno $rit): void
    {
        if (empty($rit->texto_completo)) {
            return;
        }

        $temas = $rit->temasNormativos()->get(['temas_normativos.id', 'temas_normativos.nombre', 'temas_normativos.descripcion']);
        if ($temas->isEmpty()) {
            return;
        }

        $hashActual = hash('sha256', $rit->texto_completo);
        $yaTieneTodos = $temas->every(fn (TemaNormativo $t) => !empty($t->pivot->resumen_simple));
        if ($rit->resumen_simple_texto_hash === $hashActual && $yaTieneTodos) {
            return;
        }

        try {
            $resumenes = $this->generarResumenesSimples($rit->texto_completo, $temas);
        } catch (\Throwable $e) {
            Log::warning('TemaClasificadorService: fallo al generar resumenes simples, se dejan sin resumen', [
                'reglamento_interno_id' => $rit->id,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        foreach ($resumenes as $temaId => $resumen) {
            if ($temas->contains('id', $temaId)) {
                $rit->temasNormativos()->updateExistingPivot($temaId, ['resumen_simple' => $resumen]);
            }
        }

        $rit->forceFill(['resumen_simple_texto_hash' => $hashActual])->saveQuietly();
    }

    /**
     * @param \Illuminate\Support\Collection<int, TemaNormativo> $temas
     * @return array<int, string> tema_id => resumen
     */
    private function generarResumenesSimples(string $texto, \Illuminate\Support\Collection $temas): array
    {
        $listaTemas = $temas->map(fn (TemaNormativo $t) => "- ID {$t->id}: {$t->nombre}")->implode("\n");

        $prompt = <<<PROMPT
        Eres un asistente que explica reglamentos internos de trabajo a
        personas SIN formación jurídica, incluyendo personas con baja
        escolaridad. Dado el texto real de un Reglamento Interno de
        Trabajo, escribe para CADA uno de los siguientes temas un resumen
        de máximo 2 frases cortas, en español muy sencillo, que diga
        ESPECÍFICAMENTE qué dice ESTE reglamento sobre ese tema (números,
        plazos, montos, reglas concretas si el texto los menciona) - NO
        una definición general del tema, sino lo que ESTE documento
        establece. Dirígete al trabajador de forma directa ("tú").

        Responde ÚNICAMENTE con un array JSON, sin markdown, con este
        formato exacto: [{"id": 3, "resumen": "..."}, {"id": 7, "resumen": "..."}]

        TEMAS A RESUMIR:
        {$listaTemas}

        TEXTO DEL REGLAMENTO (puede estar truncado):
        {$this->truncar($texto)}
        PROMPT;

        // 2048: cada resumen son 2 frases x hasta ~13 temas tipicos por RIT -
        // el limite de 1024 usado para la clasificacion (solo IDs) se
        // queda corto para texto real.
        $respuesta = $this->llamarGemini($prompt, 2048);

        $limpio = trim($respuesta);
        $limpio = preg_replace('/^```json\s*|\s*```$/i', '', $limpio) ?? $limpio;
        $decodificado = json_decode($limpio, true);

        if (!is_array($decodificado)) {
            return [];
        }

        $resultado = [];
        foreach ($decodificado as $item) {
            if (isset($item['id'], $item['resumen']) && is_numeric($item['id'])) {
                $resultado[(int) $item['id']] = (string) $item['resumen'];
            }
        }

        return $resultado;
    }

    /**
     * Genera UNA pregunta verdadero/falso por cada tema clasificado, sobre
     * lo que ESTE RIT en particular dice de ese tema (no una definición
     * genérica) - se usa como quiz de comprensión antes de que el
     * trabajador pueda aceptar el reglamento (ver SocializacionRit,
     * pedido "excéntrico" del usuario 2026-09-23). Mismo patrón de cacheo
     * por hash que asegurarResumenesSimples() - reutiliza la MISMA columna
     * resumen_simple_texto_hash (no una columna de hash nueva: la pregunta
     * es parte del mismo paquete de contenido derivado del texto del RIT).
     */
    public function asegurarPreguntasQuiz(ReglamentoInterno $rit): void
    {
        if (empty($rit->texto_completo)) {
            return;
        }

        $temas = $rit->temasNormativos()->get(['temas_normativos.id', 'temas_normativos.nombre', 'temas_normativos.descripcion']);
        if ($temas->isEmpty()) {
            return;
        }

        $hashActual = hash('sha256', $rit->texto_completo);
        $yaTieneTodas = $temas->every(fn (TemaNormativo $t) => !empty($t->pivot->pregunta_vf));
        if ($rit->resumen_simple_texto_hash === $hashActual && $yaTieneTodas) {
            return;
        }

        try {
            $preguntas = $this->generarPreguntasQuiz($rit->texto_completo, $temas);
        } catch (\Throwable $e) {
            Log::warning('TemaClasificadorService: fallo al generar preguntas de quiz, se dejan sin pregunta', [
                'reglamento_interno_id' => $rit->id,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        foreach ($preguntas as $temaId => $pregunta) {
            if ($temas->contains('id', $temaId)) {
                $rit->temasNormativos()->updateExistingPivot($temaId, [
                    'pregunta_vf' => $pregunta['pregunta'],
                    'respuesta_correcta' => $pregunta['respuesta'],
                ]);
            }
        }

        $rit->forceFill(['resumen_simple_texto_hash' => $hashActual])->saveQuietly();
    }

    /**
     * @param \Illuminate\Support\Collection<int, TemaNormativo> $temas
     * @return array<int, array{pregunta: string, respuesta: bool}> tema_id => pregunta
     */
    private function generarPreguntasQuiz(string $texto, \Illuminate\Support\Collection $temas): array
    {
        $listaTemas = $temas->map(fn (TemaNormativo $t) => "- ID {$t->id}: {$t->nombre}")->implode("\n");

        $prompt = <<<PROMPT
        Eres un asistente que verifica si una persona SIN formación
        jurídica entendió un Reglamento Interno de Trabajo. Dado el texto
        real de un Reglamento Interno de Trabajo, escribe para CADA uno de
        los siguientes temas UNA pregunta de verdadero o falso, en español
        muy sencillo, sobre algo ESPECÍFICO que ESTE reglamento dice sobre
        ese tema (un número, un plazo, una regla concreta) - NO una
        pregunta de cultura general sobre el tema. La pregunta debe tener
        una respuesta objetivamente verificable en el texto. Dirígete al
        trabajador de forma directa ("tú").

        Responde ÚNICAMENTE con un array JSON, sin markdown, con este
        formato exacto: [{"id": 3, "pregunta": "...", "respuesta": true}, {"id": 7, "pregunta": "...", "respuesta": false}]

        TEMAS:
        {$listaTemas}

        TEXTO DEL REGLAMENTO (puede estar truncado):
        {$this->truncar($texto)}
        PROMPT;

        $respuesta = $this->llamarGemini($prompt, 2048);

        $limpio = trim($respuesta);
        $limpio = preg_replace('/^```json\s*|\s*```$/i', '', $limpio) ?? $limpio;
        $decodificado = json_decode($limpio, true);

        if (!is_array($decodificado)) {
            return [];
        }

        $resultado = [];
        foreach ($decodificado as $item) {
            if (isset($item['id'], $item['pregunta'], $item['respuesta']) && is_numeric($item['id'])) {
                $resultado[(int) $item['id']] = [
                    'pregunta' => (string) $item['pregunta'],
                    'respuesta' => (bool) $item['respuesta'],
                ];
            }
        }

        return $resultado;
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
        son tratados de forma SUSTANCIAL en el contenido (el texto les
        dedica una sección, capítulo, artículo o párrafo de desarrollo
        propio) - NO incluyas un tema solo porque se lo menciona de paso,
        como contexto legal secundario, o como referencia incidental
        dentro del razonamiento de otro tema. Sé selectivo: un documento
        angosto (ej. una sentencia sobre un solo problema jurídico) debe
        arrojar pocos temas, no todos los que aparecen mencionados en su
        texto. Un reglamento interno completo sí puede arrojar muchos
        temas, porque legítimamente dedica un capítulo a cada uno.

        Puede ser ninguno, uno, o varios. No inventes temas que no estén
        en la lista. Responde ÚNICAMENTE con un array JSON de los IDs
        numéricos aplicables, sin texto adicional, sin markdown. Ejemplo
        de respuesta válida: [3, 7, 12]

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
                'temperature'     => 0.1,
                'maxOutputTokens' => $maxOutputTokens,
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
