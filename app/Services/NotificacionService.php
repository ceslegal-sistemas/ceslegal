<?php

namespace App\Services;

use App\Models\User;
use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
use App\Models\SolicitudCambioEmpresa;
use App\Notifications\ProcesoNotification;
use Filament\Notifications\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
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

        // Determinar URL según el destinatario real
        $url = ProcesoNotification::determinarUrlParaUsuario($relacionadoTipo, $relacionadoId, $user);

        // Icono según tipo
        $icono = match ($tipo) {
            'apertura'               => 'heroicon-o-document-plus',
            'descargos_pendientes'   => 'heroicon-o-clock',
            'descargos_realizados'   => 'heroicon-o-check-circle',
            'termino_vencido'        => 'heroicon-o-exclamation-triangle',
            'sancion_emitida'        => 'heroicon-o-shield-exclamation',
            'impugnacion_realizada'  => 'heroicon-o-arrow-path',
            'cerrado'                => 'heroicon-o-check-badge',
            'contrato_generado'      => 'heroicon-o-document-check',
            'solicitud_cambio_empresa' => 'heroicon-o-pencil-square',
            'sugerencia_actualizacion_rit' => 'heroicon-o-document-text',
            default                  => 'heroicon-o-bell',
        };

        // Color según prioridad
        $color = match ($prioridad) {
            'urgente' => 'danger',
            'alta'    => 'warning',
            'media'   => 'info',
            'baja'    => 'success',
            default   => 'info',
        };

        // Enviar como notificación Filament (visible en la campanita)
        $notif = FilamentNotification::make()
            ->title($titulo)
            ->body($mensaje)
            ->icon($icono)
            ->iconColor($color);

        if ($url) {
            $notif->actions([
                FilamentAction::make('ver')
                    ->label('Ver')
                    ->url($url)
                    ->button(),
            ]);
        }

        $notif->sendToDatabase($user);
    }

    /**
     * Notifica a super_admin cada vez que se crea un nuevo proceso disciplinario
     */
    public function notificarSuperAdminNuevoProceso(ProcesoDisciplinario $proceso): void
    {
        $sinAbogado = !$proceso->abogado_id;

        $superAdmins = User::where('role', 'super_admin')
            ->where('active', true)
            ->get();

        // Si no hay super_admin con active=true, buscar sin filtro de active
        if ($superAdmins->isEmpty()) {
            $superAdmins = User::where('role', 'super_admin')->get();
        }

        foreach ($superAdmins as $admin) {
            $this->crear(
                userId: $admin->id,
                tipo: 'apertura',
                titulo: $sinAbogado
                    ? 'Nuevo Proceso'
                    : 'Nuevo Proceso Disciplinario Creado',
                mensaje: $sinAbogado
                    ? "Se creó el proceso {$proceso->codigo} para {$proceso->trabajador->nombre_completo} (empresa: {$proceso->empresa->razon_social})."
                    : "Se creó el proceso {$proceso->codigo} para {$proceso->trabajador->nombre_completo} y fue asignado al abogado.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: $sinAbogado ? 'alta' : 'baja'
            );
        }
    }

    /**
     * Notifica cuando se apertura un proceso disciplinario
     */
    public function notificarProcesoAperturado(ProcesoDisciplinario $proceso): void
    {
        if (!$proceso->abogado_id) {
            return;
        }

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
        if (!$proceso->abogado_id) {
            return;
        }

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
        // Notificar al abogado (solo si hay uno asignado)
        if ($proceso->abogado_id) {
            $this->crear(
                userId: $proceso->abogado_id,
                tipo: 'sancion_emitida',
                titulo: 'Sanción Aplicada',
                mensaje: "Se ha aplicado una sanción de tipo {$tipoSancion} en el proceso {$proceso->codigo}.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'alta'
            );
        }

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

        // Notificar a super_admin
        $superAdmins = User::where('role', 'super_admin')->get();
        foreach ($superAdmins as $admin) {
            $this->crear(
                userId: $admin->id,
                tipo: 'sancion_emitida',
                titulo: 'Sanción Emitida',
                mensaje: "Se emitió sanción ({$tipoSancion}) en el proceso {$proceso->codigo} - {$proceso->trabajador->nombre_completo}.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'media'
            );
        }
    }

    /**
     * Notifica cuando se completan los descargos
     */
    public function notificarDescargosCompletados(ProcesoDisciplinario $proceso): void
    {
        // Notificar al abogado (solo si hay uno asignado)
        if ($proceso->abogado_id) {
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

        // Notificar al cliente que generó el proceso
        $usuarioCliente = User::where('role', 'cliente')
            ->where('empresa_id', $proceso->empresa_id)
            ->where('active', true)
            ->get();

        foreach ($usuarioCliente as $cliente) {
            $this->crear(
                userId: $cliente->id,
                tipo: 'descargos_realizados',
                titulo: 'Trabajador Completó Descargos',
                mensaje: "El trabajador {$proceso->trabajador->nombre_completo} ha completado la diligencia de descargos del proceso {$proceso->codigo}. Puede revisar las respuestas y evidencias aportadas.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'alta'
            );
        }
    }

    /**
     * Notifica cuando se envía la citación al trabajador (estado descargos_pendientes)
     */
    public function notificarCitacionEnviada(ProcesoDisciplinario $proceso): void
    {
        $fecha = $proceso->fecha_descargos_programada
            ? $proceso->fecha_descargos_programada->format('d/m/Y') .
              ($proceso->hora_descargos_programada ? ' ' . \Carbon\Carbon::parse($proceso->hora_descargos_programada)->format('H:i') : '')
            : 'por confirmar';

        $usuariosCliente = User::where('role', 'cliente')
            ->where('empresa_id', $proceso->empresa_id)
            ->where('active', true)
            ->get();

        foreach ($usuariosCliente as $cliente) {
            $this->crear(
                userId: $cliente->id,
                tipo: 'descargos_pendientes',
                titulo: 'Citación de Descargos Enviada',
                mensaje: "Se envió la citación al trabajador {$proceso->trabajador->nombre_completo} para el proceso {$proceso->codigo}. Fecha programada: {$fecha}. El proceso estará en espera hasta que el trabajador realice la diligencia.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'media'
            );
        }
    }

    /**
     * Notifica cuando se recibe una impugnación
     */
    public function notificarImpugnacionRecibida(ProcesoDisciplinario $proceso): void
    {
        // Notificar al abogado
        if ($proceso->abogado_id) {
            $this->crear(
                userId: $proceso->abogado_id,
                tipo: 'impugnacion_realizada',
                titulo: 'Impugnación Recibida - Requiere Acción',
                mensaje: "El trabajador {$proceso->trabajador->nombre_completo} ha impugnado la sanción del proceso {$proceso->codigo}. Debe revisar y resolver la impugnación.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'urgente'
            );
        }

        // Notificar al cliente de la empresa
        $usuariosCliente = User::where('role', 'cliente')
            ->where('empresa_id', $proceso->empresa_id)
            ->where('active', true)
            ->get();

        foreach ($usuariosCliente as $cliente) {
            $this->crear(
                userId: $cliente->id,
                tipo: 'impugnacion_realizada',
                titulo: 'Trabajador Impugnó la Sanción',
                mensaje: "El trabajador {$proceso->trabajador->nombre_completo} ha impugnado la sanción del proceso {$proceso->codigo}. LUPE Legal está revisando la impugnación.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'alta'
            );
        }

        // Notificar a super_admin (urgente - requiere revisión)
        $superAdmins = User::where('role', 'super_admin')->get();
        foreach ($superAdmins as $admin) {
            $this->crear(
                userId: $admin->id,
                tipo: 'impugnacion_realizada',
                titulo: 'Impugnación Recibida - Revisión Requerida',
                mensaje: "El trabajador {$proceso->trabajador->nombre_completo} impugnó la sanción del proceso {$proceso->codigo}.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'urgente'
            );
        }
    }

    /**
     * Notifica cuando se cierra un proceso
     */
    public function notificarProcesoCerrado(ProcesoDisciplinario $proceso): void
    {
        // Notificar al abogado (solo si hay uno asignado)
        if ($proceso->abogado_id) {
            $this->crear(
                userId: $proceso->abogado_id,
                tipo: 'cerrado',
                titulo: 'Proceso Disciplinario Cerrado',
                mensaje: "El proceso {$proceso->codigo} ha sido cerrado exitosamente.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'media'
            );
        }

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
     * Notifica a bufete y cliente de la empresa afectada que hay una
     * sugerencia de actualización del RIT lista para aprobar (Plan B de
     * actualización automática del RIT). Primer método de este servicio
     * que notifica al rol bufete - la relación real es
     * Empresa.bufete_id -> Bufete, y los usuarios bufete son
     * User::where('role','bufete')->where('bufete_id', $bufete->id).
     * Si la empresa no tiene bufete asignado (bufete_id null), se
     * notifica solo a cliente - no es un error, es un caso válido.
     */
    public function notificarSugerenciaActualizacionRit(\App\Models\SugerenciaActualizacionRit $sugerencia): void
    {
        $empresa = $sugerencia->empresa;

        $destinatarios = User::where('empresa_id', $empresa->id)
            ->where('active', true)
            ->where('role', 'cliente')
            ->get();

        if ($empresa->bufete_id) {
            $destinatarios = $destinatarios->merge(
                User::where('bufete_id', $empresa->bufete_id)
                    ->where('active', true)
                    ->where('role', 'bufete')
                    ->get()
            );
        }

        foreach ($destinatarios as $user) {
            $this->crear(
                userId: $user->id,
                tipo: 'sugerencia_actualizacion_rit',
                // Lenguaje más claro y accionable, pedido explícito del usuario
                // (2026-09-02): antes decía "un ajuste puntual" - la campana no
                // bastaba sola, se acumularon 14 sugerencias sin revisar en 14
                // empresas en producción. Complementa el banner del Dashboard
                // (dashboard-sugerencias-rit-notice.blade.php), que es lo
                // primero que ve el cliente al entrar, sin depender de que
                // abra la campana.
                titulo: 'Hay una actualización legal que aplica a su Reglamento Interno',
                mensaje: "Salió normativa nueva y ya identificamos los cambios puntuales que le corresponden a su RIT: {$sugerencia->justificacion_ia} ¿Desea actualizarlo?",
                relacionadoTipo: \App\Models\SugerenciaActualizacionRit::class,
                relacionadoId: $sugerencia->id,
                prioridad: 'urgente'
            );
        }
    }

    /** Usuarios cliente de la empresa + bufete asignado (si tiene) - mismo criterio ya usado en notificarSugerenciaActualizacionRit(). */
    private function destinatariosDeEmpresa(\App\Models\Empresa $empresa): Collection
    {
        $destinatarios = User::where('empresa_id', $empresa->id)
            ->where('active', true)
            ->where('role', 'cliente')
            ->get();

        if ($empresa->bufete_id) {
            $destinatarios = $destinatarios->merge(
                User::where('bufete_id', $empresa->bufete_id)
                    ->where('active', true)
                    ->where('role', 'bufete')
                    ->get()
            );
        }

        return $destinatarios;
    }

    /**
     * Contrato a término fijo dentro de la ventana de alerta (45 días por
     * defecto, ver PlazoContratoService) - da margen para gestionar el
     * preaviso de 30 días que exige la ley antes de que se cumpla el plazo.
     */
    public function notificarContratoPorVencer(SolicitudContrato $solicitud): void
    {
        $dias = app(PlazoContratoService::class)->diasHastaVencimiento($solicitud);
        $nombreTrabajador = trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}");

        foreach ($this->destinatariosDeEmpresa($solicitud->empresa) as $user) {
            $this->crear(
                userId: $user->id,
                tipo: 'contrato_por_vencer',
                titulo: 'Un contrato a término fijo está por vencer',
                mensaje: "El contrato de {$nombreTrabajador} ({$solicitud->codigo}) vence en {$dias} días. ¿Desea renovarlo?",
                relacionadoTipo: SolicitudContrato::class,
                relacionadoId: $solicitud->id,
                prioridad: 'alta'
            );
        }
    }

    /**
     * Nadie decidió a tiempo y el Art. 46 CST renovó el contrato solo -
     * prioridad urgente para que la próxima vez se gestione antes.
     */
    public function notificarRenovacionAutomatica(SolicitudContrato $solicitud): void
    {
        $nombreTrabajador = trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}");
        $nuevaFecha = $solicitud->fecha_fin_contrato?->format('d/m/Y') ?? 'sin definir';

        foreach ($this->destinatariosDeEmpresa($solicitud->empresa) as $user) {
            $this->crear(
                userId: $user->id,
                tipo: 'contrato_renovado_automaticamente',
                titulo: 'Un contrato se renovó automáticamente',
                mensaje: "No se gestionó a tiempo el contrato de {$nombreTrabajador} ({$solicitud->codigo}) y la ley lo renovó automáticamente. Nueva fecha de vencimiento: {$nuevaFecha}.",
                relacionadoTipo: SolicitudContrato::class,
                relacionadoId: $solicitud->id,
                prioridad: 'urgente'
            );
        }
    }

    /**
     * La renovación automática superaría el tope legal de 4 años - caso
     * raro y de alto riesgo legal, no se aplica sola, requiere revisión
     * humana inmediata.
     */
    public function notificarRevisionManualRenovacion(SolicitudContrato $solicitud): void
    {
        $nombreTrabajador = trim("{$solicitud->trabajador_nombres} {$solicitud->trabajador_apellidos}");

        foreach ($this->destinatariosDeEmpresa($solicitud->empresa) as $user) {
            $this->crear(
                userId: $user->id,
                tipo: 'contrato_requiere_revision_manual',
                titulo: 'Un contrato necesita su revisión urgente',
                mensaje: "El contrato de {$nombreTrabajador} ({$solicitud->codigo}) no se pudo renovar automáticamente porque superaría el límite legal de 4 años. Revíselo cuanto antes.",
                relacionadoTipo: SolicitudContrato::class,
                relacionadoId: $solicitud->id,
                prioridad: 'urgente'
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

    /**
     * Notifica cuando el trabajador no realiza los descargos
     */
    public function notificarDescargosNoRealizados(ProcesoDisciplinario $proceso): void
    {
        // Notificar al abogado (solo si hay uno asignado)
        if ($proceso->abogado_id) {
            $this->crear(
                userId: $proceso->abogado_id,
                tipo: 'descargos_no_realizados',
                titulo: 'Trabajador No Presentó Descargos',
                mensaje: "El trabajador {$proceso->trabajador->nombre_completo} NO asistió a la diligencia de descargos del proceso {$proceso->codigo}. Puede proceder a emitir la sanción correspondiente.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'alta'
            );
        }

        // Notificar a los usuarios cliente de la empresa
        $usuariosCliente = User::where('role', 'cliente')
            ->where('empresa_id', $proceso->empresa_id)
            ->where('active', true)
            ->get();

        foreach ($usuariosCliente as $cliente) {
            $this->crear(
                userId: $cliente->id,
                tipo: 'descargos_no_realizados',
                titulo: 'Trabajador No Presentó Descargos',
                mensaje: "El trabajador {$proceso->trabajador->nombre_completo} NO asistió a la diligencia de descargos del proceso {$proceso->codigo}. El proceso continuará según el procedimiento establecido.",
                relacionadoTipo: ProcesoDisciplinario::class,
                relacionadoId: $proceso->id,
                prioridad: 'alta'
            );
        }
    }

    /**
     * Notifica a todos los super_admin cuando un cliente solicita cambiar un
     * dato legal bloqueado de su empresa (razón social, NIT, etc.)
     */
    public function notificarSolicitudCambioEmpresa(SolicitudCambioEmpresa $solicitud): void
    {
        $superAdmins = User::where('role', 'super_admin')->where('active', true)->get();
        if ($superAdmins->isEmpty()) {
            $superAdmins = User::where('role', 'super_admin')->get();
        }

        foreach ($superAdmins as $admin) {
            $this->crear(
                userId: $admin->id,
                tipo: 'solicitud_cambio_empresa',
                titulo: 'Solicitud de cambio de datos de empresa',
                mensaje: "{$solicitud->empresa->razon_social} solicitó cambiar un dato legal: \"{$solicitud->mensaje}\"",
                relacionadoTipo: SolicitudCambioEmpresa::class,
                relacionadoId: $solicitud->id,
                prioridad: 'media'
            );
        }
    }

    /**
     * Notifica al cliente que solicitó el cambio si fue aprobado o rechazado.
     */
    public function notificarResultadoSolicitudCambioEmpresa(SolicitudCambioEmpresa $solicitud): void
    {
        $aprobada = $solicitud->estado === 'aprobada';

        $this->crear(
            userId: $solicitud->user_id,
            tipo: 'solicitud_cambio_empresa',
            titulo: $aprobada ? 'Su solicitud de cambio fue aprobada' : 'Su solicitud de cambio fue rechazada',
            mensaje: $aprobada
                ? 'Actualizamos los datos de su empresa según lo solicitado.'
                : 'No se pudo aprobar su solicitud. Motivo: ' . ($solicitud->motivo_rechazo ?: 'no especificado'),
            prioridad: $aprobada ? 'baja' : 'media'
        );
    }
}
