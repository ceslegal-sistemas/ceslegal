<?php

namespace App\Observers;

use App\Models\SolicitudContrato;
use App\Services\TimelineService;
use App\Services\TerminoLegalService;
use App\Services\NotificacionService;

class SolicitudContratoObserver
{
    protected TimelineService $timelineService;
    protected TerminoLegalService $terminoLegalService;
    protected NotificacionService $notificacionService;

    public function __construct(
        TimelineService $timelineService,
        TerminoLegalService $terminoLegalService,
        NotificacionService $notificacionService
    ) {
        $this->timelineService = $timelineService;
        $this->terminoLegalService = $terminoLegalService;
        $this->notificacionService = $notificacionService;
    }

    /**
     * Se ejecuta antes de crear una solicitud de contrato
     */
    public function creating(SolicitudContrato $solicitud): void
    {
        // Generar código único automáticamente si no existe
        if (empty($solicitud->codigo)) {
            $solicitud->codigo = $this->generarCodigoUnico();
        }

        // Establecer fecha de solicitud si no existe
        if (empty($solicitud->fecha_solicitud)) {
            $solicitud->fecha_solicitud = now();
        }
    }

    /**
     * Se ejecuta después de crear una solicitud de contrato
     */
    public function created(SolicitudContrato $solicitud): void
    {
        // Registrar en el timeline
        $this->timelineService->registrarCreacion(
            procesoTipo: 'contrato',
            procesoId: $solicitud->id,
            descripcion: "Solicitud de contrato {$solicitud->codigo} creada para {$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}",
            metadata: [
                'empresa_id' => $solicitud->empresa_id,
                'tipo_contrato' => $solicitud->tipo_contrato,
                'cargo' => $solicitud->cargo_contrato,
            ]
        );
    }

    /**
     * Se ejecuta antes de actualizar una solicitud
     */
    public function updating(SolicitudContrato $solicitud): void
    {
        // Detectar cambio de estado
        if ($solicitud->isDirty('estado')) {
            $estadoAnterior = $solicitud->getOriginal('estado');
            $estadoNuevo = $solicitud->estado;

            // Registrar cambio de estado en timeline después de guardar
            $solicitud->_cambioEstado = [
                'anterior' => $estadoAnterior,
                'nuevo' => $estadoNuevo,
            ];

            // Aplicar lógica específica según el estado
            $this->aplicarLogicaEstado($solicitud, $estadoNuevo);
        }

        // Detectar asignación de abogado
        if ($solicitud->isDirty('abogado_id') && !empty($solicitud->abogado_id)) {
            $solicitud->_abogadoAsignado = true;
        }
    }

    /**
     * Se ejecuta después de actualizar una solicitud
     */
    public function updated(SolicitudContrato $solicitud): void
    {
        // Registrar cambio de estado si existe
        if (isset($solicitud->_cambioEstado)) {
            $this->timelineService->registrarCambioEstado(
                procesoTipo: 'contrato',
                procesoId: $solicitud->id,
                estadoAnterior: $solicitud->_cambioEstado['anterior'],
                estadoNuevo: $solicitud->_cambioEstado['nuevo']
            );

            unset($solicitud->_cambioEstado);
        }

        // Registrar asignación de abogado si existe
        if (isset($solicitud->_abogadoAsignado)) {
            $abogado = \App\Models\User::find($solicitud->abogado_id);

            if ($abogado) {
                $this->timelineService->registrarAsignacion(
                    procesoTipo: 'contrato',
                    procesoId: $solicitud->id,
                    abogadoId: $abogado->id,
                    nombreAbogado: $abogado->name
                );

                // Notificar al abogado
                $this->notificacionService->crear(
                    userId: $abogado->id,
                    tipo: 'contrato_generado',
                    titulo: 'Nueva Solicitud de Contrato Asignada',
                    mensaje: "Se te ha asignado la solicitud de contrato {$solicitud->codigo}",
                    relacionadoTipo: SolicitudContrato::class,
                    relacionadoId: $solicitud->id,
                    prioridad: 'alta'
                );
            }

            unset($solicitud->_abogadoAsignado);
        }
    }

    /**
     * Genera un código único para la solicitud (formato: SC-2025-0001)
     */
    private function generarCodigoUnico(): string
    {
        $anio = now()->year;
        $prefijo = "SC-{$anio}-";

        // Obtener el último número del año actual
        $ultimaSolicitud = SolicitudContrato::where('codigo', 'like', "{$prefijo}%")
            ->orderBy('codigo', 'desc')
            ->first();

        if ($ultimaSolicitud) {
            // Extraer el número del último código
            $ultimoNumero = (int) substr($ultimaSolicitud->codigo, -4);
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return $prefijo . str_pad($nuevoNumero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Aplica lógica específica según el nuevo estado
     */
    private function aplicarLogicaEstado(SolicitudContrato $solicitud, string $nuevoEstado): void
    {
        switch ($nuevoEstado) {
            case 'en_analisis':
                if (empty($solicitud->fecha_analisis)) {
                    $solicitud->fecha_analisis = now();
                }
                break;

            case 'contrato_generado':
                if (empty($solicitud->fecha_generacion_contrato)) {
                    $solicitud->fecha_generacion_contrato = now();
                }

                // Notificar que el contrato está listo
                $this->notificacionService->notificarContratoGenerado($solicitud);
                break;

            case 'enviado_rrhh':
                if (empty($solicitud->fecha_envio_rrhh)) {
                    $solicitud->fecha_envio_rrhh = now();
                }
                break;

            case 'cerrado':
                if (empty($solicitud->fecha_cierre)) {
                    $solicitud->fecha_cierre = now();
                }
                break;
        }
    }
}
