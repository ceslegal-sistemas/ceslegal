<?php

namespace App\Filament\Admin\Resources\CorreoEnviadoResource\Pages;

use App\Filament\Admin\Resources\CorreoEnviadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCorreoEnviado extends ViewRecord
{
    protected static string $resource = CorreoEnviadoResource::class;

    public function getView(): string
    {
        return 'filament.admin.resources.correo-enviado-resource.pages.view-correo-enviado';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('volver')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(CorreoEnviadoResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function getViewData(): array
    {
        $correo   = $this->record;
        $uaInfo   = $correo->parsearUserAgent();
        $adjuntos = collect($correo->adjuntos ?? [])
            ->map(fn ($path) => basename($path))
            ->all();

        return [
            'correo'   => $correo,
            'uaInfo'   => $uaInfo,
            'adjuntos' => $adjuntos,
        ];
    }
}
