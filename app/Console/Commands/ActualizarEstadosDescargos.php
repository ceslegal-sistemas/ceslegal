<?php

namespace App\Console\Commands;

use App\Models\ProcesoDisciplinario;
use App\Services\EstadoProcesoService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ActualizarEstadosDescargos extends Command
{
    protected $signature = 'procesos:actualizar-estados-descargos';

    protected $description = 'Detecta y actualiza automáticamente los estados de procesos según el resultado de los descargos';

    public function handle(): int
    {
        $estadoService = app(EstadoProcesoService::class);
        $actualizados = 0;
        $noRealizados = 0;

        // =====================================================
        // CASO 1: Descargos realizados (respondió al menos 1 pregunta)
        // =====================================================
        $procesosConRespuestas = ProcesoDisciplinario::where('estado', 'descargos_pendientes')
            ->whereHas('diligenciaDescargo.preguntas.respuesta')
            ->with('diligenciaDescargo')
            ->get();

        foreach ($procesosConRespuestas as $proceso) {
            $diligencia = $proceso->diligenciaDescargo;

            if (!$diligencia) {
                continue;
            }

            $preguntasRespondidas = $diligencia->preguntas()->whereHas('respuesta')->count();

            // Si respondió al menos 1 pregunta, marcar como descargos realizados
            if ($preguntasRespondidas >= 1) {
                try {
                    $estadoService->alCompletarDescargos($proceso);
                    $actualizados++;

                    // Marcar que el trabajador asistió si no estaba marcado
                    if (!$diligencia->trabajador_asistio) {
                        $diligencia->update(['trabajador_asistio' => true]);
                    }

                    Log::info('Estado actualizado a descargos_realizados', [
                        'proceso_id' => $proceso->id,
                        'codigo' => $proceso->codigo,
                        'preguntas_respondidas' => $preguntasRespondidas,
                    ]);

                    $this->info("✓ {$proceso->codigo} → descargos_realizados ({$preguntasRespondidas} preguntas respondidas)");
                } catch (\Exception $e) {
                    Log::error('Error al actualizar estado', [
                        'proceso_id' => $proceso->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("✗ Error en {$proceso->codigo}: {$e->getMessage()}");
                }
            }
        }

        // =====================================================
        // CASO 2: Descargos no realizados (no respondió y pasó la fecha)
        // =====================================================
        $procesosVencidos = ProcesoDisciplinario::where('estado', 'descargos_pendientes')
            ->where(function ($query) {
                // La fecha de descargos ya pasó
                $query->where('fecha_descargos_programada', '<', Carbon::now()->startOfDay());
            })
            ->whereHas('diligenciaDescargo', function ($query) {
                // El trabajador no asistió
                $query->where(function ($q) {
                    $q->where('trabajador_asistio', false)
                      ->orWhereNull('trabajador_asistio');
                });
            })
            ->with('diligenciaDescargo')
            ->get();

        foreach ($procesosVencidos as $proceso) {
            $diligencia = $proceso->diligenciaDescargo;

            if (!$diligencia) {
                continue;
            }

            // Verificar que no haya respondido ninguna pregunta
            $preguntasRespondidas = $diligencia->preguntas()->whereHas('respuesta')->count();

            if ($preguntasRespondidas === 0) {
                try {
                    $estadoService->alNoAsistirDescargos($proceso);
                    $noRealizados++;

                    Log::info('Estado actualizado a descargos_no_realizados', [
                        'proceso_id' => $proceso->id,
                        'codigo' => $proceso->codigo,
                        'fecha_programada' => $proceso->fecha_descargos_programada,
                    ]);

                    $this->warn("⚠ {$proceso->codigo} → descargos_no_realizados (no asistió, fecha: {$proceso->fecha_descargos_programada})");
                } catch (\Exception $e) {
                    Log::error('Error al actualizar estado', [
                        'proceso_id' => $proceso->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("✗ Error en {$proceso->codigo}: {$e->getMessage()}");
                }
            }
        }

        // Resumen
        if ($actualizados === 0 && $noRealizados === 0) {
            $this->info('No hay procesos pendientes de actualización.');
        } else {
            $this->newLine();
            $this->info("Resumen:");
            $this->info("  - Descargos realizados: {$actualizados}");
            $this->info("  - Descargos no realizados: {$noRealizados}");
        }

        return Command::SUCCESS;
    }
}
