<?php

namespace App\Services;

use App\Models\BloqueReglamentoInterno;
use App\Models\ReglamentoInterno;
use App\Services\RitDiffService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Genera y mantiene al día los embeddings por bloque (un artículo/parágrafo
 * = un bloque, mismo partidor que RitDiffService) de cada RIT - base para el
 * filtro de similitud contra cambios de Biblioteca Legal. NO es lo mismo que
 * ReglamentoInternoService::generarFragmentosRIT() (esa usa ventanas de ~500
 * palabras para RAG de preguntas libres, esta usa bloques de un artículo
 * para comparación quirúrgica) - ver spec para la justificación completa.
 */
class RitBloqueEmbeddingService
{
    private const EMBEDDING_MODEL = 'gemini-embedding-001';

    /**
     * Regenera los bloques+embeddings del RIT SOLO si el texto cambió desde
     * la última vez (comparando bloques_texto_hash) - evita gastar cuota de
     * Gemini en cada corrida si el RIT no se ha tocado.
     */
    public function asegurarBloques(ReglamentoInterno $rit): void
    {
        if (empty($rit->texto_completo)) {
            return;
        }

        $hashActual = hash('sha256', $rit->texto_completo);
        if ($rit->bloques_texto_hash === $hashActual && $rit->bloques()->exists()) {
            return;
        }

        $rit->bloques()->delete();

        $bloques = RitDiffService::partirEnBloques($rit->texto_completo);
        foreach ($bloques as $orden => $contenido) {
            BloqueReglamentoInterno::create([
                'reglamento_interno_id' => $rit->id,
                'orden'                 => $orden + 1,
                'contenido'             => $contenido,
                'embedding'             => $this->obtenerEmbedding($contenido) ?: null,
            ]);
        }

        $rit->update([
            'bloques_texto_hash'    => $hashActual,
            'bloques_generados_en'  => now(),
        ]);
    }

    private function obtenerEmbedding(string $texto): ?array
    {
        $cacheKey = 'rit_bloque_emb_' . md5($texto);

        return Cache::remember($cacheKey, 86400, function () use ($texto) {
            $apiKey = config('services.ia.gemini.api_key', '');
            $url    = "https://generativelanguage.googleapis.com/v1beta/models/"
                    . self::EMBEDDING_MODEL . ":embedContent?key={$apiKey}";

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post($url, [
                    'model'    => 'models/' . self::EMBEDDING_MODEL,
                    'content'  => ['parts' => [['text' => mb_substr($texto, 0, 8000)]]],
                    'taskType' => 'RETRIEVAL_DOCUMENT',
                ]);

            if (!$response->successful()) {
                Log::warning('RitBloqueEmbeddingService: error generando embedding', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json('embedding.values');
        });
    }
}
