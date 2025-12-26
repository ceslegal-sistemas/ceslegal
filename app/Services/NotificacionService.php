<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\User;
use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
use Illuminate\Support\Collection;

class NotificacionService
{
    /**
     * Crea una notificación
     */
    public function crear(
        int $userId,
        string $tipo,
        string $titulo,
        string $mensaje,
        ?string $relacionadoTipo = null,
        ?int $relacionadoId = null,
        string $prioridad = 'media'
    ): Notificacion {
        return Notificacion::create([
            'user_id' => $userId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'relacionado_tipo' => $relacionadoTipo,
            'relacionado_id' => $relacionadoId,
            'prioridad' => $prioridad,
            'leida' => false,
        ]);
    }

    /**
     * Notifica cuando se apertura un proceso disciplinario
     */
    public function notificarProcesoAperturado(ProcesoDisciplinario $proceso): void
    {
        $this->crear(
            userId: $proceso->abogado_id,
            tipo: 'proceso_aperturado',
            titulo: 'Nuevo Proceso Disciplinario Asignado',
            mensaje: "Se te ha asignado el proceso {$proceso->codigo} para el trabajador {$proceso->trabajador->nombre_completo}.",
            relacionadoTipo: ProcesoDisciplinario::class,
            relacionadoId: $proceso->id,
            prioridad: 'alta'
        );
    }

    /**
     * Notifica cuando se acerca la fecha de descargos
     */
    public function notificarDescargosProximos(ProcesoDisciplinario $proceso, int $diasRestantes): void
    {
        $this->crear(
            userId: $proceso->abogado_id,
            tipo: 'descargos_proximos',
            titulo: 'Diligencia de Descargos Próxima',
            mensaje: "La diligencia de descargos del proceso {$proceso->codigo} está programada en {$diasRestantes} días hábiles.",
            relacionadoTipo: ProcesoDisciplinario::class,
            relacionadoId: $proceso->id,
            prioridad: $diasRestantes <= 1 ? 'urgente' : 'alta'
        );
    }

    /**
     * Notifica cuando un término legal ha vencido
     */
    public function notificarTerminoVencido(
        int $userId,
        string $procesoTipo,
        int $procesoId,
        string $codigoProceso,
        string $terminoTipo
    ): void {
        $this->crear(
            userId: $userId,
            tipo: 'termino_vencido',
            titulo: 'Término Legal Vencido',
            mensaje: "El término de {$terminoTipo} del proceso {$codigoProceso} ha vencido.",
            relacionadoTipo: $procesoTipo,
            relacionadoId: $procesoId,
            prioridad: 'urgente'
        );
    }

    /**
     * Notifica cuando se aplica una sanción
     */
    public function notificarSancionAplicada(ProcesoDisciplinario $proceso, string $tipoSancion): void
    {
        // Notificar al abogado
        $this->crear(
            userId: $proceso->abogado_id,
            tipo: 'sancion_aplicada',
            titulo: 'Sanción Aplicada',
            mensaje: "Se ha aplicado una sanción de tipo {$tipoSancion} en el proceso {$proceso->codigo}.",
            relacionadoTipo: ProcesoDisciplinario::class,
            relacionadoId: $proceso->id,
            prioridad: 'alta'
        );

        // Notificar a RRHH (usuarios con rol rrhh de la misma empresa)
        $usuariosRRHH = User::where('role', 'rrhh')
            ->where('empresa_id', $proceso->empresa_id)
            ->where('active', true)
            ->get();

        foreach ($usuariosRRHH as $userRRHH) {
            $this->crear(
                userId: $userRRHH->id,
                tipo: 'sancion_aplicada',
                titulo: 'Sanción Aplicada - Requiere Acción de RRHH',
                mensaje: "Se aplicó {$tipoSancion} al trabajador {$proceso->trabajador->nombre_completo}. Proceso: {$proceso->codigo}",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'urgente'
            );
        }
    }

    /**
     * Notifica cuando se recibe una impugnación
     */
    public function notificarImpugnacionRecibida(ProcesoDisciplinario $proceso): void
    {
        $this->crear(
            userId: $proceso->abogado_id,
            tipo: 'impugnacion_recibida',
            titulo: 'Impugnación Recibida',
            mensaje: "Se ha recibido una impugnación para el proceso {$proceso->codigo}. Requiere análisis urgente.",
            relacionadoTipo: ProcesoDisciplinario::class,
            relacionadoId: $proceso->id,
            prioridad: 'urgente'
        );
    }

    /**
     * Notifica cuando se genera un contrato
     */
    public function notificarContratoGenerado(SolicitudContrato $solicitud): void
    {
        // Notificar al abogado asignado
        if ($solicitud->abogado_id) {
            $this->crear(
                userId: $solicitud->abogado_id,
                tipo: 'contrato_generado',
                titulo: 'Contrato Generado',
                mensaje: "El contrato {$solicitud->codigo} ha sido generado exitosamente.",
                relacionadoTipo: SolicitudContrato::class,
                relacionadoId: $solicitud->id,
                prioridad: 'media'
            );
        }

        // Notificar a RRHH de la empresa
        $usuariosRRHH = User::where('role', 'rrhh')
            ->where('empresa_id', $solicitud->empresa_id)
            ->where('active', true)
            ->get();

        foreach ($usuariosRRHH as $userRRHH) {
            $this->crear(
                userId: $userRRHH->id,
                tipo: 'contrato_generado',
                titulo: 'Contrato Listo para Firma',
                mensaje: "El contrato {$solicitud->codigo} está listo. Trabajador: {$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}",
                relacionadoTipo: SolicitudContrato::class,
                relacionadoId: $solicitud->id,
                prioridad: 'alta'
            );
        }
    }

    /**
     * Marca una notificación como leída
     */
    public function marcarComoLeida(Notificacion $notificacion): void
    {
        $notificacion->update([
            'leida' => true,
            'fecha_lectura' => now(),
        ]);
    }

    /**
     * Marca todas las notificaciones de un usuario como leídas
     */
    public function marcarTodasComoLeidas(int $userId): void
    {
        Notificacion::where('user_id', $userId)
            ->where('leida', false)
            ->update([
                'leida' => true,
                'fecha_lectura' => now(),
            ]);
    }

    /**
     * Obtiene las notificaciones no leídas de un usuario
     */
    public function obtenerNoLeidas(int $userId): Collection
    {
        return Notificacion::where('user_id', $userId)
            ->where('leida', false)
            ->orderBy('prioridad', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtiene el conteo de notificaciones no leídas
     */
    public function contarNoLeidas(int $userId): int
    {
        return Notificacion::where('user_id', $userId)
            ->where('leida', false)
            ->count();
    }

    /**
     * Obtiene todas las notificaciones de un usuario (paginadas)
     */
    public function obtenerTodas(int $userId, int $porPagina = 20)
    {
        return Notificacion::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($porPagina);
    }

    /**
     * Elimina notificaciones antiguas (más de 90 días y ya leídas)
     */
    public function limpiarNotificacionesAntiguas(int $dias = 90): int
    {
        return Notificacion::where('leida', true)
            ->where('created_at', '<', now()->subDays($dias))
            ->delete();
    }

    /**
     * Obtiene estadísticas de notificaciones
     */
    public function obtenerEstadisticas(int $userId): array
    {
        $total = Notificacion::where('user_id', $userId)->count();
        $noLeidas = $this->contarNoLeidas($userId);
        $urgentes = Notificacion::where('user_id', $userId)
            ->where('leida', false)
            ->where('prioridad', 'urgente')
            ->count();

        return [
            'total' => $total,
            'no_leidas' => $noLeidas,
            'urgentes' => $urgentes,
            'porcentaje_leidas' => $total > 0 ? round((($total - $noLeidas) / $total) * 100, 2) : 0,
        ];
    }
}
