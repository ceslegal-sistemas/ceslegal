<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BufeteResource\Pages;
use App\Filament\Admin\Resources\BufeteResource\RelationManagers\AbogadosRelationManager;
use App\Filament\Admin\Resources\BufeteResource\RelationManagers\EmpresasRelationManager;
use App\Models\Bufete;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Administración de bufetes (firmas de abogados externas que gestionan los procesos
 * disciplinarios de varias empresas clientes). Solo para super_admin.
 */
class BufeteResource extends Resource
{
    protected static ?string $model = Bufete::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Bufetes';

    protected static ?string $modelLabel = 'Bufete';

    protected static ?string $pluralModelLabel = 'Bufetes';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 2;

    /** Solo el super administrador gestiona los bufetes (evita depender de policies). */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del bufete')
                ->description('Firma de abogados externa que atiende a varias empresas clientes.')
                ->icon('heroicon-o-briefcase')
                ->schema([
                    Forms\Components\TextInput::make('nombre')
                        ->label('Nombre del bufete')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Ej: Abogados Asociados S.A.S')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('nit')
                        ->label('NIT')
                        ->maxLength(50)
                        ->placeholder('Ej: 900123456-7'),

                    Forms\Components\TextInput::make('representante')
                        ->label('Representante')
                        ->maxLength(255)
                        ->placeholder('Nombre del representante'),

                    Forms\Components\TextInput::make('email_contacto')
                        ->label('Correo de contacto')
                        ->email()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('telefono')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(30),

                    Forms\Components\Toggle::make('active')
                        ->label('Bufete activo')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Desactive si el bufete ya no opera en la plataforma.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Bufete')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-briefcase'),

                Tables\Columns\TextColumn::make('nit')
                    ->label('NIT')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('representante')
                    ->label('Representante')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('empresas_count')
                    ->label('Empresas')
                    ->counts('empresas')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('email_contacto')
                    ->label('Contacto')
                    ->toggleable()
                    ->icon('heroicon-o-envelope'),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('nombre');
    }

    public static function getRelations(): array
    {
        return [
            EmpresasRelationManager::class,
            AbogadosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBufetes::route('/'),
            'create' => Pages\CreateBufete::route('/create'),
            'view'   => Pages\ViewBufete::route('/{record}'),
            'edit'   => Pages\EditBufete::route('/{record}/edit'),
        ];
    }
}
