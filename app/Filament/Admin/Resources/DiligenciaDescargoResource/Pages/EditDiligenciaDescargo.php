<?php

namespace App\Filament\Admin\Resources\DiligenciaDescargoResource\Pages;

use App\Filament\Admin\Resources\DiligenciaDescargoResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDiligenciaDescargo extends EditRecord
{
    protected static string $resource = DiligenciaDescargoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Cuando cambia la fecha de acceso, recalcular la expiración del token
        // para que NUNCA expire antes de que el trabajador pueda acceder.
        if (!empty($data['fecha_acceso_permitida'])) {
            $finDiaPermitido = Carbon::parse($data['fecha_acceso_permitida'])->endOfDay();
            $minimoTresDias  = now()->addDays(3)->endOfDay();
            $data['token_expira_en'] = $finDiaPermitido->max($minimoTresDias);
        }

        return $data;
    }
}
