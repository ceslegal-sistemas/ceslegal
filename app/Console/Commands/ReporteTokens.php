<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Reporte de tokens y costo de las llamadas a Gemini, a partir del registro
 * generado por el listener (storage/logs/ia-tokens.jsonl) cuando IA_TOKEN_LOG=true.
 *
 * Flujo de uso para medir "un flujo completo de descargos → sanción":
 *   1. IA_TOKEN_LOG=true en .env  +  php artisan optimize:clear
 *   2. php artisan ia:reporte-tokens --limpiar        (deja el registro en cero)
 *   3. Corre UN flujo real: crear descargos → diligenciar → emitir sanción.
 *   4. php artisan ia:reporte-tokens                   (muestra tokens y costo)
 *
 * Precios POR DEFECTO (USD por 1M de tokens) — VERIFÍCALOS en ai.google.dev/pricing,
 * cambian con el tiempo. Se pueden sobreescribir con las opciones --in-* / --out-*.
 */
class ReporteTokens extends Command
{
    protected $signature = 'ia:reporte-tokens
        {--archivo= : Ruta del jsonl (def: storage/logs/ia-tokens.jsonl)}
        {--desde= : Solo contar registros con ts >= a este ISO8601}
        {--limpiar : Vacía el registro y termina (para empezar una medición)}
        {--cop=4000 : Tasa COP por USD para mostrar el costo en pesos}
        {--in-flash=0.30 : USD/1M tokens de ENTRADA gemini-2.5-flash}
        {--out-flash=2.50 : USD/1M tokens de SALIDA gemini-2.5-flash}
        {--in-lite=0.10 : USD/1M tokens de ENTRADA gemini-2.5-flash-lite}
        {--out-lite=0.40 : USD/1M tokens de SALIDA gemini-2.5-flash-lite}';

    protected $description = 'Tokens y costo de Gemini por flujo (requiere IA_TOKEN_LOG=true)';

    public function handle(): int
    {
        $archivo = $this->option('archivo') ?: storage_path('logs/ia-tokens.jsonl');

        if ($this->option('limpiar')) {
            @file_put_contents($archivo, '');
            $this->info("Registro vaciado: {$archivo}");
            $this->comment('Ahora corre UN flujo completo y vuelve a ejecutar el comando sin --limpiar.');
            return self::SUCCESS;
        }

        if (! is_file($archivo)) {
            $this->error("No existe el registro: {$archivo}");
            $this->line('¿Activaste IA_TOKEN_LOG=true y corriste optimize:clear?');
            return self::FAILURE;
        }

        // Precios por modelo (USD / 1M tokens).
        $precio = [
            'gemini-2.5-flash'      => ['in' => (float) $this->option('in-flash'), 'out' => (float) $this->option('out-flash')],
            'gemini-2.5-flash-lite' => ['in' => (float) $this->option('in-lite'),  'out' => (float) $this->option('out-lite')],
        ];
        $cop  = (float) $this->option('cop');
        $desde = $this->option('desde');

        $lineas = array_filter(array_map('trim', file($archivo)));
        $registros = [];
        foreach ($lineas as $l) {
            $r = json_decode($l, true);
            if (! is_array($r)) continue;
            if ($desde && ($r['ts'] ?? '') < $desde) continue;
            $registros[] = $r;
        }

        if (empty($registros)) {
            $this->warn('No hay registros (¿el flujo no generó llamadas o el --desde es muy reciente?).');
            return self::SUCCESS;
        }

        // ── Detalle por llamada ────────────────────────────────────────────────
        $filasDetalle = [];
        $totIn = 0; $totOut = 0; $totCost = 0.0;
        $porModelo = [];

        foreach ($registros as $r) {
            $modelo = $r['modelo'] ?? 'desconocido';
            $in  = (int) ($r['prompt'] ?? 0);
            $out = (int) ($r['salida'] ?? 0);
            $p = $precio[$modelo] ?? null;
            $costo = $p ? ($in / 1e6 * $p['in']) + ($out / 1e6 * $p['out']) : 0.0;

            $totIn += $in; $totOut += $out; $totCost += $costo;

            $porModelo[$modelo] ??= ['in' => 0, 'out' => 0, 'costo' => 0.0, 'n' => 0];
            $porModelo[$modelo]['in']  += $in;
            $porModelo[$modelo]['out'] += $out;
            $porModelo[$modelo]['costo'] += $costo;
            $porModelo[$modelo]['n']++;

            $filasDetalle[] = [
                substr($r['ts'] ?? '', 11, 8),
                $modelo . ($r['metodo'] === 'embedContent' ? ' (emb)' : ''),
                number_format($in),
                number_format($out),
                $p ? '$' . number_format($costo, 5) : 'n/d',
                \Illuminate\Support\Str::limit($r['ruta'] ?? '', 28),
            ];
        }

        $this->newLine();
        $this->info('DETALLE POR LLAMADA (orden cronológico = pasos del flujo)');
        $this->table(['Hora', 'Modelo', 'Entrada', 'Salida', 'Costo USD', 'Ruta'], $filasDetalle);

        // ── Resumen por modelo ─────────────────────────────────────────────────
        $filasModelo = [];
        foreach ($porModelo as $modelo => $d) {
            $filasModelo[] = [
                $modelo,
                $d['n'],
                number_format($d['in']),
                number_format($d['out']),
                '$' . number_format($d['costo'], 5),
            ];
        }
        $this->newLine();
        $this->info('RESUMEN POR MODELO');
        $this->table(['Modelo', 'Llamadas', 'Tokens entrada', 'Tokens salida', 'Costo USD'], $filasModelo);

        // ── Total del flujo ────────────────────────────────────────────────────
        $this->newLine();
        $this->info('TOTAL DEL FLUJO');
        $this->table(['Métrica', 'Valor'], [
            ['Llamadas a IA',     count($registros)],
            ['Tokens de entrada', number_format($totIn)],
            ['Tokens de salida',  number_format($totOut)],
            ['Tokens totales',    number_format($totIn + $totOut)],
            ['Costo USD',         '$' . number_format($totCost, 4)],
            ['Costo COP (~' . number_format($cop) . '/USD)', '$' . number_format($totCost * $cop, 0)],
        ]);

        $this->newLine();
        $this->comment('Nota: los embeddings (gemini-embedding-001) no devuelven usageMetadata; su costo es marginal y no se incluye.');
        $this->comment('Precios usados (USD/1M): flash in ' . $this->option('in-flash') . ' / out ' . $this->option('out-flash')
            . ' · lite in ' . $this->option('in-lite') . ' / out ' . $this->option('out-lite') . '. Verifícalos en ai.google.dev/pricing.');

        return self::SUCCESS;
    }
}
