<?php

namespace App\Services;

use App\Models\BloqueReglamentoInterno;
use App\Models\DocumentoLegal;
use App\Models\ReglamentoInterno;
use App\Support\VectorSearch;
use Illuminate\Support\Collection;
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

    /**
     * Dado un DocumentoLegal recién procesado, devuelve los IDs de empresa
     * cuyo RIT activo tiene al menos un bloque con similitud alta contra
     * algún fragmento del documento - candidatas a revisión con IA (Plan B).
     * Sin llamada generativa: solo compara embeddings ya calculados.
     *
     * @return Collection<int, int> IDs de empresa, sin duplicados
     */
    public function empresasCandidatas(DocumentoLegal $documento, ?float $umbral = null): Collection
    {
        // Umbral en config, no hardcodeado: filtro barato = generoso a propósito
        // (prioriza no descartar nada relevante sobre precisión) - ajustable sin
        // deploy mientras se calibra con datos reales de producción.
        $umbral ??= (float) config('ces.rit_filtro_umbral_similitud', 0.5);

        $fragmentosEmbeddings = $documento->fragmentos()
            ->whereNotNull('embedding')
            ->pluck('embedding');

        if ($fragmentosEmbeddings->isEmpty()) {
            return collect();
        }

        $ritsActivos = ReglamentoInterno::where('activo', true)->get();
        $empresasCandidatas = collect();

        foreach ($ritsActivos as $rit) {
            $this->asegurarBloques($rit);

            // Traer los bloques del RIT UNA sola vez (no por cada fragmento del
            // documento) - el set de bloques no cambia entre fragmentos, así que
            // repetir la consulta ahí adentro era O(RITs × fragmentos) round-trips
            // en vez de O(RITs).
            $bloques = BloqueReglamentoInterno::where('reglamento_interno_id', $rit->id)
                ->whereNotNull('embedding')
                ->pluck('embedding');

            if ($bloques->isEmpty()) {
                continue;
            }

            // Score máximo visto para este RIT, aunque no cruce el umbral - sin
            // esto es imposible saber después si el umbral está bien calibrado
            // (¿los casos que no pasaron estaban lejos, o rozando 0.74?).
            $scoreMaximo = 0.0;
            $huboCoincidencia = false;

            foreach ($fragmentosEmbeddings as $embFragmento) {
                foreach ($bloques as $embBloque) {
                    $score = VectorSearch::cosine($embFragmento, $embBloque);
                    if ($score > $scoreMaximo) {
                        $scoreMaximo = $score;
                    }
                    if ($score >= $umbral) {
                        $huboCoincidencia = true;
                    }
                }
            }

            Log::info('RitBloqueEmbeddingService: score de similitud evaluado', [
                'documento_legal_id' => $documento->id,
                'reglamento_interno_id' => $rit->id,
                'empresa_id' => $rit->empresa_id,
                'score_maximo' => round($scoreMaximo, 4),
                'umbral' => $umbral,
                'paso_filtro' => $huboCoincidencia,
            ]);

            if ($huboCoincidencia) {
                $empresasCandidatas->push($rit->empresa_id);
            }
        }

        return $empresasCandidatas->unique()->values();
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
