<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\Actions\Action;

class ProcesoNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $tipo,
        public string $titulo,
        public string $mensaje,
        public string $prioridad = 'media',
        public ?string $relacionadoTipo = null,
        public ?int $relacionadoId = null,
        public ?string $url = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification (para la tabla notifications).
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the array representation of the notification para la base de datos.
     */
    public function toArray(object $notifiable): array
    {
        // Determinar icono según tipo
        $icono = match ($this->tipo) {
            'apertura' => 'heroicon-o-document-plus',
            'descargos_pendientes' => 'heroicon-o-clock',
            'descargos_realizados' => 'heroicon-o-check-circle',
            'termino_vencido' => 'heroicon-o-exclamation-triangle',
            'sancion_emitida' => 'heroicon-o-shield-exclamation',
            'impugnacion_realizada' => 'heroicon-o-arrow-path',
            'cerrado' => 'heroicon-o-check-badge',
            'contrato_generado' => 'heroicon-o-document-check',
            default => 'heroicon-o-bell',
        };

        // Determinar color según prioridad
        $color = match ($this->prioridad) {
            'urgente' => 'danger',
            'alta' => 'warning',
            'media' => 'info',
            'baja' => 'success',
            default => 'info',
        };

        // Formato para Filament Database Notifications
        // IMPORTANTE: 'format' => 'filament' es requerido por Filament 3.3+
        // para que aparezcan en la campanita (filtra por data->format = 'filament')
        // IMPORTANTE: 'duration' => 'persistent' evita que Filament las elimine
        // automáticamente (el default es 6000ms y las borra de la BD)
        $data = [
            'format' => 'filament',
            'duration' => 'persistent',
            'title' => $this->titulo,
            'body' => $this->mensaje,
            'icon' => $icono,
            'iconColor' => $color,
            // Datos adicionales para referencia
            'tipo' => $this->tipo,
            'prioridad' => $this->prioridad,
            'relacionado_tipo' => $this->relacionadoTipo,
            'relacionado_id' => $this->relacionadoId,
        ];

        // Determinar URL según el destinatario real (no auth())
        $url = self::determinarUrlParaUsuario($this->relacionadoTipo, $this->relacionadoId, $notifiable)
            ?? $this->url;

        // Agregar acciones solo si hay URL
        if ($url) {
            $data['actions'] = [
                [
                    'name' => 'view',
                    'color' => 'primary',
                    'url' => $url,
                    'label' => 'Ver',
                ]
            ];
        }

        return $data;
    }

    /**
     * Determinar la URL según el destinatario real de la notificación.
     */
    public static function determinarUrlParaUsuario(?string $relacionadoTipo, ?int $relacionadoId, ?object $notifiable = null): ?string
    {
        if (!$relacionadoTipo || !$relacionadoId) {
            return null;
        }

        $esCliente = method_exists($notifiable, 'hasRole')
            ? $notifiable->hasRole('cliente')
            : false;

        return match ($relacionadoTipo) {
            'App\Models\ProcesoDisciplinario' => $esCliente
                ? url('/admin/proceso-disciplinarios/' . $relacionadoId)
                : url('/admin/proceso-disciplinarios/' . $relacionadoId . '/edit'),
            'App\Models\SolicitudContrato' => $esCliente
                ? url('/admin/solicitud-contratos/' . $relacionadoId)
                : url('/admin/solicitud-contratos/' . $relacionadoId . '/edit'),
            default => null,
        };
    }

    /**
     * @deprecated Usar determinarUrlParaUsuario()
     */
    public static function determinarUrl(?string $relacionadoTipo, ?int $relacionadoId): ?string
    {
        return self::determinarUrlParaUsuario($relacionadoTipo, $relacionadoId, auth()->user());
    }
}