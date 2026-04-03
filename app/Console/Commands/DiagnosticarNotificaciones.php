<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificacionService;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnosticarNotificaciones extends Command
{
    protected $signature = 'notificaciones:diagnosticar {email? : Email del usuario a diagnosticar} {--send : Enviar una notificación de prueba}';
    protected $description = 'Diagnostica el estado de notificaciones Filament de un usuario';

    public function handle(): void
    {
        $email = $this->argument('email') ?? 'admin@ceslegal.co';
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Usuario no encontrado: {$email}");
            return;
        }

        $this->info("=== Diagnóstico de notificaciones para: {$user->email} ===");
        $this->line("Usuario ID: {$user->id} | Rol: {$user->role}");
        $this->newLine();

        // Contar en la tabla notifications (estándar Laravel)
        $totalNotifs = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->count();

        $unreadNotifs = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->count();

        // Contar con filtro Filament (data->format = 'filament')
        $filamentTotal = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.format')) = 'filament'")
            ->count();

        $filamentUnread = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.format')) = 'filament'")
            ->count();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total notificaciones (tabla notifications)', $totalNotifs],
                ['No leídas (todas)', $unreadNotifs],
                ['Total con format=filament', $filamentTotal],
                ['No leídas con format=filament (visible en campanita)', $filamentUnread],
            ]
        );

        if ($filamentTotal > 0) {
            $this->newLine();
            $this->line('Últimas 3 notificaciones Filament:');
            $notifs = DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', User::class)
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.format')) = 'filament'")
                ->orderByDesc('created_at')
                ->take(3)
                ->get();

            foreach ($notifs as $n) {
                $data = json_decode($n->data, true);
                $readStatus = $n->read_at ? '✓ leída' : '● NO LEÍDA';
                $title = $data['title'] ?? 'sin título';
                $this->line("  [{$readStatus}] {$title} — {$n->created_at}");
            }
        }

        if ($this->option('send')) {
            $this->newLine();
            $this->info('Enviando notificación de prueba...');

            Notification::make()
                ->title('🔔 Prueba de notificación')
                ->body('Esta es una notificación de diagnóstico enviada el ' . now()->format('d/m/Y H:i:s'))
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->sendToDatabase($user);

            $newCount = DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', User::class)
                ->whereNull('read_at')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.format')) = 'filament'")
                ->count();

            $this->info("Notificación enviada. Ahora hay {$newCount} notificaciones no leídas (Filament).");
            $this->line("La campanita debería mostrar el número {$newCount} en los próximos 30 segundos (polling).");
        } else {
            $this->newLine();
            $this->line('Tip: usa --send para enviar una notificación de prueba.');
        }
    }
}
