<?php

namespace App\Services;

use App\Models\ProcesoDisciplinario;
use Illuminate\Support\Facades\Log;

class EstadoProcesoService
{
    /**
     * Define las transiciones válidas de estado
     */
    const TRANSICIONES_VALIDAS = [
        'apertura' => ['traslado', 'descargos_pendientes', 'archivado'],
        'traslado' => ['descargos_pendientes', 'archivado'],
        'descargos_pendientes' => ['descargos_realizados', 'archivado'],
        'descargos_realizados' => ['analisis_juridico', 'archivado'],
        'analisis_juridico' => ['pendiente_gerencia', 'sancion_definida', 'archivado'],
        'pendiente_gerencia' => ['sancion_definida', 'archivado'],
        'sancion_definida' => ['notificado', 'archivado'],
        'notificado' => ['impugnado', 'cerrado'],
        'impugnado' => ['analisis_juridico', 'cerrado'],
        'cerrado' => [],
        'archivado' => [],
    ];

    /**
     * Descripciones de cada estado
     */
    const DESCRIPCIONES_ESTADO = [
        'apertura' => 'Proceso iniciado - En apertura',
        'traslado' => 'Traslado de cargos al trabajador',
        'descargos_pendientes' => 'Citación enviada - Descargos pendientes',
        'descargos_realizados' => 'Descargos realizados por el trabajador',
        'analisis_juridico' => 'En análisis jurídico',
        'pendiente_gerencia' => 'Pendiente de decisión de gerencia',
        'sancion_definida' => 'Sanción definida',
        'notificado' => 'Trabajador notificado de la decisión',
        'impugnado' => 'Sanción impugnada por el trabajador',
        'cerrado' => 'Proceso cerrado',
        'archivado' => 'Proceso archivado',
    ];

    protected TimelineService $timelineService;

    public function __construct(TimelineService $timelineService)
    {
        $this->timelineService = $timelineService;
    }

    /**
     * Cambia el estado del proceso si la transición es válida
     */
    public function cambiarEstado(
        ProcesoDisciplinario $proceso,
        string $nuevoEstado,
        ?string $observacion = null
    ): bool {
        $estadoActual = $proceso->estado;

        // Verificar si la transición es válida
        if (!$this->esTransicionValida($estadoActual, $nuevoEstado)) {
            Log::warning('Intento de transición inválida de estado', [
                'proceso_id' => $proceso->id,
                'estado_actual' => $estadoActual,
                'estado_nuevo' => $nuevoEstado,
            ]);
            return false;
        }

        // Cambiar el estado
        $proceso->estado = $nuevoEstado;
        $proceso->save();

        // Registrar en timeline
        $this->timelineService->registrarCambioEstado(
            procesoTipo: 'proceso_disciplinario',
            procesoId: $proceso->id,
            estadoAnterior: $estadoActual,
            estadoNuevo: $nuevoEstado,
            observacion: $observacion ?? $this->getDescripcionEstado($nuevoEstado)
        );

        Log::info('Estado de proceso cambiado exitosamente', [
            'proceso_id' => $proceso->id,
            'estado_anterior' => $estadoActual,
            'estado_nuevo' => $nuevoEstado,
        ]);

        return true;
    }

    /**
     * Verifica si una transición de estado es válida
     */
    public function esTransicionValida(string $estadoActual, string $nuevoEstado): bool
    {
        if (!isset(self::TRANSICIONES_VALIDAS[$estadoActual])) {
            return false;
        }

        return in_array($nuevoEstado, self::TRANSICIONES_VALIDAS[$estadoActual]);
    }

    /**
     * Obtiene los próximos estados posibles desde el estado actual
     */
    public function getProximosEstadosValidos(string $estadoActual): array
    {
        return self::TRANSICIONES_VALIDAS[$estadoActual] ?? [];
    }

    /**
     * Obtiene la descripción de un estado
     */
    public function getDescripcionEstado(string $estado): string
    {
        return self::DESCRIPCIONES_ESTADO[$estado] ?? $estado;
    }

    /**
     * Transiciones automáticas basadas en eventos
     */
    
    public function alEnviarCitacion(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'descargos_pendientes',
            'Citación enviada al trabajador'
        );
    }

    public function alCompletarDescargos(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'descargos_realizados',
            'Trabajador completó la diligencia de descargos'
        );
    }

    public function alCrearAnalisisJuridico(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'analisis_juridico',
            'Análisis jurídico iniciado'
        );
    }

    public function alDefinirSancion(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'sancion_definida',
            'Sanción definida y registrada'
        );
    }

    public function alNotificarTrabajador(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'notificado',
            'Trabajador notificado de la decisión'
        );
    }

    public function alImpugnar(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'impugnado',
            'Sanción impugnada por el trabajador'
        );
    }

    public function alCerrarProceso(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'cerrado',
            'Proceso disciplinario cerrado'
        );
    }

    public function alArchivarProceso(ProcesoDisciplinario $proceso, string $motivo): void
    {
        $this->cambiarEstado(
            $proceso,
            'archivado',
            "Proceso archivado: {$motivo}"
        );
    }
}
