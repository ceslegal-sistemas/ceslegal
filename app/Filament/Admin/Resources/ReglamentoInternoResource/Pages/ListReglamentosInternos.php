<?php

namespace App\Filament\Admin\Resources\ReglamentoInternoResource\Pages;

use App\Filament\Admin\Resources\ReglamentoInternoResource;
use Filament\Resources\Pages\ListRecords;

class ListReglamentosInternos extends ListRecords
{
    protected static string $resource = ReglamentoInternoResource::class;

    public function mount(): void
    {
        $this->redirect(static::getResource()::getUrl('create'));
    }
}
