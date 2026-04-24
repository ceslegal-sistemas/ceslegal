<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReglamentoInternoResource\Pages;
use App\Models\ReglamentoInterno;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class ReglamentoInternoResource extends Resource
{
    protected static ?string $model = ReglamentoInterno::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    // Oculto del menú lateral — solo accesible por enlace directo
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'create' => Pages\CreateReglamentoInterno::route('/create'),
        ];
    }
}
