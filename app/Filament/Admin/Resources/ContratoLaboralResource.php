<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ContratoLaboralResource\Pages;
use App\Models\ContratoLaboral;
use App\Support\EmpresaActiva;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContratoLaboralResource extends Resource
{
    protected static ?string $model = ContratoLaboral::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Contratos Laborales';
    protected static ?string $navigationGroup = 'Lupe Organiza';
    protected static ?string $modelLabel = 'Contrato Laboral';
    protected static ?string $pluralModelLabel = 'Contratos Laborales';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // ScopedToBufeteOrEmpresa NO aplica aquí (ContratoLaboral no lo usa,
        // ver spec) - filtro explícito a la empresa activa del selector,
        // igual que MiReglamentoInterno/AuditarRIT/CreateReglamentoInterno.
        $query = parent::getEloquentQuery();

        if ($empresaId = EmpresaActiva::id()) {
            $query->where('empresa_id', $empresaId);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        // Los campos reales viven en CreateContratoLaboral (wizard) - este
        // form() base queda vacío a propósito, igual que otros Resources de
        // este proyecto cuyo Create real usa getSteps()/HasWizard.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('trabajador.nombre_completo')
                    ->label('Trabajador')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state) => ContratoLaboral::TIPOS[$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('salario')
                    ->label('Salario')
                    ->money('COP'),
                Tables\Columns\TextColumn::make('estado')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'generado', 'activo' => 'success',
                        'terminado' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListContratosLaborales::route('/'),
            'create' => Pages\CreateContratoLaboral::route('/create'),
            'view'   => Pages\ViewContratoLaboral::route('/{record}'),
        ];
    }
}
