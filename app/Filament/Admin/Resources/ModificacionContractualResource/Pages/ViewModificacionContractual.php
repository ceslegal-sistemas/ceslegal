<?php

namespace App\Filament\Admin\Resources\ModificacionContractualResource\Pages;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use App\Filament\Admin\Resources\ModificacionContractualResource\Widgets\ModificacionContractualRecordHeroWidget;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewModificacionContractual extends ViewRecord
{
    protected static string $resource = ModificacionContractualResource::class;

    // Vista custom: mismo lenguaje visual (.rit-info-card/.rit-hero) que Ver
    // Solicitud de Contrato, en vez del formulario deshabilitado por
    // defecto de Filament (que mostraba el Wizard crudo, sin sentido para
    // solo consultar un otrosí ya generado).
    protected static string $view = 'filament.admin.resources.modificacion-contractual-resource.pages.view-modificacion-contractual';

    protected function getHeaderWidgets(): array
    {
        return [
            ModificacionContractualRecordHeroWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return ['record' => $this->record];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Editar'),
        ];
    }
}
