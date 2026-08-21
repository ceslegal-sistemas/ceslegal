<?php

namespace App\Services;

use App\Models\BloqueReglamentoInterno;
use App\Models\ReglamentoInterno;
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
        $conExito = 0;
        foreach ($bloques as $orden => $contenido) {
            $embedding = $this->obtenerEmbedding($contenido, $rit->id, $orden + 1);
            if ($embedding) {
                $conExito++;
            }

            BloqueReglamentoInterno::create([
                'reglamento_interno_id' => $rit->id,
                'orden'                 => $orden + 1,
                'contenido'             => $contenido,
                'embedding'             => $embedding ?: null,
            ]);
        }

        // Si NINGÚN bloque logró embedding (falla sistémica: API key mala,
        // cuota agotada, 429/timeout) no se marca el hash como "al día" - de
        // lo contrario esta corrida queda permanentemente atascada con
        // embeddings en null, sin ningún reintento futuro, hasta que el
        // texto del RIT vuelva a cambiar. Con al menos 1 bloque exitoso sí
        // se marca (evita reintentar toda la cuota si solo falló uno o dos).
        if ($conExito === 0 && count($bloques) > 0) {
            Log::warning('RitBloqueEmbeddingService: ningún bloque obtuvo embedding, hash NO marcado como al día (se reintentará en la próxima corrida)', [
                'reglamento_interno_id' => $rit->id,
                'total_bloques' => count($bloques),
            ]);
            return;
        }

        $rit->update([
            'bloques_texto_hash'    => $hashActual,
            'bloques_generados_en'  => now(),
        ]);
    }

    private function obtenerEmbedding(string $texto, int $ritId, int $orden): ?array
    {
        $cacheKey = 'rit_bloque_emb_' . md5($texto);

        return Cache::remember($cacheKey, 86400, function () use ($texto, $ritId, $orden) {
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
                    'reglamento_interno_id' => $ritId,
                    'orden' => $orden,
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json('embedding.values');
        });
    }
}
