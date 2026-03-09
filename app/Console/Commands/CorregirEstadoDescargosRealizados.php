<?php

namespace App\Console\Commands;

use App\Models\ProcesoDisciplinario;
use App\Services\EstadoProcesoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CorregirEstadoDescargosRealizados extends Command
{
    protected $signature = 'procesos:corregir-estado-descargos
                            {--dry-run : Solo muestra los procesos afectados sin hacer cambios}';

    protected $description = 'Corrige procesos en estado "descargos_no_realizados" donde el trabajador sí completó la diligencia';

    public function handle(EstadoProcesoService $estadoService): int
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? '[DRY RUN] Buscando procesos a corregir...' : 'Buscando procesos a corregir...');

        // Buscar procesos en descargos_no_realizados que tengan diligencia completada
        $procesos = ProcesoDisciplinario::where('estado', 'descargos_no_realizados')
            ->with(['diligenciaDescargo', 'trabajador'])
            ->get()
            ->filter(function (ProcesoDisciplinario $proceso) {
                $diligencia = $proceso->diligenciaDescargo;
                if (!$diligencia) {
                    return false;
                }
                return $diligencia->trabajador_asistio === true
                    || $diligencia->preguntas()->whereHas('respuesta')->exists();
            });

        if ($procesos->isEmpty()) {
            $this->info('No se encontraron procesos que requieran corrección.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Código', 'Trabajador', 'Empresa', 'Asistió', 'Preguntas respondidas'],
            $procesos->map(fn($p) => [
                $p->id,
                $p->codigo ?? '-',
                $p->trabajador?->nombre_completo ?? '-',
                $p->empresa?->razon_social ?? '-',
                $p->diligenciaDescargo?->trabajador_asistio ? 'Sí' : 'No',
                $p->diligenciaDescargo?->preguntas()->whereHas('respuesta')->count() ?? 0,
            ])
        );

        $this->info("Total: {$procesos->count()} proceso(s) a corregir.");

        if ($dryRun) {
            $this->warn('[DRY RUN] No se realizaron cambios. Ejecute sin --dry-run para aplicar.');
            return self::SUCCESS;
        }

        if (!$this->confirm('¿Desea actualizar el estado de estos procesos a "descargos_realizados"?')) {
            $this->info('Operación cancelada.');
            return self::SUCCESS;
        }

        $corregidos = 0;
        $errores    = 0;

        foreach ($procesos as $proceso) {
            $resultado = $estadoService->cambiarEstado(
                $proceso,
                'descargos_realizados',
                'Estado corregido por comando: el trabajador completó la diligencia'
            );

            if ($resultado) {
                $corregidos++;
                $this->line("  ✓ Proceso #{$proceso->id} ({$proceso->codigo}) → descargos_realizados");
                Log::info('Estado corregido por comando', ['proceso_id' => $proceso->id]);
            } else {
                $errores++;
                $this->error("  ✗ Proceso #{$proceso->id} ({$proceso->codigo}) — no se pudo cambiar el estado");
            }
        }

        $this->newLine();
        $this->info("Completado: {$corregidos} corregido(s), {$errores} error(es).");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
