<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Micro-benchmark de capacidad del servidor SIN tocar Gemini ni datos reales.
 *
 * Mide los dos puntos pesados de la app de forma aislada:
 *   - Generación de PDF (DomPDF) -> CPU/memoria por documento.
 *   - Escritura/lectura en MySQL -> dado que cola, caché y sesión usan la BD.
 *
 * Da números por PROCESO (un core). Multiplica por los procesos/workers reales
 * para estimar la capacidad total del plan.
 *
 * Uso:
 *   php artisan stress:benchmark --pdf=50
 *   php artisan stress:benchmark --db=2000
 *   php artisan stress:benchmark --pdf=30 --db=1000
 */
class StressBenchmark extends Command
{
    protected $signature = 'stress:benchmark
        {--pdf=0 : Cantidad de PDFs a renderizar}
        {--db=0  : Cantidad de operaciones insert+select+delete en MySQL}';

    protected $description = 'Mide throughput de PDF y de MySQL del servidor (sin IA, sin costo)';

    public function handle(): int
    {
        $this->warn('Benchmark de capacidad - NO usa Gemini, NO toca datos reales.');
        $this->line('Servidor PHP ' . PHP_VERSION . ' · memory_limit=' . ini_get('memory_limit'));
        $this->newLine();

        $pdf = (int) $this->option('pdf');
        $db  = (int) $this->option('db');

        if ($pdf <= 0 && $db <= 0) {
            $this->error('Indica al menos --pdf=N o --db=N. Ej: php artisan stress:benchmark --pdf=50 --db=2000');
            return self::INVALID;
        }

        if ($pdf > 0) {
            $this->benchPdf($pdf);
        }

        if ($db > 0) {
            $this->benchDb($db);
        }

        $this->newLine();
        $this->info('Listo. Multiplica el throughput por los workers/procesos reales del plan para estimar el total.');
        $this->comment('Recuerda: la IA (Gemini) es un techo aparte - esto solo mide TU servidor.');

        return self::SUCCESS;
    }

    private function benchPdf(int $n): void
    {
        $this->line("── PDF: renderizando {$n} documentos ──");

        // HTML representativo: ~1 página con tabla y texto (similar a una carta/acta).
        $html = $this->htmlMuestra();

        $tiempos = [];
        $bar = $this->output->createProgressBar($n);
        $bar->start();

        $inicio = microtime(true);
        $fallos = 0;
        for ($i = 0; $i < $n; $i++) {
            $t0 = microtime(true);
            try {
                $pdf = Pdf::loadHTML($html);
                $contenido = $pdf->output();      // fuerza el render completo
                unset($pdf, $contenido);
            } catch (\Throwable $e) {
                $fallos++;
            }
            $tiempos[] = (microtime(true) - $t0) * 1000;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $total = microtime(true) - $inicio;
        $this->reportar('PDF', $n, $total, $tiempos, $fallos);
        $this->line('  Memoria pico: ' . $this->mb(memory_get_peak_usage(true)) . ' MB');
        $this->newLine();
    }

    private function benchDb(int $n): void
    {
        $this->line("── MySQL: {$n} ciclos insert+select+delete ──");

        $tabla = 'stress_benchmark_tmp';
        Schema::dropIfExists($tabla);
        Schema::create($tabla, function ($t) {
            $t->bigIncrements('id');
            $t->string('clave', 64)->index();
            $t->text('valor');
            $t->timestamps();
        });

        $tiempos = [];
        $bar = $this->output->createProgressBar($n);
        $bar->start();

        $inicio = microtime(true);
        $fallos = 0;
        for ($i = 0; $i < $n; $i++) {
            $t0 = microtime(true);
            try {
                $id = DB::table($tabla)->insertGetId([
                    'clave'      => 'k' . $i,
                    'valor'      => str_repeat('x', 256),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table($tabla)->where('id', $id)->first();
                DB::table($tabla)->where('id', $id)->delete();
            } catch (\Throwable $e) {
                $fallos++;
            }
            $tiempos[] = (microtime(true) - $t0) * 1000;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $total = microtime(true) - $inicio;
        Schema::dropIfExists($tabla);

        $this->reportar('MySQL (ciclo)', $n, $total, $tiempos, $fallos);
        $this->newLine();
    }

    private function reportar(string $titulo, int $n, float $totalSeg, array $tiempos, int $fallos): void
    {
        sort($tiempos);
        $p = fn(float $q) => $tiempos[(int) floor(($n - 1) * $q)] ?? 0;
        $rate = $totalSeg > 0 ? $n / $totalSeg : 0;

        $this->table(
            ['Métrica', $titulo],
            [
                ['Operaciones',        $n],
                ['Fallos',             $fallos],
                ['Tiempo total',       number_format($totalSeg, 2) . ' s'],
                ['Throughput',         number_format($rate, 1) . ' /s'],
                ['Latencia media',     number_format(array_sum($tiempos) / max(1, $n), 1) . ' ms'],
                ['Latencia p50',       number_format($p(0.50), 1) . ' ms'],
                ['Latencia p95',       number_format($p(0.95), 1) . ' ms'],
                ['Latencia p99',       number_format($p(0.99), 1) . ' ms'],
            ]
        );
    }

    private function mb(int $bytes): string
    {
        return number_format($bytes / 1048576, 1);
    }

    private function htmlMuestra(): string
    {
        $filas = '';
        for ($i = 1; $i <= 20; $i++) {
            $filas .= "<tr><td>{$i}</td><td>Concepto disciplinario número {$i}</td>"
                . "<td>Artículo 115 CST</td><td>Observación de prueba para medir render</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
  body{font-family:DejaVu Sans, sans-serif;font-size:12px;color:#222;}
  h1{font-size:18px;} table{width:100%;border-collapse:collapse;margin-top:10px;}
  td,th{border:1px solid #999;padding:4px 6px;} th{background:#eee;}
</style></head><body>
<h1>Documento de prueba - Benchmark</h1>
<p>Este documento simula una carta/acta de una página con tabla y texto para medir
el tiempo de render de DomPDF en el servidor. No tiene datos reales.</p>
<table><thead><tr><th>#</th><th>Concepto</th><th>Base legal</th><th>Observación</th></tr></thead>
<tbody>{$filas}</tbody></table>
<p>Fin del documento de prueba.</p>
</body></html>
HTML;
    }
}
