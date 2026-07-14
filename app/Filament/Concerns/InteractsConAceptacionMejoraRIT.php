<?php

namespace App\Filament\Concerns;

use App\Services\AceptacionMejoraRITService;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Acción reutilizable "Actualizar RIT con las sugerencias": captura la declaración de
 * autoridad y los datos del responsable, y dispara la generación + adopción del RIT
 * mejorado. La usan la página de Auditar RIT y la vista unificada del cliente.
 *
 * La página que use este trait debe exponer `public ?\App\Models\AuditoriaRIT $auditoria`.
 */
trait InteractsConAceptacionMejoraRIT
{
    public function aceptarSugerenciasRITAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('aceptarSugerenciasRIT')
            ->label('Actualizar RIT con las sugerencias')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->modalHeading('Actualizar el Reglamento Interno con las sugerencias')
            ->modalDescription('La IA generará una versión corregida del RIT y, al confirmar, se adoptará como versión vigente. Registramos quién autoriza este cambio.')
            ->modalSubmitActionLabel('Confirmar y actualizar')
            ->modalWidth('lg')
            ->form([
                Placeholder::make('aviso')
                    ->hiddenLabel()
                    ->content('Esta acción reemplaza su Reglamento Interno vigente por la versión mejorada. Debe ser aprobada por alguien con autoridad para modificar el RIT de la empresa.'),
                Checkbox::make('autoridad_declarada')
                    ->label('Declaro que tengo la autoridad para aprobar cambios al Reglamento Interno de esta empresa.')
                    ->accepted()
                    ->required(),
                TextInput::make('responsable_nombre')
                    ->label('Nombre completo')
                    ->required()
                    ->maxLength(255),
                TextInput::make('responsable_documento')
                    ->label('Documento (cédula)')
                    ->required()
                    ->maxLength(50),
                TextInput::make('responsable_cargo')
                    ->label('Cargo')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                $auditoria = $this->auditoria ?? null;
                if (! $auditoria) {
                    Notification::make()->warning()->title('No hay auditoría activa')->send();
                    return;
                }

                $svc = app(AceptacionMejoraRITService::class);
                $svc->registrarAceptacion($auditoria, $data);
                $svc->dispararMejora($auditoria, (int) auth()->id());

                $this->auditoria = $auditoria->fresh();

                Notification::make()
                    ->success()
                    ->title('Actualización del RIT registrada')
                    ->body('La versión mejorada se adoptará como su Reglamento Interno vigente. Si aún se está generando, le avisaremos al terminar.')
                    ->send();
            });
    }
}
