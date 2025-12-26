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
     */
    private function aplicarLogicaEstado(ProcesoDisciplinario $proceso, string $nuevoEstado): void
    {
        switch ($nuevoEstado) {
            case 'traslado':
                // Crear término legal para los descargos (15 días hábiles)
                if (empty($proceso->fecha_apertura)) {
                    $proceso->fecha_apertura = now();
                }

                // Crear el término legal para traslado de descargos
                $this->terminoLegalService->crearTermino(
                    procesoTipo: 'proceso_disciplinario',
                    procesoId: $proceso->id,
                    terminoTipo: 'traslado_descargos',
                    fechaInicio: now(),
                    diasHabiles: 15,
                    observaciones: 'Término para que el trabajador presente descargos'
                );
                break;

            case 'sancion_definida':
                // Establecer fecha de notificación si no existe
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
                }
                break;

            case 'impugnado':
                $proceso->impugnado = true;
                if (empty($proceso->fecha_impugnacion)) {
                    $proceso->fecha_impugnacion = now();
                }

                // Notificar al abogado
                $this->notificacionService->notificarImpugnacionRecibida($proceso);
                break;

            case 'cerrado':
            case 'archivado':
                if (empty($proceso->fecha_cierre)) {
                    $proceso->fecha_cierre = now();
                }

                // Cerrar todos los términos legales activos del proceso
                $terminos = \App\Models\TerminoLegal::where('proceso_tipo', 'proceso_disciplinario')
                    ->where('proceso_id', $proceso->id)
                    ->where('estado', 'activo')
                    ->get();

                foreach ($terminos as $termino) {
                    $this->terminoLegalService->cerrarTermino($termino, "Proceso {$nuevoEstado}");
                }
                break;
        }
    }
}
