<?php

namespace App\Filament\Admin\Resources\BibliotecaLegalResource\Pages;

use App\Filament\Admin\Resources\BibliotecaLegalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBibliotecaLegal extends EditRecord
{
    protected static string $resource = BibliotecaLegalResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Si se reemplaza el archivo de un documento ya procesado, resetear estado a
     * 'pendiente' - sin esto, DocumentoLegalObserver nunca se entera del cambio
     * (exige wasChanged('estado')) y el contenido nuevo queda huérfano: nunca se
     * re-extrae, re-fragmenta, ni se vuelve a comparar contra ningún RIT. Mismo
     * reseteo que ya hace la acción explícita "Reprocesar" de la tabla/listado.
     */
    protected bool $archivoReemplazado = false;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (
            array_key_exists('archivo_path', $data)
            && $data['archivo_path'] !== $this->record->archivo_path
        ) {
            $this->archivoReemplazado = true;
            $data['estado'] = 'pendiente';
            $data['error_mensaje'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->archivoReemplazado) {
            \App\Jobs\ProcesarBibliotecaLegal::dispatch($this->record);

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Documento actualizado - reprocesando')
                ->body('Se reemplazó el archivo, así que se está volviendo a extraer y analizar el contenido en segundo plano.')
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('previsualizar')
                ->label('Previsualizar')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->visible(fn() => !empty($this->record->archivo_path))
                ->modalHeading(fn() => $this->record->titulo)
                ->modalWidth(\Filament\Support\Enums\MaxWidth::SevenExtraLarge)
                ->modalContent(fn() => BibliotecaLegalResource::buildPreviewContent($this->record))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),

            Actions\Action::make('descargar')
                ->label('Descargar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn() => !empty($this->record->archivo_path))
                ->url(fn() => route('biblioteca.descargar', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('ver_impacto')
                ->label('Ver impacto')
                ->icon('heroicon-o-building-office-2')
                ->color('gray')
                ->visible(fn() => BibliotecaLegalResource::tieneSugerencias($this->record))
                ->modalHeading('Impacto de este documento en los Reglamentos')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::Large)
                ->modalContent(fn() => view('filament.components.biblioteca-legal-impacto', ['documento' => $this->record]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),

            Actions\DeleteAction::make()
                ->label('Eliminar documento')
                ->visible(fn() => !BibliotecaLegalResource::tieneSugerencias($this->record))
                ->before(function () {
                    // Misma defensa adicional que en la tabla - ver comentario ahí.
                    BibliotecaLegalResource::bloquearSiTieneSugerencias(collect([$this->record]));
                }),

            Actions\Action::make('desactivar_bloqueado')
                ->label('Desactivar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => $this->record->activo && BibliotecaLegalResource::tieneSugerencias($this->record))
                ->requiresConfirmation()
                ->modalHeading('Desactivar documento')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::Large)
                ->modalContent(fn() => view('filament.components.biblioteca-legal-impacto', [
                    'documento' => $this->record,
                    'intro' => 'No se puede eliminar sin perder la trazabilidad legal de estos cambios. Al desactivarlo, deja de usarse para nuevas sugerencias, pero conserva el historial de lo que ya cambió en cada empresa.',
                ]))
                ->modalSubmitActionLabel('Desactivar')
                ->action(function () {
                    $this->record->update(['activo' => false]);
                    $this->record->refresh();
                    \Filament\Notifications\Notification::make()->success()->title('Documento desactivado')->send();
                }),

            Actions\Action::make('activar')
                ->label('Activar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => !$this->record->activo)
                ->action(function () {
                    $this->record->update(['activo' => true]);
                    $this->record->refresh();
                    \Filament\Notifications\Notification::make()->success()->title('Documento activado')->send();
                }),
        ];
    }
}
