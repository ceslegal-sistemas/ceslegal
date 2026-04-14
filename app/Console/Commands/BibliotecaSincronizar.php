<?php

namespace App\Console\Commands;

use App\Models\DocumentoLegal;
use App\Services\Scrapers\CorteConstitucionalScraper;
use App\Services\Scrapers\CorteSupremaLaboralScraper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BibliotecaSincronizar extends Command
{
    protected $signature = 'biblioteca:sincronizar
                            {--fuente=todas : Fuente a sincronizar: todas|corte-constitucional|corte-suprema}
                            {--desde= : Fecha desde (YYYY-MM-DD). Por defecto: últimos 90 días}
                            {--limite=20 : Máximo de documentos a importar por ejecución}
                            {--dry-run : Solo listar sin guardar}';

    protected $description = 'Sincroniza sentencias legales desde fuentes oficiales colombianas (Corte Constitucional, Corte Suprema Sala Laboral).';

    public function handle(
        CorteConstitucionalScraper $ccScraper,
        CorteSupremaLaboralScraper $csjScraper
    ): int {
        $fuente  = $this->option('fuente');
        $limite  = (int) $this->option('limite');
        $dryRun  = $this->option('dry-run');
        $desde   = $this->option('desde')
            ? Carbon::parse($this->option('desde'))
            : Carbon::now()->subDays(90);

        $this->info("Sincronizando biblioteca legal desde {$desde->format('d/m/Y')}...");
        if ($dryRun) {
            $this->warn('  [dry-run] No se guardarán documentos.');
        }
        $this->newLine();

        $total = 0;

        // ── Corte Constitucional ─────────────────────────────────────────────────
        if (in_array($fuente, ['todas', 'corte-constitucional'])) {
            $this->line('<fg=blue>▶ Corte Constitucional (T, C, SU)</>');

            $sentencias = $ccScraper->obtenerSentencias($desde, $limite);
            $this->line("  Encontradas: " . count($sentencias));

            foreach ($sentencias as $item) {
                if ($this->yaExiste($item['referencia'])) {
                    $this->line("  ⊘ Ya existe: <comment>{$item['referencia']}</comment>");
                    continue;
                }

                $this->line("  + {$item['titulo']}");

                if (!$dryRun) {
                    $this->guardarDesdeTexto($item);
                    $total++;
                }
            }
            $this->newLine();
        }

        // ── Corte Suprema — Sala Laboral ─────────────────────────────────────────
        if (in_array($fuente, ['todas', 'corte-suprema'])) {
            $this->line('<fg=blue>▶ Corte Suprema de Justicia — Sala de Casación Laboral (SL, STL)</>');

            $sentencias = $csjScraper->obtenerSentencias($desde, $limite);
            $this->line("  Encontradas: " . count($sentencias));

            foreach ($sentencias as $item) {
                if ($this->yaExiste($item['referencia'])) {
                    $this->line("  ⊘ Ya existe: <comment>{$item['referencia']}</comment>");
                    continue;
                }

                $this->line("  + {$item['titulo']}");

                if (!$dryRun) {
                    $this->guardarDesdePDF($item);
                    $total++;
                }
            }
            $this->newLine();
        }

        $this->info("Completado. {$total} documento(s) nuevos encolados para procesar.");
        $this->line('  Ejecuta <comment>php artisan biblioteca:procesar --todos</comment> para generar embeddings.');

        return self::SUCCESS;
    }

    private function yaExiste(string $referencia): bool
    {
        return DocumentoLegal::where('referencia', $referencia)->exists();
    }

    /**
     * Guarda un documento cuyo contenido ya es texto extraído (HTML de relatoria CC).
     */
    private function guardarDesdeTexto(array $item): void
    {
        // Guardar texto como TXT en storage
        $nombreArchivo = Str::uuid() . '.txt';
        $ruta          = 'biblioteca-legal/' . $nombreArchivo;

        Storage::disk('public')->put($ruta, $item['texto']);

        DocumentoLegal::create([
            'titulo'                  => $item['titulo'],
            'tipo'                    => $item['tipo'],
            'referencia'              => $item['referencia'],
            'descripcion'             => $item['descripcion'] ?? null,
            'archivo_path'            => $ruta,
            'archivo_nombre_original' => $nombreArchivo,
            'estado'                  => 'pendiente',
            'activo'                  => true,
        ]);
    }

    /**
     * Guarda un documento cuyo contenido es un PDF binario descargado.
     */
    private function guardarDesdePDF(array $item): void
    {
        $nombreArchivo = Str::uuid() . '.pdf';
        $ruta          = 'biblioteca-legal/' . $nombreArchivo;

        Storage::disk('public')->put($ruta, $item['pdf_contenido']);

        DocumentoLegal::create([
            'titulo'                  => $item['titulo'],
            'tipo'                    => $item['tipo'],
            'referencia'              => $item['referencia'],
            'descripcion'             => $item['descripcion'] ?? null,
            'archivo_path'            => $ruta,
            'archivo_nombre_original' => $nombreArchivo,
            'estado'                  => 'pendiente',
            'activo'                  => true,
        ]);
    }
}
