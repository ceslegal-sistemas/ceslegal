<?php

namespace App\Console\Commands;

use App\Models\ProcesoDisciplinario;
use App\Services\EstadoProcesoService;
use App\Services\DocumentGeneratorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ActualizarEstadosDescargos extends Command
{
    protected $signature = 'procesos:actualizar-estados-descargos';

    protected $description = 'Actualiza automáticamente los estados de procesos disciplinarios (descargos y cierre por vencimiento de impugnación)';

    public function handle(): int
    {
        $this->info('=== Actualizando estados de procesos disciplinarios ===');
        $this->newLine();

        $estadoService = app(EstadoProcesoService::class);
        $documentService = app(DocumentGeneratorService::class);

        // Ejecutar todas las actualizaciones
        $resultadoRecordatorios = $this->enviarRecordatoriosDescargos($documentService);
        $resultadoDescargos = $this->actualizarEstadosDescargos($estadoService, $documentService);
        $resultadoCierres = $this->cerrarProcesosSinImpugnacion($estadoService);

        // Resumen final
        $this->newLine();
        $this->info('=== Resumen Final ===');
        $this->info("  - Recordatorios enviados: {$resultadoRecordatorios['enviados']}");
        $this->info("  - Descargos realizados: {$resultadoDescargos['realizados']}");
        $this->info("  - Descargos no realizados: {$resultadoDescargos['no_realizados']}");
        $this->info("  - Notificaciones a empleadores: {$resultadoDescargos['notificaciones_empleador']}");
        $this->info("  - Procesos cerrados (sin impugnación): {$resultadoCierres['cerrados']}");

        $total = $resultadoRecordatorios['enviados'] + $resultadoDescargos['realizados'] + $resultadoDescargos['no_realizados'] + $resultadoCierres['cerrados'];
        if ($total === 0) {
            $this->info('No hubo cambios de estado ni notificaciones.');
        }

        return Command::SUCCESS;
    }

    /**
     * Envía recordatorios a trabajadores cuya diligencia de descargos es mañana
     */
    private function enviarRecordatoriosDescargos(DocumentGeneratorService $documentService): array
    {
        $this->info('>> Enviando recordatorios de descargos (1 día antes)...');

        $enviados = 0;
        $manana = Carbon::tomorrow()->startOfDay();

        // Buscar procesos con descargos programados para mañana
        $procesos = ProcesoDisciplinario::where('estado', 'descargos_pendientes')
            ->whereDate('fecha_descargos_programada', $manana)
            ->whereHas('trabajador', function ($query) {
                $query->whereNotNull('email');
            })
            ->with(['trabajador', 'empresa', 'diligenciaDescargo'])
            ->get();

        foreach ($procesos as $proceso) {
            // Verificar que no se haya enviado recordatorio hoy
            $yaEnviado = \App\Models\EmailTracking::where('proceso_id', $proceso->id)
                ->where('tipo_documento', 'recordatorio_descargos')
                ->whereDate('enviado_en', Carbon::today())
                ->exists();

            if ($yaEnviado) {
                $this->line("   - {$proceso->codigo}: recordatorio ya enviado hoy, omitido");
                continue;
            }

            try {
                $resultado = $documentService->enviarRecordatorioDescargos($proceso);

                if ($resultado['success']) {
                    $enviados++;
                    $this->info("   ✓ {$proceso->codigo} → Recordatorio enviado a {$proceso->trabajador->email}");
                } else {
                    $this->warn("   ⚠ {$proceso->codigo} → {$resultado['error']}");
                }
            } catch (\Exception $e) {
                Log::error('Error al enviar recordatorio de descargos', [
                    'proceso_id' => $proceso->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("   ✗ Error en {$proceso->codigo}: {$e->getMessage()}");
            }
        }

        if ($procesos->isEmpty()) {
            $this->line('   No hay diligencias programadas para mañana.');
        }

        return [
            'enviados' => $enviados,
        ];
    }

    /**
     * Actualiza estados basados en descargos realizados o no realizados
     */
    private function actualizarEstadosDescargos(EstadoProcesoService $estadoService, DocumentGeneratorService $documentService): array
    {
        $this->newLine();
        $this->info('>> Verificando estados de descargos...');

        $actualizados = 0;
        $noRealizados = 0;
        $notificacionesEmpleador = 0;

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

                    $this->info("   ✓ {$proceso->codigo} → descargos_realizados ({$preguntasRespondidas} preguntas respondidas)");
                } catch (\Exception $e) {
                    Log::error('Error al actualizar estado', [
                        'proceso_id' => $proceso->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("   ✗ Error en {$proceso->codigo}: {$e->getMessage()}");
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
            ->with(['diligenciaDescargo', 'trabajador', 'empresa'])
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

                    $this->warn("   ⚠ {$proceso->codigo} → descargos_no_realizados (no asistió, fecha: {$proceso->fecha_descargos_programada})");

                    // Enviar notificación al empleador
                    try {
                        $resultadoNotificacion = $documentService->notificarEmpleadorDescargosNoRealizados($proceso);

                        if ($resultadoNotificacion['success']) {
                            $notificacionesEmpleador++;
                            $this->info("     → Empleador notificado ({$resultadoNotificacion['enviados']} correo(s))");
                        } else {
                            $this->warn("     → No se pudo notificar al empleador: {$resultadoNotificacion['error']}");
                        }
                    } catch (\Exception $e) {
                        Log::error('Error al notificar empleador de descargos no realizados', [
                            'proceso_id' => $proceso->id,
                            'error' => $e->getMessage(),
                        ]);
                        $this->error("     → Error al notificar empleador: {$e->getMessage()}");
                    }

                } catch (\Exception $e) {
                    Log::error('Error al actualizar estado', [
                        'proceso_id' => $proceso->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("   ✗ Error en {$proceso->codigo}: {$e->getMessage()}");
                }
            }
        }

        return [
            'realizados' => $actualizados,
            'no_realizados' => $noRealizados,
            'notificaciones_empleador' => $notificacionesEmpleador,
        ];
    }

    /**
     * Cierra automáticamente procesos en sancion_emitida cuando ha pasado
     * el plazo de 3 días hábiles para impugnar sin que se haya presentado impugnación
     */
    private function cerrarProcesosSinImpugnacion(EstadoProcesoService $estadoService): array
    {
        $this->newLine();
        $this->info('>> Verificando procesos con plazo de impugnación vencido...');

        $cerrados = 0;

        // Buscar procesos en estado sancion_emitida sin impugnación
        $procesos = ProcesoDisciplinario::where('estado', 'sancion_emitida')
            ->whereDoesntHave('impugnacion')
            ->whereNotNull('fecha_notificacion')
            ->get();

        foreach ($procesos as $proceso) {
            // Calcular fecha límite de impugnación (3 días hábiles desde la notificación)
            $fechaNotificacion = Carbon::parse($proceso->fecha_notificacion);
            $fechaLimite = $fechaNotificacion->copy();
            $diasContados = 0;

            while ($diasContados < 3) {
                $fechaLimite->addDay();
                if ($fechaLimite->isWeekday()) {
                    $diasContados++;
                }
            }

            // Si ya pasó la fecha límite, cerrar el proceso
            if (now()->startOfDay()->gt($fechaLimite)) {
                try {
                    $estadoService->alCerrarProceso($proceso);
                    $cerrados++;

                    Log::info('Proceso cerrado automáticamente por vencimiento del plazo de impugnación', [
                        'proceso_id' => $proceso->id,
                        'codigo' => $proceso->codigo,
                        'fecha_notificacion' => $fechaNotificacion->format('Y-m-d'),
                        'fecha_limite' => $fechaLimite->format('Y-m-d'),
                    ]);

                    $this->info("   ✓ {$proceso->codigo} → cerrado (plazo de impugnación vencido: {$fechaLimite->format('d/m/Y')})");
                } catch (\Exception $e) {
                    Log::error('Error al cerrar proceso automáticamente', [
                        'proceso_id' => $proceso->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("   ✗ Error en {$proceso->codigo}: {$e->getMessage()}");
                }
            }
        }

        return [
            'cerrados' => $cerrados,
        ];
    }
}
