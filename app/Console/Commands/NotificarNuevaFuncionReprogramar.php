<?php

namespace App\Console\Commands;

use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class NotificarNuevaFuncionReprogramar extends Command
{
    protected $signature   = 'notificar:reprogramar-citacion {--todos : Enviar también a super_admin y abogado}';
    protected $description = 'Notifica sobre la nueva función Reprogramar Citación';

    public function handle(): void
    {
        $usuarios = $this->option('todos')
            ? User::all()
            : User::role('cliente')->get();

        if ($usuarios->isEmpty()) {
            $this->warn('No se encontraron usuarios.');
            return;
        }

        Notification::make()
            ->title('Nueva función: Reprogramar Citación')
            ->body('Ya puedes reprogramar la diligencia de descargos cuando el trabajador no la realizó. Búscala en el Historial de Descargos cuando el estado sea "Descargo No Realizado".')
            ->icon('heroicon-o-calendar')
            ->iconColor('primary')
            ->actions([
                Action::make('ver')
                    ->label('Ir al Historial')
                    ->url('/admin/proceso-disciplinarios')
                    ->button(),
            ])
            ->sendToDatabase($usuarios);

        $this->info("Notificación enviada a {$usuarios->count()} usuario(s).");
    }
}
