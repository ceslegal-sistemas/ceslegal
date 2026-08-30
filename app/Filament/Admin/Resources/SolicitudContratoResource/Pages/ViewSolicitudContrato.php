<?php

namespace App\Filament\Admin\Resources\SolicitudContratoResource\Pages;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Widgets\SolicitudContratoRecordHeroWidget;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSolicitudContrato extends ViewRecord
{
    protected static string $resource = SolicitudContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Editar'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SolicitudContratoRecordHeroWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return ['record' => $this->record];
    }
}
