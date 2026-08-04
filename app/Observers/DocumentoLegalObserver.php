<?php

namespace App\Observers;

use App\Models\DocumentoLegal;
use App\Models\ReglamentoInterno;
use App\Models\User;
use Filament\Notifications\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;

class DocumentoLegalObserver
{
    /**
     * Cuando un documento legal pasa a estado 'procesado', notifica a los usuarios
     * cliente de las empresas que tienen un RIT activo para que re-auditen.
     */
    public function updated(DocumentoLegal $documento): void
    {
        // Solo reaccionar cuando el estado cambia a 'procesado' por primera vez
        if (!$documento->wasChanged('estado')
            || $documento->estado !== 'procesado'
            || !$documento->activo) {
            return;
        }

        // Idempotencia: ignorar si ya estaba procesado antes
        if ($documento->getOriginal('estado') === 'procesado') {
            return;
        }

        // Empresas con al menos un RIT activo
        $empresaIds = ReglamentoInterno::where('activo', true)
            ->distinct()
            ->pluck('empresa_id');

        if ($empresaIds->isEmpty()) {
            return;
        }

        // Notificar a usuarios 'cliente' activos de esas empresas
        $usuarios = User::whereIn('empresa_id', $empresaIds)
            ->where('active', true)
            ->where('role', 'cliente')
            ->get();

        foreach ($usuarios as $user) {
            FilamentNotification::make()
                ->title('Nueva normativa disponible: ' . $documento->titulo)
                ->body('Recomendamos auditar su RIT para verificar el cumplimiento con la normativa actualizada.')
                ->icon('heroicon-o-scale')
                ->iconColor('warning')
                ->actions([
                    // Antes apuntaba a /admin/auditar-r-i-t, pero esa página está oculta
                    // para el rol 'cliente' (usa la vista unificada "Mi Reglamento
                    // Interno" - ver AuditarRIT::shouldRegisterNavigation()). El query
                    // param resalta el botón "Auditar ahora" / "Volver a auditar" al llegar.
                    FilamentAction::make('auditar')
                        ->label('Auditar RIT')
                        ->url(url('/empresa/mi-reglamento-interno') . '?resaltar=auditar')
                        ->button(),
                ])
                ->sendToDatabase($user);
        }
    }
}
