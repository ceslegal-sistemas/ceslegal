<?php

namespace App\Filament\Admin\Resources\ContratoLaboralResource\Pages;

use App\Filament\Admin\Resources\ContratoLaboralResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewContratoLaboral extends ViewRecord
{
    protected static string $resource = ContratoLaboralResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::can('view', $this->record), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => !empty($this->record->documento_path))
                ->action(fn () => response()->download(
                    Storage::disk('local')->path($this->record->documento_path)
                )),
        ];
    }
}
