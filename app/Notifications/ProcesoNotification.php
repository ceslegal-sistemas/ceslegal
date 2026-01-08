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
        $data = [
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

        // Agregar acciones solo si hay URL
        if ($this->url) {
            $data['actions'] = [
                [
                    'name' => 'view',
                    'color' => 'primary',
                    'url' => $this->url,
                    'label' => 'Ver',
                ]
            ];
        }

        return $data;
    }

    /**
     * Determinar la URL según el tipo de relacionado
     */
    public static function determinarUrl(?string $relacionadoTipo, ?int $relacionadoId): ?string
    {
        if (!$relacionadoTipo || !$relacionadoId) {
            return null;
        }

        return match ($relacionadoTipo) {
            'App\Models\ProcesoDisciplinario' => url('/admin/proceso-disciplinarios/' . $relacionadoId . '/edit'),
            'App\Models\SolicitudContrato' => url('/admin/solicitud-contratos/' . $relacionadoId . '/edit'),
            default => null,
        };
    }
}