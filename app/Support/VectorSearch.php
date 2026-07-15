<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda semántica (cosine similarity) centralizada para MySQL, pensada para escalar.
 *
 * Estrategia:
 *  - Fase 1 (puntuación): recorre los candidatos cargando SOLO [clave, embedding] con un
 *    cursor perezoso → memoria mínima (un embedding a la vez, no toda la tabla en RAM).
 *  - Fase 2 (hidratación): el llamador trae los registros completos SOLO para el top-K.
 *
 * Ventajas vs. el patrón anterior (cargar toda la tabla con texto + embedding en memoria):
 *  - No carga el texto completo de miles de filas solo para rankear.
 *  - El pico de memoria no crece con el tamaño del corpus.
 *  - Un pre-filtro por categoría/tipo se pasa como Builder ya acotado.
 *
 * Cuando el corpus crezca a millones de vectores, se sustituye topK() por un motor vectorial
 * gestionado (Qdrant/Pinecone) SIN tocar a los llamadores (misma interfaz de entrada/salida).
 */
class VectorSearch
{
    /**
     * Devuelve el top-K por similitud coseno.
     *
     * @param  Builder  $query            Query ya acotada (activos, pre-filtros, etc.).
     * @param  float[]  $queryEmb         Embedding de la consulta.
     * @param  int      $k                Máximo de resultados.
     * @param  float    $umbral           Score mínimo [0..1].
     * @param  string   $embeddingColumn  Columna con el vector (cast 'array').
     * @param  string   $keyColumn        Clave primaria a devolver.
     * @return array<int, array{key: mixed, score: float}>  Ordenado desc por score.
     */
    public static function topK(
        Builder $query,
        array   $queryEmb,
        int     $k = 8,
        float   $umbral = 0.30,
        string  $embeddingColumn = 'embedding',
        string  $keyColumn = 'id',
    ): array {
        if (empty($queryEmb) || $k < 1) {
            return [];
        }

        $scored = [];
        foreach ($query->select([$keyColumn, $embeddingColumn])->cursor() as $row) {
            $emb = $row->{$embeddingColumn};
            if (empty($emb) || !is_array($emb)) {
                continue;
            }
            $score = self::cosine($queryEmb, $emb);
            if ($score >= $umbral) {
                $scored[] = ['key' => $row->{$keyColumn}, 'score' => $score];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $k);
    }

    /** Similitud coseno entre dos vectores (implementación única del proyecto). */
    public static function cosine(array $a, array $b): float
    {
        $dot = $magA = $magB = 0.0;
        $n   = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot  += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }
        $denom = sqrt($magA) * sqrt($magB);
        return $denom > 0.0 ? $dot / $denom : 0.0;
    }
}
