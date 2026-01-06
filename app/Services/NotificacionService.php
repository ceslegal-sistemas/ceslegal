<?php

namespace App\Services;

use App\Models\User;
use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
use App\Notifications\ProcesoNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificacionService
{
    /**
     * Crea una notificación usando el sistema nativo de Laravel
     */
    public function crear(
        int $userId,
        string $tipo,
        string $titulo,
        string $mensaje,
        ?string $relacionadoTipo = null,
        ?int $relacionadoId = null,
        string $prioridad = 'media'
    ): void {
        $user = User::find($userId);

        if (!$user) {
            Log::warning('Usuario no encontrado al intentar enviar notificación', [
                'user_id' => $userId,
                'tipo' => $tipo,
            ]);
            return;
        }

        // Determinar URL automáticamente
        $url = ProcesoNotification::determinarUrl($relacionadoTipo, $relacionadoId);

        // Enviar notificación de Laravel (se guarda en tabla notifications)
        $user->notify(new ProcesoNotification(
            tipo: $tipo,
            titulo: $titulo,
            mensaje: $mensaje,
            prioridad: $prioridad,
            relacionadoTipo: $relacionadoTipo,
            relacionadoId: $relacionadoId,
            url: $url,
        ));
    }

    /**
     * Notifica cuando se apertura un proceso disciplinario
     */
    public function notificarProcesoAperturado(ProcesoDisciplinario $proceso): void
    {
        $this->crear(
            userId: $proceso->abogado_id,
            tipo: 'apertura',
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
            tipo: 'descargos_pendientes',
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
            tipo: 'sancion_emitida',
            titulo: 'Sanción Aplicada',
            mensaje: "Se ha aplicado una sanción de tipo {$tipoSancion} en el proceso {$proceso->codigo}.",
            relacionadoTipo: ProcesoDisciplinario::class,
            relacionadoId: $proceso->id,
            prioridad: 'alta'
        );

        // Notificar a RRHH (usuarios con rol rrhh de la misma empresa)
        $usuariosRRHH = User::where('role', 'cliente')
            ->where('empresa_id', $proceso->empresa_id)
            ->where('active', true)
            ->get();

        foreach ($usuariosRRHH as $userRRHH) {
            $this->crear(
                userId: $userRRHH->id,
                tipo: 'sancion_emitida',
                titulo: 'Sanción Aplicada - Requiere Acción de RRHH',
                mensaje: "Se aplicó {$tipoSancion} al trabajador {$proceso->trabajador->nombre_completo}. Proceso: {$proceso->codigo}",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'urgente'
            );
        }
    }

    /**
     * Notifica cuando se completan los descargos
     */
    public function notificarDescargosCompletados(ProcesoDisciplinario $proceso): void
    {
        $this->crear(
            userId: $proceso->abogado_id,
            tipo: 'descargos_realizados',
            titulo: 'Descargos Completados',
            mensaje: "El trabajador {$proceso->trabajador->nombre_completo} completó la diligencia de descargos del proceso {$proceso->codigo}. Debe emitir la sanción correspondiente.",
            relacionadoTipo: ProcesoDisciplinario::class,
            relacionadoId: $proceso->id,
            prioridad: 'alta'
        );
    }

    /**
     * Notifica cuando se recibe una impugnación
     */
    public function notificarImpugnacionRecibida(ProcesoDisciplinario $proceso): void
    {
        $this->crear(
            userId: $proceso->abogado_id,
            tipo: 'impugnacion_realizada',
            titulo: 'Impugnación Recibida',
            mensaje: "Se ha recibido una impugnación para el proceso {$proceso->codigo}. Requiere análisis urgente.",
            relacionadoTipo: ProcesoDisciplinario::class,
            relacionadoId: $proceso->id,
            prioridad: 'urgente'
        );
    }

    /**
     * Notifica cuando se cierra un proceso
     */
    public function notificarProcesoCerrado(ProcesoDisciplinario $proceso): void
    {
        // Notificar al abogado
        $this->crear(
            userId: $proceso->abogado_id,
            tipo: 'cerrado',
            titulo: 'Proceso Disciplinario Cerrado',
            mensaje: "El proceso {$proceso->codigo} ha sido cerrado exitosamente.",
            relacionadoTipo: ProcesoDisciplinario::class,
            relacionadoId: $proceso->id,
            prioridad: 'media'
        );

        // Notificar a RRHH de la empresa
        $usuariosRRHH = User::where('role', 'cliente')
            ->where('empresa_id', $proceso->empresa_id)
            ->where('active', true)
            ->get();

        foreach ($usuariosRRHH as $userRRHH) {
            $this->crear(
                userId: $userRRHH->id,
                tipo: 'cerrado',
                titulo: 'Proceso Disciplinario Cerrado',
                mensaje: "El proceso {$proceso->codigo} del trabajador {$proceso->trabajador->nombre_completo} ha sido cerrado.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'baja'
            );
        }
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
        $usuariosRRHH = User::where('role', 'cliente')
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
    public function marcarComoLeida(string $notificationId): void
    {
        $notification = DB::table('notifications')
            ->where('id', $notificationId)
            ->first();

        if ($notification) {
            DB::table('notifications')
                ->where('id', $notificationId)
                ->update(['read_at' => now()]);
        }
    }

    /**
     * Marca todas las notificaciones de un usuario como leídas
     */
    public function marcarTodasComoLeidas(int $userId): void
    {
        DB::table('notifications')
            ->where('notifiable_id', $userId)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Obtiene las notificaciones no leídas de un usuario
     */
    public function obtenerNoLeidas(int $userId): Collection
    {
        $user = User::find($userId);

        if (!$user) {
            return collect([]);
        }

        return $user->unreadNotifications;
    }

    /**
     * Obtiene el conteo de notificaciones no leídas
     */
    public function contarNoLeidas(int $userId): int
    {
        $user = User::find($userId);

        if (!$user) {
            return 0;
        }

        return $user->unreadNotifications()->count();
    }

    /**
     * Obtiene todas las notificaciones de un usuario
     */
    public function obtenerTodas(int $userId): Collection
    {
        $user = User::find($userId);

        if (!$user) {
            return collect([]);
        }

        return $user->notifications;
    }

    /**
     * Elimina notificaciones antiguas (más de 90 días y ya leídas)
     */
    public function limpiarNotificacionesAntiguas(int $dias = 90): int
    {
        return DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($dias))
            ->delete();
    }

    /**
     * Obtiene estadísticas de notificaciones
     */
    public function obtenerEstadisticas(int $userId): array
    {
        $user = User::find($userId);

        if (!$user) {
            return [
                'total' => 0,
                'no_leidas' => 0,
                'urgentes' => 0,
                'porcentaje_leidas' => 0,
            ];
        }

        $total = $user->notifications()->count();
        $noLeidas = $user->unreadNotifications()->count();

        // Contar urgentes (desde el campo data->prioridad)
        $urgentes = $user->unreadNotifications()
            ->whereRaw("JSON_EXTRACT(data, '$.prioridad') = 'urgente'")
            ->count();

        return [
            'total' => $total,
            'no_leidas' => $noLeidas,
            'urgentes' => $urgentes,
            'porcentaje_leidas' => $total > 0 ? round((($total - $noLeidas) / $total) * 100, 2) : 0,
        ];
    }
}
