<?php

namespace App\Filament\Admin\Resources\InformeJuridicoResource\Pages;

use App\Filament\Admin\Resources\InformeJuridicoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInformeJuridico extends EditRecord
{
    protected static string $resource = InformeJuridicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Descomponer tiempo_minutos → horas + minutos para el formulario
        $total = (int) ($data['tiempo_minutos'] ?? 0);
        $data['tiempo_horas'] = intdiv($total, 60);
        $data['tiempo_mins']  = $total % 60;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Recombinar horas + minutos → tiempo_minutos
        $horas   = (int) ($data['tiempo_horas'] ?? 0);
        $minutos = (int) ($data['tiempo_mins']  ?? 0);
        $data['tiempo_minutos'] = ($horas * 60) + $minutos;
        unset($data['tiempo_horas'], $data['tiempo_mins']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
