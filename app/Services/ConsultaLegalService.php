<?php

namespace App\Services;

use App\Models\ArticuloLegal;
use Illuminate\Support\Facades\Log;

/**
 * Recuperación de contenido legal real (RAG) por similitud semántica, para
 * citar en un prompt de IA en vez de dejar que el modelo responda de su
 * conocimiento general. Extraído de EvaluacionHechosService::buscarNormasRelevantes()
 * (el motor de Descargos) para reutilizarse también desde el Asistente Panel
 * (Chatwoot/n8n) - EvaluacionHechosService delega aquí, sin cambiar su
 * comportamiento.
 */
class ConsultaLegalService
{
    public function __construct(protected BibliotecaLegalService $bibliotecaLegal)
    {
    }

    /**
     * Busca artículos legales (universales del CST + propios del RIT de una
     * empresa, según ArticuloLegal::scopeParaEmpresa()) y los enriquece con
     * fragmentos de la Biblioteca Legal, todo por similitud semántica contra
     * $texto.
     *
     * @return string Bloque de texto citable, listo para incluir en un prompt.
     *                 Cadena vacía si no hay coincidencias relevantes o hay error.
     */
    public function buscarContenidoRelevante(string $texto, ?int $empresaId = null, int $limite = 4): string
    {
        try {
            $queryEmbedding = $this->bibliotecaLegal->embedConsulta($texto);
            if (!$queryEmbedding) {
                return '';
            }

            $articulos = ArticuloLegal::whereNotNull('embedding')
                ->activos()
                ->paraEmpresa($empresaId)
                ->get();

            if ($articulos->isEmpty()) {
                return '';
            }

            $scored = [];
            foreach ($articulos as $articulo) {
                $emb = $articulo->embedding; // cast 'array' ya decodifica el JSON
                if (!is_array($emb) || empty($emb)) {
                    continue;
                }
                $scored[] = [
                    'articulo' => $articulo,
                    'score'    => $this->cosineSimilarity($queryEmbedding, $emb),
                ];
            }

            if (empty($scored)) {
                return '';
            }

            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

            // Solo incluir artículos con similitud significativa (>= 0.55).
            $top = array_filter(
                array_slice($scored, 0, $limite),
                fn($s) => $s['score'] >= 0.55
            );

            if (empty($top)) {
                return '';
            }

            $lineas = [];
            foreach ($top as $item) {
                $art      = $item['articulo'];
                $textoArt = $art->getRawOriginal('texto_completo') ?? $art->descripcion ?? '';
                $fuente   = $art->fuente ? " - {$art->fuente}" : '';
                $lineas[] = "[{$art->codigo}{$fuente}]";
                $lineas[] = $art->titulo;
                if ($textoArt) {
                    $lineas[] = mb_substr($textoArt, 0, 600);
                }
                $lineas[] = '';
            }

            $resultado = trim(implode("\n", $lineas));

            // Enriquecer con la Biblioteca Legal (sentencias, doctrina, CST en PDF).
            try {
                $fragmentosBiblioteca = $this->bibliotecaLegal->buscarFragmentos($texto, limite: 4, umbral: 0.60);
                if (!empty($fragmentosBiblioteca)) {
                    $resultado = $resultado
                        ? $resultado . "\n\n" . $fragmentosBiblioteca
                        : $fragmentosBiblioteca;
                }
            } catch (\Throwable $e) {
                Log::warning('ConsultaLegalService: biblioteca RAG error', ['error' => $e->getMessage()]);
            }

            return $resultado;
        } catch (\Throwable $e) {
            Log::warning('ConsultaLegalService::buscarContenidoRelevante', ['error' => $e->getMessage()]);
            return '';
        }
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot  = 0.0;
        $magA = 0.0;
        $magB = 0.0;
        $n    = min(count($a), count($b));

        for ($i = 0; $i < $n; $i++) {
            $dot  += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }

        $denom = sqrt($magA) * sqrt($magB);
        return $denom > 0.0 ? (float) ($dot / $denom) : 0.0;
    }
}
