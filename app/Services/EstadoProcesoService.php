<?php

namespace App\Services;

use App\Models\ProcesoDisciplinario;
use Illuminate\Support\Facades\Log;

class EstadoProcesoService
{
    /**
     * Define las transiciones válidas de estado (FLUJO SIMPLIFICADO)
     *
     * FLUJO PRINCIPAL:
     * apertura → descargos_pendientes → descargos_realizados → sancion_emitida → cerrado
     *
     * FLUJOS ALTERNATIVOS:
     * - Desde descargos_pendientes puede ir a descargos_no_realizados (si no asiste)
     * - Desde sancion_emitida puede ir a impugnacion_realizada
     * - Desde impugnacion_realizada puede volver a sancion_emitida o ir a cerrado
     * - Cualquier estado puede ir a archivado
     */
    const TRANSICIONES_VALIDAS = [
        'apertura' => ['descargos_pendientes', 'archivado'],
        'descargos_pendientes' => ['descargos_realizados', 'descargos_no_realizados', 'archivado'],
        'descargos_realizados' => ['sancion_emitida', 'archivado'],
        'descargos_no_realizados' => ['sancion_emitida', 'archivado'],
        'sancion_emitida' => ['impugnacion_realizada', 'cerrado', 'archivado'],
        'impugnacion_realizada' => ['sancion_emitida', 'cerrado', 'archivado'],
        'cerrado' => [],
        'archivado' => [],
    ];

    /**
     * Descripciones de cada estado (SIMPLIFICADO)
     */
    const DESCRIPCIONES_ESTADO = [
        'apertura' => 'Proceso iniciado',
        'descargos_pendientes' => 'Citación enviada - Esperando diligencia',
        'descargos_realizados' => 'Diligencia de descargos completada',
        'descargos_no_realizados' => 'El trabajador no asistió a la diligencia',
        'sancion_emitida' => 'Sanción emitida y notificada',
        'impugnacion_realizada' => 'Sanción impugnada por el trabajador',
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
            descripcion: $observacion ?? $this->getDescripcionEstado($nuevoEstado)
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
     * Transiciones automáticas basadas en eventos (SIMPLIFICADO)
     */

    /**
     * Cuando se envía la citación al trabajador
     */
    public function alEnviarCitacion(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'descargos_pendientes',
            'Citación enviada al trabajador'
        );
    }

    /**
     * Cuando el trabajador completa la diligencia de descargos
     */
    public function alCompletarDescargos(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'descargos_realizados',
            'Trabajador completó la diligencia de descargos'
        );
    }

    /**
     * Cuando el trabajador no asiste a la diligencia de descargos
     */
    public function alNoAsistirDescargos(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'descargos_no_realizados',
            'El trabajador no asistió a la diligencia de descargos programada'
        );
    }

    /**
     * Cuando se emite y notifica la sanción al trabajador
     */
    public function alEmitirSancion(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'sancion_emitida',
            'Sanción emitida y notificada al trabajador'
        );
    }

    /**
     * Cuando el trabajador impugna la sanción
     */
    public function alImpugnar(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'impugnacion_realizada',
            'Sanción impugnada por el trabajador'
        );
    }

    /**
     * Cuando el proceso se cierra definitivamente
     */
    public function alCerrarProceso(ProcesoDisciplinario $proceso): void
    {
        $this->cambiarEstado(
            $proceso,
            'cerrado',
            'Proceso disciplinario cerrado'
        );
    }

    /**
     * Cuando el proceso se archiva sin sanción
     */
    public function alArchivarProceso(ProcesoDisciplinario $proceso, string $motivo): void
    {
        $this->cambiarEstado(
            $proceso,
            'archivado',
            "Proceso archivado: {$motivo}"
        );
    }

    // ============================================
    // MÉTODOS OBSOLETOS (para compatibilidad)
    // ============================================

    /**
     * @deprecated Usar alEmitirSancion() en su lugar
     */
    public function alDefinirSancion(ProcesoDisciplinario $proceso): void
    {
        $this->alEmitirSancion($proceso);
    }

    /**
     * @deprecated Estado eliminado del flujo simplificado
     */
    public function alCrearAnalisisJuridico(ProcesoDisciplinario $proceso): void
    {
        Log::warning('Método obsoleto alCrearAnalisisJuridico llamado', [
            'proceso_id' => $proceso->id,
        ]);
    }

    /**
     * @deprecated Estado eliminado del flujo simplificado
     */
    public function alNotificarTrabajador(ProcesoDisciplinario $proceso): void
    {
        Log::warning('Método obsoleto alNotificarTrabajador llamado', [
            'proceso_id' => $proceso->id,
        ]);
    }
}
