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

        // Inicio del período de contrato vigente = inicio del contrato
        // original al crearlo. PlazoContratoService lo va actualizando en
        // cada prórroga para calcular "el mismo período" (Art. 46 CST).
        if (empty($solicitud->fecha_inicio_periodo_actual) && !empty($solicitud->fecha_inicio_propuesta)) {
            $solicitud->fecha_inicio_periodo_actual = $solicitud->fecha_inicio_propuesta;
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
            // El motivo de rechazo se guarda en la misma llamada ->update()
            // que cambia el estado (ver SolicitudContratoResource::table(),
            // acción 'rechazar') - va como metadata del cambio de estado en
            // vez de un evento aparte, para no duplicar información.
            $metadata = $solicitud->_cambioEstado['nuevo'] === 'rechazado' && $solicitud->motivo_rechazo
                ? ['motivo_rechazo' => $solicitud->motivo_rechazo]
                : null;

            $this->timelineService->registrarCambioEstado(
                procesoTipo: 'contrato',
                procesoId: $solicitud->id,
                estadoAnterior: $solicitud->_cambioEstado['anterior'],
                estadoNuevo: $solicitud->_cambioEstado['nuevo'],
                metadata: $metadata
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

        // withTrashed(): SolicitudContrato usa SoftDeletes, así que sin esto la
        // consulta excluye los códigos ya borrados - la BD sí los sigue
        // bloqueando por la restricción única de la columna, así que cualquier
        // solicitud borrada dejaba su código "libre" para la lógica pero
        // ocupado para la BD real, reventando con UniqueConstraintViolationException
        // en la siguiente solicitud del mismo año (bug real, reproducido con
        // Livewire::test() al borrar una solicitud de prueba y crear otra después).
        //
        // withoutGlobalScopes(): `codigo` es único en TODA la tabla (todas las
        // empresas comparten la misma secuencia SC-{año}-NNNN), pero
        // ScopedToBufeteOrEmpresa agrega un global scope que filtra por
        // empresa_id para el rol cliente - sin excluirlo aquí, esta consulta
        // busca "el último código de MI empresa" en vez de "el último código
        // de todo el sistema", así que la primera solicitud de cualquier
        // empresa nueva vuelve a calcular "0001" aunque ese número ya lo haya
        // usado otra empresa - bug real en producción: UniqueConstraintViolationException
        // al crear la primera solicitud de una empresa (RENBEL, "SC-2026-0001"
        // ya usado por otra compañía).
        $ultimaSolicitud = SolicitudContrato::withoutGlobalScopes()
            ->withTrashed()
            ->where('codigo', 'like', "{$prefijo}%")
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
     * Aplica lógica específica según el nuevo estado.
     *
     * 'en_analisis'/'cerrado' se retiraron con la simplificación de
     * estados (migración 2026_08_24_000001) - 'cerrado' ya era código
     * muerto desde antes (legado inalcanzable, confirmado en sesiones
     * anteriores), 'en_analisis' se retira ahora.
     */
    private function aplicarLogicaEstado(SolicitudContrato $solicitud, string $nuevoEstado): void
    {
        switch ($nuevoEstado) {
            case 'borrador':
                if (empty($solicitud->fecha_generacion_contrato)) {
                    $solicitud->fecha_generacion_contrato = now();
                }
                break;

            case 'aprobado':
                if (empty($solicitud->fecha_cierre)) {
                    $solicitud->fecha_cierre = now();
                }

                // Notificar que el contrato está listo (abogado asignado +
                // RRHH/cliente de la empresa) - CRÍTICO: sin este case, esta
                // notificación dejaría de dispararse en silencio, porque
                // 'estado' ya nunca vuelve a valer 'contrato_generado'.
                $this->notificacionService->notificarContratoGenerado($solicitud);
                break;
        }
    }
}
