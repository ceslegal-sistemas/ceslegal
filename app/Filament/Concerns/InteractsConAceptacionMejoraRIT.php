<?php

namespace App\Filament\Concerns;

use App\Services\AceptacionMejoraRITService;
use App\Services\RitDiffService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Acción reutilizable "Actualizar RIT con las sugerencias": captura la declaración de
 * autoridad, los datos del responsable y su verificación fotográfica (equivalencia
 * funcional de firma manuscrita, Ley 527/1999 Art. 7-8 y Decreto 2364/2012), y dispara
 * la generación + adopción del RIT mejorado. La usan la página de Auditar RIT y la
 * vista unificada del cliente.
 *
 * La página que use este trait debe exponer `public ?\App\Models\AuditoriaRIT $auditoria`.
 */
trait InteractsConAceptacionMejoraRIT
{
    use HasVerificacionFotografica;

    public function aceptarSugerenciasRITAction(): Action
    {
        return Action::make('aceptarSugerenciasRIT')
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

                Placeholder::make('foto_verificacion')
                    ->label('Verificación fotográfica')
                    ->helperText('Equivalencia funcional de su firma: confirma que usted, y no otra persona, autoriza este cambio.')
                    ->content(fn () => view('filament.components.webcam-autorizador', [
                        'wireTargetPath' => 'mountedActionsData.0.foto_responsable_base64',
                    ])),
                Hidden::make('foto_responsable_base64'),
            ])
            ->action(function (array $data, Action $action): void {
                $auditoria = $this->auditoria ?? null;
                if (! $auditoria) {
                    Notification::make()->warning()->title('No hay auditoría activa')->send();
                    return;
                }

                // La foto es un campo oculto (base64): si falta, el error de "requerido"
                // no se mostraría al usuario. Se valida aquí con un aviso claro.
                if (empty($data['foto_responsable_base64'])) {
                    Notification::make()
                        ->danger()
                        ->title('Falta la verificación fotográfica')
                        ->body('Debe tomar la foto de verificación antes de continuar.')
                        ->persistent()
                        ->send();

                    $action->halt();
                }

                $data['responsable_foto_path'] = $this->guardarFotoVerificacion(
                    $data['foto_responsable_base64'] ?? null,
                    "fotos-verificacion/rit/{$auditoria->id}",
                );

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

    /**
     * "Ver cambios": redline entre el RIT original y el mejorado (verde=agregado,
     * rojo=eliminado, amarillo=modificado), con las mismas acciones de decisión al
     * pie del modal para que revisar y aceptar/mantener ocurran en un solo lugar.
     */
    public function verCambiosRITAction(): Action
    {
        return Action::make('verCambiosRIT')
            ->label('Ver cambios')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading('Cambios propuestos en el Reglamento Interno')
            ->modalDescription('Verde: se agregó. Rojo: se eliminó. Amarillo: se modificó.')
            ->modalWidth('4xl')
            ->modalContent(function () {
                $auditoria = $this->auditoria ?? null;
                $original  = $auditoria?->reglamento?->texto_completo;
                $mejorado  = $this->ritMejorado?->texto_completo ?? $auditoria?->reglamentoMejorado?->texto_completo;

                $cambios = (!empty($original) && !empty($mejorado))
                    ? app(RitDiffService::class)->compararDocumentos($original, $mejorado)
                    : [];

                return view('filament.components.rit-redline', ['cambios' => $cambios]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(fn (): array => [
                $this->aceptarSugerenciasRITAction(),
                $this->mantenerRITAction(),
            ]);
    }

    /** Envuelve mantenerRITActual() (ya implementado en cada página) como Action, para el pie del modal de "Ver cambios". */
    public function mantenerRITAction(): Action
    {
        return Action::make('mantenerRIT')
            ->label('Mantener el actual')
            ->color('gray')
            ->outlined()
            ->requiresConfirmation()
            ->modalHeading('¿Mantener su Reglamento Interno actual?')
            ->modalDescription('La versión mejorada quedará archivada; podrá adoptarla más adelante si cambia de opinión.')
            ->action(fn () => $this->mantenerRITActual());
    }
}
