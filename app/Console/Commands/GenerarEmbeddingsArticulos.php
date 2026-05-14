<?php

namespace App\Console\Commands;

use App\Models\ArticuloLegal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerarEmbeddingsArticulos extends Command
{
    protected $signature = 'articulos:generar-embeddings
                            {--force : Regenerar embeddings aunque ya existan}';

    protected $description = 'Genera embeddings vectoriales para los artículos legales usando Gemini gemini-embedding-001';

    public function handle(): int
    {
        $apiKey = config('services.ia.gemini.api_key') ?? config('services.gemini.api_key');
        if (!$apiKey) {
            $this->error('GEMINI_API_KEY no está configurada en .env');
            return self::FAILURE;
        }

        $query = ArticuloLegal::query();
        if (!$this->option('force')) {
            $query->whereNull('embedding');
        }

        $articulos = $query->get();

        if ($articulos->isEmpty()) {
            $this->info('Todos los artículos ya tienen embedding. Use --force para regenerar.');
            return self::SUCCESS;
        }

        $this->info("Generando embeddings para {$articulos->count()} artículos...");
        $bar = $this->output->createProgressBar($articulos->count());
        $bar->start();

        $errores = 0;
        foreach ($articulos as $articulo) {
            $texto = $this->buildTextoParaEmbedding($articulo);

            try {
                $embedding = $this->obtenerEmbedding($texto, $apiKey);
                $articulo->update(['embedding' => $embedding]);
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("Error en [{$articulo->codigo}]: {$e->getMessage()}");
                Log::warning('GenerarEmbeddingsArticulos: error', [
                    'codigo' => $articulo->codigo,
                    'error'  => $e->getMessage(),
                ]);
                $errores++;
            }

            $bar->advance();
            // Pausa para no saturar la API (free tier: 1500 req/min)
            usleep(100_000); // 100ms
        }

        $bar->finish();
        $this->newLine();

        $ok = $articulos->count() - $errores;
        $this->info("✅ Embeddings generados: {$ok} / {$articulos->count()}");
        if ($errores > 0) {
            $this->warn("⚠️  Errores: {$errores}. Vuelva a ejecutar el comando.");
        }

        return $errores === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function buildTextoParaEmbedding(ArticuloLegal $articulo): string
    {
        // Combinamos código + título + texto completo para un embedding rico en contexto
        $partes = [
            $articulo->codigo,
            $articulo->fuente ?? '',
            $articulo->titulo,
            $articulo->getRawOriginal('texto_completo') ?? $articulo->descripcion,
        ];

        return implode('. ', array_filter($partes));
    }

    private function obtenerEmbedding(string $texto, string $apiKey): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}";

        $response = Http::timeout(15)->post($url, [
            'content' => [
                'parts' => [['text' => $texto]],
            ],
            'taskType' => 'RETRIEVAL_DOCUMENT',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Gemini API error {$response->status()}: {$response->body()}");
        }

        $values = $response->json('embedding.values');
        if (!is_array($values) || empty($values)) {
            throw new \RuntimeException('Respuesta de embedding vacía o inválida');
        }

        return $values;
    }
}
