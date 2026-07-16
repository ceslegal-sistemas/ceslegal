<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Estima cuántos descargos simultáneos aguanta el servidor.
 *
 * Mide la LATENCIA REAL de la llamada a Gemini que cada envío de respuesta dispara
 * (las preguntas dinámicas corren con dispatchAfterResponse, ocupando el proceso
 * PHP ~esa latencia). Con esa T, el límite de procesos del plan (--procesos) y la
 * cadencia de envío por trabajador (--cadencia), calcula los descargos simultáneos:
 *
 *     N ≈ procesos × cadencia / latencia
 *
 * Uso (en el servidor):
 *   php artisan descargos:capacidad --n=12 --procesos=20 --cadencia=90
 *
 * --procesos: tope de procesos PHP concurrentes del plan (hPanel → Uso de recursos).
 * --cadencia: segundos promedio entre envíos de un mismo trabajador (lee + escribe).
 */
class CapacidadDescargos extends Command
{
    protected $signature = 'descargos:capacidad
        {--n=12 : Número de llamadas IA a cronometrar}
        {--procesos=20 : Tope de procesos PHP concurrentes del plan}
        {--cadencia=90 : Segundos promedio entre envíos de un trabajador}';

    protected $description = 'Estima descargos simultáneos midiendo la latencia real de Gemini por envío';

    public function handle(): int
    {
        $config = config('services.ia.gemini', []);
        $apiKey = $config['api_key'] ?? null;
        if (empty($apiKey)) {
            $this->error('Falta la API key de Gemini (services.ia.gemini.api_key).');
            return self::FAILURE;
        }

        $n = max(1, (int) $this->option('n'));
        $procesos = max(1, (int) $this->option('procesos'));
        $cadencia = max(1, (int) $this->option('cadencia'));
        $modelo = $config['model'] ?? 'gemini-2.5-flash';

        $this->warn("Midiendo latencia real de {$n} llamadas a Gemini ({$modelo}) - simula la carga de un envío de descargos.");
        $this->line('Esto hace llamadas reales (cuesta unos centavos). No persiste datos.');
        $this->newLine();

        $prompt = $this->promptRepresentativo();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

        $tiempos = [];
        $fallos = 0;
        $bar = $this->output->createProgressBar($n);
        $bar->start();

        for ($i = 0; $i < $n; $i++) {
            $t0 = microtime(true);
            try {
                $resp = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(90)
                    ->post($url, [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 512, 'topP' => 0.95],
                    ]);
                if (! $resp->successful()) {
                    $fallos++;
                }
            } catch (\Throwable $e) {
                $fallos++;
            }
            $tiempos[] = (microtime(true) - $t0) * 1000;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        sort($tiempos);
        $p = fn(float $q) => $tiempos[(int) floor(($n - 1) * $q)] ?? 0;
        $avg = array_sum($tiempos) / max(1, $n);
        $p50 = $p(0.50);
        $p95 = $p(0.95);

        $this->info('LATENCIA POR ENVÍO (llamada a Gemini)');
        $this->table(['Métrica', 'Valor'], [
            ['Llamadas', $n],
            ['Fallos', $fallos],
            ['Media', number_format($avg, 0) . ' ms'],
            ['p50', number_format($p50, 0) . ' ms'],
            ['p95', number_format($p95, 0) . ' ms (la usamos para el cálculo, conservador)'],
        ]);

        // Estimación: N ≈ procesos × cadencia / latencia (en segundos)
        $latSeg = max(0.1, $p95 / 1000);
        $estimado = (int) floor($procesos * $cadencia / $latSeg);

        $this->newLine();
        $this->info('ESTIMADO DE DESCARGOS SIMULTÁNEOS');
        $this->line("Fórmula: procesos ({$procesos}) × cadencia ({$cadencia}s) / latencia p95 (" . number_format($latSeg, 1) . "s)");
        $this->table(['Escenario (cadencia entre envíos)', 'Descargos simultáneos ≈'], [
            ['Intenso (45s entre envíos)',  (int) floor($procesos * 45  / $latSeg)],
            ['Normal (90s entre envíos)',   (int) floor($procesos * 90  / $latSeg)],
            ['Pausado (150s entre envíos)', (int) floor($procesos * 150 / $latSeg)],
            ['→ Con tu --cadencia=' . $cadencia . 's', $estimado],
        ]);

        $this->newLine();
        $this->comment('OJO ráfaga: si más de ~' . $procesos . ' trabajadores ENVÍAN respuesta en la misma ventana de ' . number_format($latSeg, 0) . 's, se llena el límite de procesos (algunos 503).');
        $this->comment('Para subir el techo: mover las preguntas dinámicas de dispatchAfterResponse() a una cola real (queue:work).');

        return self::SUCCESS;
    }

    /** Prompt de tamaño representativo de una generación de preguntas de descargos. */
    private function promptRepresentativo(): string
    {
        $contexto = str_repeat(
            'El trabajador se desempeña como operario. Los hechos presuntos describen una posible falta '
            . 'al reglamento interno relacionada con el cumplimiento de funciones, procedimientos de seguridad '
            . 'y reporte oportuno a su superior conforme al Código Sustantivo del Trabajo y la jurisprudencia aplicable. ',
            12
        );

        return <<<PROMPT
Eres un abogado laboral colombiano conduciendo una diligencia de descargos con enfoque garantista.
CONTEXTO DEL CASO:
{$contexto}

El trabajador ya declaró brevemente su versión. Con base en el debido proceso (Art. 29 C.P., Art. 115 CST),
genera EXACTAMENTE 2 preguntas nuevas, abiertas, neutrales y breves, pertinentes a los hechos, sin repetir
aspectos ya cubiertos ni preguntar por las funciones del cargo (esas las conoces). Formato:
PREGUNTA_1: [texto]
PREGUNTA_2: [texto]
PROMPT;
    }
}
