<?php

namespace App\Services;

use App\Models\Jurisprudencia;
use Illuminate\Support\Facades\Log;

/**
 * Búsqueda semántica de jurisprudencia curada (Corte Constitucional/Suprema)
 * relevante para un caso. Antes esta lógica vivía solo dentro de
 * IAAnalisisSancionService::obtenerContextoJurisprudencia() - se extrae aquí
 * para que otros servicios (impugnaciones, descargos, auditoría RIT, etc.)
 * puedan reutilizarla sin reimplementar embeddings + similitud coseno.
 */
class JurisprudenciaService
{
    /**
     * Busca jurisprudencia relevante para un texto de consulta libre y la
     * devuelve como bloque de texto listo para insertar en un prompt (o ''
     * si no hay nada suficientemente relevante). Nunca lanza excepción - un
     * fallo de búsqueda no debe tumbar el flujo que la usa.
     */
    public function buscarContexto(string $consulta, int $limite = 3, float $umbral = 0.45): string
    {
        try {
            $sentencias = Jurisprudencia::query()
                ->activas()->procesadas()
                ->whereNotNull('embedding')
                ->get(['referencia', 'tema', 'tesis', 'fecha', 'magistrado', 'embedding']);

            if ($sentencias->isEmpty()) {
                return '';
            }

            $queryEmb = app(BibliotecaLegalService::class)->embedConsulta(trim($consulta));
            if (empty($queryEmb)) {
                return '';
            }

            $scored = $sentencias
                ->map(function ($s) use ($queryEmb) {
                    $emb = $s->embedding;
                    if (empty($emb)) {
                        return null;
                    }
                    return ['s' => $s, 'score' => $this->cosenoSimilitud($queryEmb, $emb)];
                })
                ->filter(fn($x) => $x !== null && $x['score'] >= $umbral)
                ->sortByDesc('score')
                ->take($limite)
                ->values();

            if ($scored->isEmpty()) {
                return '';
            }

            return $scored->map(function ($x) {
                $s    = $x['s'];
                $meta = trim(($s->fecha?->toDateString() ? $s->fecha->toDateString() . '; ' : '') . ($s->magistrado ? 'M.P. ' . $s->magistrado : ''));
                $head = "--- Sentencia {$s->referencia}" . ($meta ? " ({$meta})" : '') . " ---";
                $tema = $s->tema ? "Tema: {$s->tema}\n" : '';
                return "{$head}\n{$tema}Tesis: {$s->tesis}";
            })->implode("\n\n");

        } catch (\Throwable $e) {
            Log::warning('JurisprudenciaService: no se pudo buscar jurisprudencia relevante', [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /** Similitud coseno entre dos vectores. */
    private function cosenoSimilitud(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = $na = $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] * $a[$i];
            $nb  += $b[$i] * $b[$i];
        }
        if ($na <= 0 || $nb <= 0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }
}
