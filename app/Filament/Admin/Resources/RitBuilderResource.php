<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RitBuilderResource\Pages;
use App\Models\RitBuilder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class RitBuilderResource extends Resource
{
    protected static ?string $model = RitBuilder::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRitBuilders::route('/'),
            'create' => Pages\CreateRitBuilder::route('/create'),
            'edit'   => Pages\EditRitBuilder::route('/{record}/edit'),
        ];
    }
}
