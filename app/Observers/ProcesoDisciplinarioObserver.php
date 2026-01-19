<?php

namespace App\Observers;

use App\Models\ProcesoDisciplinario;
use App\Services\DocumentGeneratorService;
use App\Services\TimelineService;
use App\Services\TerminoLegalService;
use App\Services\NotificacionService;
use App\Services\EstadoProcesoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcesoDisciplinarioObserver
{
    protected TimelineService $timelineService;
    protected TerminoLegalService $terminoLegalService;
    protected NotificacionService $notificacionService;
    protected EstadoProcesoService $estadoService;

    // Almacenar cambios de estado temporalmente (no se persiste en BD)
    protected static array $cambiosEstado = [];

    public function __construct(
        TimelineService $timelineService,
        TerminoLegalService $terminoLegalService,
        NotificacionService $notificacionService,
        EstadoProcesoService $estadoService
    ) {
        $this->timelineService = $timelineService;
        $this->terminoLegalService = $terminoLegalService;
        $this->notificacionService = $notificacionService;
        $this->estadoService = $estadoService;
    }

    /**
     * Se ejecuta antes de crear un proceso disciplinario
     */
    public function creating(ProcesoDisciplinario $proceso): void
    {
        // Generar código único automáticamente si no existe
        if (empty($proceso->codigo)) {
            $proceso->codigo = $this->generarCodigoUnico();
        }

        // Establecer fecha de solicitud si no existe
        if (empty($proceso->fecha_solicitud)) {
            $proceso->fecha_solicitud = now();
        }
    }

    /**
     * Se ejecuta después de crear un proceso disciplinario
     */
    public function created(ProcesoDisciplinario $proceso): void
    {
        // Registrar en el timeline
        $this->timelineService->registrarCreacion(
            procesoTipo: 'proceso_disciplinario',
            procesoId: $proceso->id,
            descripcion: "Proceso disciplinario {$proceso->codigo} creado para {$proceso->trabajador->nombre_completo}",
            metadata: [
                'trabajador_id' => $proceso->trabajador_id,
                'empresa_id' => $proceso->empresa_id,
            ]
        );

        // Notificar al abogado asignado si existe
        if ($proceso->abogado_id) {
            $this->notificacionService->notificarProcesoAperturado($proceso);
        }
    }

    /**
     * Se ejecuta antes de actualizar un proceso
     */
    public function updating(ProcesoDisciplinario $proceso): void
    {
        // Detectar cambio de estado
        if ($proceso->isDirty('estado')) {
            $estadoAnterior = $proceso->getOriginal('estado');
            $estadoNuevo = $proceso->estado;

            // Almacenar cambio de estado temporalmente usando static array
            self::$cambiosEstado[$proceso->id] = [
                'anterior' => $estadoAnterior,
                'nuevo' => $estadoNuevo,
            ];

            // Aplicar lógica específica según el estado
            $this->aplicarLogicaEstado($proceso, $estadoNuevo);
        }
    }

    /**
     * Se ejecuta después de actualizar un proceso
     */
    public function updated(ProcesoDisciplinario $proceso): void
    {
        // Registrar cambio de estado si existe
        if (isset(self::$cambiosEstado[$proceso->id])) {
            $cambio = self::$cambiosEstado[$proceso->id];

            $this->timelineService->registrarCambioEstado(
                procesoTipo: 'proceso_disciplinario',
                procesoId: $proceso->id,
                estadoAnterior: $cambio['anterior'],
                estadoNuevo: $cambio['nuevo']
            );

            // Limpiar el cambio temporal
            unset(self::$cambiosEstado[$proceso->id]);
        }
    }

    /**
     * Genera un código único para el proceso (formato: PD-2025-0001)
     */
    private function generarCodigoUnico(): string
    {
        $anio = now()->year;
        $prefijo = "PD-{$anio}-";

        // Obtener el último número del año actual
        $ultimoProceso = ProcesoDisciplinario::where('codigo', 'like', "{$prefijo}%")
            ->orderBy('codigo', 'desc')
            ->first();

        if ($ultimoProceso) {
            // Extraer el número del último código
            $ultimoNumero = (int) substr($ultimoProceso->codigo, -4);
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return $prefijo . str_pad($nuevoNumero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Aplica lógica específica según el nuevo estado
     *
     * FLUJO DEL PROCESO DISCIPLINARIO:
     * ==================================================
     * 1. APERTURA                 → Proceso iniciado
     * 2. DESCARGOS_PENDIENTES     → Citación enviada, esperando diligencia
     * 3. DESCARGOS_REALIZADOS     → Trabajador completó descargos
     * 4. SANCION_EMITIDA          → Sanción determinada y notificada
     * 5. IMPUGNACION_REALIZADA    → Trabajador impugnó la sanción
     * 6. CERRADO                  → Proceso finalizado
     * 7. ARCHIVADO                → Proceso archivado sin sanción
     */
    private function aplicarLogicaEstado(ProcesoDisciplinario $proceso, string $nuevoEstado): void
    {
        switch ($nuevoEstado) {
            // ============================================
            // ESTADO 1: APERTURA
            // ============================================
            case 'apertura':
                // Estado inicial del proceso
                if (empty($proceso->fecha_apertura)) {
                    $proceso->fecha_apertura = now();
                }

                Log::info('Proceso disciplinario en apertura', [
                    'proceso_id' => $proceso->id,
                    'codigo' => $proceso->codigo,
                ]);
                break;

            // ============================================
            // ESTADO 2: DESCARGOS PENDIENTES
            // ============================================
            case 'descargos_pendientes':
                // La citación fue enviada, esperando que el trabajador asista a la diligencia
                if (empty($proceso->fecha_apertura)) {
                    $proceso->fecha_apertura = now();
                }

                // Verificar si ya existe un término para descargos
                $terminoExistente = \App\Models\TerminoLegal::where('proceso_tipo', 'proceso_disciplinario')
                    ->where('proceso_id', $proceso->id)
                    ->where('termino_tipo', 'traslado_descargos')
                    ->where('estado', 'activo')
                    ->exists();

                if (!$terminoExistente) {
                    $this->terminoLegalService->crearTermino(
                        procesoTipo: 'proceso_disciplinario',
                        procesoId: $proceso->id,
                        terminoTipo: 'traslado_descargos',
                        fechaInicio: now(),
                        diasHabiles: 5,
                        observaciones: 'Término para que el trabajador asista a la diligencia de descargos'
                    );
                }

                Log::info('Citación enviada - Descargos pendientes', [
                    'proceso_id' => $proceso->id,
                    'fecha_programada' => $proceso->fecha_descargos_programada,
                ]);
                break;

            // ============================================
            // ESTADO 3: DESCARGOS REALIZADOS
            // ============================================
            case 'descargos_realizados':
                // El trabajador completó la diligencia de descargos
                if (empty($proceso->fecha_descargos_realizada)) {
                    $proceso->fecha_descargos_realizada = now();
                }

                // Notificar al abogado que debe emitir la sanción (no detener si falla)
                try {
                    if (method_exists($this->notificacionService, 'notificarDescargosCompletados')) {
                        $this->notificacionService->notificarDescargosCompletados($proceso);
                    }
                } catch (\Exception $e) {
                    Log::warning('No se pudo enviar notificación de descargos completados', [
                        'proceso_id' => $proceso->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                Log::info('Descargos completados - Listo para emitir sanción', [
                    'proceso_id' => $proceso->id,
                ]);
                break;

            // ============================================
            // ESTADO 3B: DESCARGOS NO REALIZADOS
            // ============================================
            case 'descargos_no_realizados':
                // El trabajador no asistió a la diligencia de descargos
                if (empty($proceso->fecha_descargos_realizada)) {
                    $proceso->fecha_descargos_realizada = now();
                }

                // Notificar que el trabajador no asistió (no detener si falla)
                try {
                    if (method_exists($this->notificacionService, 'notificarDescargosNoRealizados')) {
                        $this->notificacionService->notificarDescargosNoRealizados($proceso);
                    }
                } catch (\Exception $e) {
                    Log::warning('No se pudo enviar notificación de descargos no realizados', [
                        'proceso_id' => $proceso->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                Log::info('Descargos no realizados - Trabajador no asistió', [
                    'proceso_id' => $proceso->id,
                    'fecha_programada' => $proceso->fecha_descargos_programada,
                ]);
                break;

            // ============================================
            // ESTADO 4: SANCIÓN EMITIDA
            // ============================================
            case 'sancion_emitida':
                // La sanción fue emitida y notificada al trabajador
                if (empty($proceso->fecha_notificacion)) {
                    $proceso->fecha_notificacion = now();
                }

                // Calcular fecha límite de impugnación (3 días hábiles)
                if (empty($proceso->fecha_limite_impugnacion)) {
                    $fechaLimite = $this->terminoLegalService->calcularFechaVencimiento(now(), 3);
                    $proceso->fecha_limite_impugnacion = $fechaLimite;

                    // Crear término legal para impugnación
                    $this->terminoLegalService->crearTermino(
                        procesoTipo: 'proceso_disciplinario',
                        procesoId: $proceso->id,
                        terminoTipo: 'impugnacion',
                        fechaInicio: now(),
                        diasHabiles: 3,
                        observaciones: 'Término para que el trabajador impugne la sanción'
                    );

                    Log::info('Sanción emitida - Término de impugnación creado', [
                        'proceso_id' => $proceso->id,
                        'fecha_limite' => $fechaLimite,
                        'tipo_sancion' => $proceso->tipo_sancion,
                    ]);
                }
                break;

            // ============================================
            // ESTADO 5: IMPUGNACIÓN REALIZADA
            // ============================================
            case 'impugnacion_realizada':
                // El trabajador impugnó la sanción
                $proceso->impugnado = true;

                if (empty($proceso->fecha_impugnacion)) {
                    $proceso->fecha_impugnacion = now();
                }

                // Notificar al abogado de la impugnación
                $this->notificacionService->notificarImpugnacionRecibida($proceso);

                Log::info('Sanción impugnada - Requiere revisión', [
                    'proceso_id' => $proceso->id,
                    'fecha_impugnacion' => $proceso->fecha_impugnacion,
                ]);
                break;

            // ============================================
            // ESTADO 6: CERRADO
            // ============================================
            case 'cerrado':
                // El proceso disciplinario ha concluido
                if (empty($proceso->fecha_cierre)) {
                    $proceso->fecha_cierre = now();
                }

                // Cerrar todos los términos legales activos
                $this->cerrarTerminosLegalesActivos($proceso, 'Proceso cerrado');

                // Notificar cierre del proceso
                if (method_exists($this->notificacionService, 'notificarProcesoCerrado')) {
                    $this->notificacionService->notificarProcesoCerrado($proceso);
                }

                Log::info('Proceso disciplinario cerrado', [
                    'proceso_id' => $proceso->id,
                    'codigo' => $proceso->codigo,
                    'fecha_cierre' => $proceso->fecha_cierre,
                ]);
                break;

            // ============================================
            // ESTADO 7: ARCHIVADO
            // ============================================
            case 'archivado':
                // El proceso fue archivado (sin sanción o por motivo específico)
                if (empty($proceso->fecha_cierre)) {
                    $proceso->fecha_cierre = now();
                }

                // Cerrar todos los términos legales activos
                $this->cerrarTerminosLegalesActivos($proceso, 'Proceso archivado');

                Log::info('Proceso disciplinario archivado', [
                    'proceso_id' => $proceso->id,
                    'codigo' => $proceso->codigo,
                    'motivo_archivo' => $proceso->motivo_archivo,
                ]);
                break;

            // ============================================
            // ESTADO NO RECONOCIDO
            // ============================================
            default:
                Log::warning('Estado no reconocido en aplicarLogicaEstado', [
                    'proceso_id' => $proceso->id,
                    'estado' => $nuevoEstado,
                ]);
                break;
        }
    }

    /**
     * Cierra todos los términos legales activos de un proceso
     */
    private function cerrarTerminosLegalesActivos(ProcesoDisciplinario $proceso, string $motivo): void
    {
        $terminos = \App\Models\TerminoLegal::where('proceso_tipo', 'proceso_disciplinario')
            ->where('proceso_id', $proceso->id)
            ->where('estado', 'activo')
            ->get();

        foreach ($terminos as $termino) {
            $this->terminoLegalService->cerrarTermino($termino, $motivo);
        }

        if ($terminos->count() > 0) {
            Log::info('Términos legales cerrados', [
                'proceso_id' => $proceso->id,
                'cantidad' => $terminos->count(),
                'motivo' => $motivo,
            ]);
        }
    }
}
