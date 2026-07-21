<?php

namespace App\Filament\Admin\Resources\ArticuloLegalResource\Pages;

use App\Filament\Admin\Resources\ArticuloLegalResource;
use App\Filament\Admin\Resources\ArticuloLegalResource\Widgets\ProgresoScraperCstWidget;
use App\Jobs\ActualizarArticulosCstJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;

class ListArticuloLegals extends ListRecords
{
    protected static string $resource = ArticuloLegalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('actualizarArticulosCst')
                ->label('Actualizar artículos legales')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Actualizar artículos legales')
                ->modalDescription('Vuelve a descargar los artículos del Código Sustantivo del Trabajo desde la fuente y regenera los que falten, para que la auditoría y generación de RIT usen la normativa vigente. Tarda varios minutos; puede seguir usando el sistema mientras corre.')
                ->modalSubmitActionLabel('Actualizar')
                ->action(function () {
                    $progreso = Cache::get(ActualizarArticulosCstJob::CACHE_KEY);
                    if (($progreso['estado'] ?? null) === 'procesando') {
                        Notification::make()
                            ->warning()
                            ->title('Ya hay una actualización en curso')
                            ->body('Espere a que termine antes de iniciar otra.')
                            ->send();
                        return;
                    }

                    ActualizarArticulosCstJob::dispatch(auth()->id());

                    Notification::make()
                        ->success()
                        ->title('Actualización iniciada')
                        ->body('Descargando artículos del CST en segundo plano. Puede seguir en esta página para ver el avance.')
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ProgresoScraperCstWidget::class,
        ];
    }
}
