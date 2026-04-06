<?php

namespace App\Console\Commands;

use App\Models\ArticuloLegal;
use App\Models\ReglamentoInterno;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Indexa el Reglamento Interno de Trabajo (RIT) de cada empresa en articulos_legales
 * como artículos empresa-específicos (empresa_id = X, fuente = 'RIT').
 *
 * Es idempotente: elimina chunks anteriores del mismo empresa_id + fuente='RIT'
 * y los regenera para mantener el índice actualizado.
 *
 * Uso:
 *   php artisan rit:indexar                    # Todas las empresas con RIT activo
 *   php artisan rit:indexar --empresa=5        # Solo la empresa con id=5
 */
class IndexarRit extends Command
{
    protected $signature   = 'rit:indexar {--empresa= : ID de empresa específica (opcional)}';
    protected $description = 'Indexa el Reglamento Interno de Trabajo de empresas en articulos_legales para RAG';

    private const CHUNK_SIZE      = 1500; // caracteres por chunk
    private const CHUNK_OVERLAP   = 200;  // solapamiento entre chunks
    private const EMBEDDING_PAUSE = 350;  // ms entre llamadas a Gemini

    public function handle(): int
    {
        $this->info('Indexando Reglamentos Internos de Trabajo (RIT)...');

        $apiKey = config('services.ia.gemini.api_key') ?? config('services.gemini.api_key');

        if (!$apiKey) {
            $this->error('No se encontró GEMINI_API_KEY en la configuración.');
            return self::FAILURE;
        }

        $query = ReglamentoInterno::where('activo', true)
            ->whereNotNull('texto_completo')
            ->where('texto_completo', '!=', '');

        if ($empresaId = $this->option('empresa')) {
            $query->where('empresa_id', (int) $empresaId);
        }

        $reglamentos = $query->with('empresa')->get();

        if ($reglamentos->isEmpty()) {
            $this->warn('No se encontraron reglamentos internos activos con texto.');
            return self::SUCCESS;
        }

        $this->info("Procesando {$reglamentos->count()} reglamento(s)...");

        foreach ($reglamentos as $reglamento) {
            $this->indexarReglamento($reglamento, $apiKey);
        }

        $this->info('Indexación completada.');
        return self::SUCCESS;
    }

    private function indexarReglamento(ReglamentoInterno $reglamento, string $apiKey): void
    {
        $empresaId   = $reglamento->empresa_id;
        $nombreEmpresa = $reglamento->empresa->nombre ?? "Empresa #{$empresaId}";

        $this->line("  Empresa: {$nombreEmpresa} (ID={$empresaId})");

        // Eliminar chunks anteriores para mantener índice limpio
        $eliminados = ArticuloLegal::where('empresa_id', $empresaId)
            ->where('fuente', 'RIT')
            ->delete();

        if ($eliminados > 0) {
            $this->line("    [limpio] {$eliminados} chunk(s) anteriores eliminados");
        }

        $chunks = $this->dividirEnChunks($reglamento->texto_completo);

        if (empty($chunks)) {
            $this->warn("    [skip] El texto del RIT está vacío o es muy corto.");
            return;
        }

        $this->line("    Generando " . count($chunks) . " chunk(s)...");

        foreach ($chunks as $i => $chunk) {
            $orden  = $i + 1;
            $codigo = "RIT-{$empresaId}-{$orden}";

            $embedding = $this->generarEmbedding($chunk, $apiKey);

            ArticuloLegal::create([
                'empresa_id'     => $empresaId,
                'codigo'         => $codigo,
                'titulo'         => "Reglamento Interno — Sección {$orden}",
                'descripcion'    => mb_substr($chunk, 0, 255),
                'texto_completo' => $chunk,
                'categoria'      => 'reglamento_interno',
                'fuente'         => 'RIT',
                'activo'         => true,
                'orden'          => $orden,
                'embedding'      => $embedding,
            ]);

            $status = $embedding ? '[ok]' : '[sin embedding]';
            $this->line("    {$status} {$codigo}");

            usleep(self::EMBEDDING_PAUSE * 1000);
        }

        $this->info("    {$nombreEmpresa}: " . count($chunks) . " chunk(s) indexados.");
    }

    /**
     * Divide el texto en chunks solapados para mejor recuperación RAG.
     */
    private function dividirEnChunks(string $texto): array
    {
        $texto  = trim($texto);
        $length = mb_strlen($texto);

        if ($length <= self::CHUNK_SIZE) {
            return [$texto];
        }

        $chunks = [];
        $offset = 0;

        while ($offset < $length) {
            $chunk = mb_substr($texto, $offset, self::CHUNK_SIZE);

            // Intentar cortar en límite de párrafo o punto
            if ($offset + self::CHUNK_SIZE < $length) {
                $corte = mb_strrpos($chunk, "\n\n");
                if ($corte === false || $corte < self::CHUNK_SIZE * 0.5) {
                    $corte = mb_strrpos($chunk, '. ');
                }
                if ($corte !== false && $corte > self::CHUNK_SIZE * 0.5) {
                    $chunk = mb_substr($chunk, 0, $corte + 1);
                }
            }

            $chunks[] = trim($chunk);
            $chunkLen  = mb_strlen($chunk);
            $offset   += max(1, $chunkLen - self::CHUNK_OVERLAP);
        }

        return array_filter($chunks, fn($c) => mb_strlen(trim($c)) > 50);
    }

    private function generarEmbedding(string $texto, string $apiKey): ?array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}";

        try {
            $response = Http::timeout(15)->post($url, [
                'content'  => ['parts' => [['text' => mb_substr($texto, 0, 8000)]]],
                'taskType' => 'RETRIEVAL_DOCUMENT',
            ]);

            if (!$response->successful()) {
                Log::warning('rit:indexar — embedding fallido', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $values = $response->json('embedding.values');
            return is_array($values) && !empty($values) ? $values : null;
        } catch (\Exception $e) {
            Log::error('rit:indexar — excepción embedding', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
