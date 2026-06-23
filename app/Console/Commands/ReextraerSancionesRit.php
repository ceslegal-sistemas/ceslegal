<?php

namespace App\Console\Commands;

use App\Models\ReglamentoInterno;
use App\Services\ReglamentoInternoService;
use Illuminate\Console\Command;

/**
 * Re-extrae las sanciones por gravedad (leve/grave/muy grave) de los RIT SUBIDOS,
 * usando el prompt actualizado que separa la sanción por nivel. Los RIT del wizard
 * no lo necesitan (usan respuestas_cuestionario.sanciones_configuradas).
 *
 * Uso:
 *   php artisan rit:reextraer                 (todos los subidos con texto)
 *   php artisan rit:reextraer --id=12         (uno específico)
 *   php artisan rit:reextraer --todos         (incluye también los del wizard)
 */
class ReextraerSancionesRit extends Command
{
    protected $signature = 'rit:reextraer
        {--id= : ID de un RIT específico}
        {--todos : Incluir también los RIT generados por wizard (normalmente innecesario)}';

    protected $description = 'Re-extrae las sanciones por gravedad (leve/grave/muy grave) de los RIT subidos';

    public function handle(ReglamentoInternoService $service): int
    {
        $query = ReglamentoInterno::query()
            ->whereNotNull('texto_completo')
            ->where('texto_completo', '!=', '');

        if ($this->option('id')) {
            $query->where('id', $this->option('id'));
        } elseif (! $this->option('todos')) {
            $query->where('fuente', 'subido');
        }

        $rits = $query->get();

        if ($rits->isEmpty()) {
            $this->warn('No hay RIT con texto para re-extraer.');
            return self::SUCCESS;
        }

        $this->info("Re-extrayendo sanciones de {$rits->count()} RIT...");
        $ok = 0; $vacios = 0; $err = 0;

        foreach ($rits as $rit) {
            $this->line("  · RIT #{$rit->id} ({$rit->fuente}) — " . ($rit->nombre ?: 's/n'));
            try {
                $datos = $service->extraerYPersistirSanciones($rit);
                if (empty($datos)) {
                    $vacios++;
                    $this->warn('    sin cuadro de faltas claro');
                } else {
                    $ok++;
                    $leve = $datos['sancion_leve'] ?? '—';
                    $grave = $datos['sancion_grave'] ?? '—';
                    $muy = $datos['sancion_muy_grave'] ?? '—';
                    $this->info("    OK — leve: {$leve} | grave: {$grave} | muy grave: {$muy}");
                }
            } catch (\Throwable $e) {
                $err++;
                $this->error('    ERROR: ' . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Listo. OK: {$ok} · Vacíos: {$vacios} · Errores: {$err}.");

        return self::SUCCESS;
    }
}
