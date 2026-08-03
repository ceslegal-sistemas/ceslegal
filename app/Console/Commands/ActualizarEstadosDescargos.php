<?php

namespace App\Console\Commands;

use App\Models\DiligenciaDescargo;
use App\Models\ProcesoDisciplinario;
use App\Services\ActaDescargosService;
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
        $resultadoAbandonadas = $this->cerrarDiligenciasAbandonadasTrasCompletarPreguntas($estadoService, $documentService);
        $resultadoCierres = $this->cerrarProcesosSinImpugnacion($estadoService);

        // Resumen final
        $this->newLine();
        $this->info('=== Resumen Final ===');
        $this->info("  - Recordatorios enviados: {$resultadoRecordatorios['enviados']}");
        $this->info("  - Descargos realizados: {$resultadoDescargos['realizados']}");
        $this->info("  - Descargos no realizados: {$resultadoDescargos['no_realizados']}");
        $this->info("  - Notificaciones a empleadores: {$resultadoDescargos['notificaciones_empleador']}");
        $this->info("  - Diligencias abandonadas cerradas (respondió todo, nunca finalizó): {$resultadoAbandonadas['cerradas']}");
        $this->info("  - Procesos cerrados (sin impugnación): {$resultadoCierres['cerrados']}");

        $total = $resultadoRecordatorios['enviados'] + $resultadoDescargos['realizados'] + $resultadoDescargos['no_realizados'] + $resultadoAbandonadas['cerradas'] + $resultadoCierres['cerrados'];
        if ($total === 0) {
            $this->info('No hubo cambios de estado ni notificaciones.');
        }

        return Command::SUCCESS;
    }

    /**
     * Cierra diligencias donde el trabajador respondió TODAS las preguntas
     * (preguntas_completadas_en ya quedó registrado) pero nunca llegó a hacer
     * clic en "Finalizar" - por lo que trabajador_asistio nunca se marcó, no
     * se generó el acta y no se enviaron las notificaciones de cierre.
     *
     * Caso real que motivó esto: PD-2026-0057 (31 de julio) - el motor de
     * corroboración hizo 25 preguntas de seguimiento con IA sobre un mismo
     * tema (varias veces literalmente repetidas) antes de chocar con el
     * tope de 30 preguntas; el trabajador probablemente cerró la pestaña
     * agotado antes de llegar al botón final. La diligencia quedó atascada
     * varios días sin que nada la recuperara - ni este comando (que solo
     * miraba trabajador_asistio=true o 0 preguntas respondidas) ni ningún
     * otro proceso automático.
     *
     * Margen de 60 minutos desde preguntas_completadas_en antes de cerrar:
     * suficiente para que alguien todavía activo termine feedback/evidencias
     * sin que este comando le gane de mano, pero corto para no dejar un
     * caso real varado por días como pasó aquí.
     */
    private function cerrarDiligenciasAbandonadasTrasCompletarPreguntas(EstadoProcesoService $estadoService, DocumentGeneratorService $documentService): array
    {
        $this->newLine();
        $this->info('>> Verificando diligencias abandonadas tras completar las preguntas...');

        $cerradas = 0;
        $margenMinutos = 60;

        $diligencias = DiligenciaDescargo::whereNotNull('preguntas_completadas_en')
            ->where('preguntas_completadas_en', '<', now()->subMinutes($margenMinutos))
            ->where(function ($query) {
                $query->where('trabajador_asistio', false)
                    ->orWhereNull('trabajador_asistio');
            })
            ->whereHas('proceso', fn($query) => $query->where('estado', 'descargos_pendientes'))
            ->with(['proceso.trabajador', 'proceso.empresa'])
            ->get();

        foreach ($diligencias as $diligencia) {
            $proceso = $diligencia->proceso;

            try {
                $diligencia->update([
                    'trabajador_asistio' => true,
                    'fecha_diligencia' => $diligencia->preguntas_completadas_en,
                ]);
                $estadoService->alCompletarDescargos($proceso);

                $actaPath = null;
                $actaService = app(ActaDescargosService::class);
                $resultadoActa = $actaService->generarActaDescargos($diligencia);
                if ($resultadoActa['success'] ?? false) {
                    $actaPath = $resultadoActa['path'];
                    $diligencia->update(['acta_generada' => true, 'ruta_acta' => $actaPath]);
                }

                $documentService->enviarNotificacionEstadoDescargos($proceso, 'descargos_realizados', $actaPath);
                $documentService->enviarNotificacionDescargosAlCliente($proceso, 'descargos_realizados');

                $cerradas++;
                Log::channel('descargos')->warning('[recuperacion] Diligencia abandonada cerrada automáticamente por cron', [
                    'diligencia_id' => $diligencia->id,
                    'proceso_id' => $proceso->id,
                    'codigo' => $proceso->codigo,
                    'preguntas_completadas_en' => $diligencia->preguntas_completadas_en,
                    'acta_generada' => $actaPath !== null,
                ]);
                $this->warn("   ⚠ {$proceso->codigo} → cerrada automáticamente (llevaba " . $diligencia->preguntas_completadas_en->diffForHumans(null, true) . " sin finalizar)");
            } catch (\Exception $e) {
                Log::error('Error al cerrar diligencia abandonada', [
                    'diligencia_id' => $diligencia->id,
                    'proceso_id' => $proceso->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("   ✗ Error en {$proceso->codigo}: {$e->getMessage()}");
            }
        }

        if ($diligencias->isEmpty()) {
            $this->line('   No hay diligencias abandonadas pendientes de cerrar.');
        }

        return ['cerradas' => $cerradas];
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
        // CASO 1: Descargos realizados (el trabajador completó el formulario)
        // Solo se procesa si trabajador_asistio = true, lo cual se establece
        // al hacer clic en "Finalizar Descargos". Esto actúa como fallback
        // en caso de que la transición de estado haya fallado durante la sesión.
        // =====================================================
        $procesosConRespuestas = ProcesoDisciplinario::where('estado', 'descargos_pendientes')
            ->whereHas('diligenciaDescargo', function ($query) {
                $query->where('trabajador_asistio', true);
            })
            ->with('diligenciaDescargo')
            ->get();

        foreach ($procesosConRespuestas as $proceso) {
            $diligencia = $proceso->diligenciaDescargo;

            if (!$diligencia || !$diligencia->trabajador_asistio) {
                continue;
            }

            $preguntasRespondidas = $diligencia->preguntas()->whereHas('respuesta')->count();

            try {
                $estadoService->alCompletarDescargos($proceso);
                $actualizados++;

                Log::info('Estado actualizado a descargos_realizados (fallback por cron)', [
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
