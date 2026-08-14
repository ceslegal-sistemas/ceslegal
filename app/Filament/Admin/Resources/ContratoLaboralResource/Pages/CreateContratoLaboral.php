<?php

namespace App\Filament\Admin\Resources\ContratoLaboralResource\Pages;

use App\Filament\Admin\Resources\ContratoLaboralResource;
use App\Models\ContratoLaboral;
use App\Models\Trabajador;
use App\Support\EmpresaActiva;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;

class CreateContratoLaboral extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ContratoLaboralResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::can('create'), 403);
    }

    protected function getSteps(): array
    {
        return [
            Step::make('trabajador')
                ->label('Trabajador')
                ->schema([
                    Select::make('trabajador_id')
                        ->label('Trabajador')
                        ->options(function () {
                            $empresaId = EmpresaActiva::id();
                            return Trabajador::query()
                                ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
                                ->get()
                                ->mapWithKeys(fn ($t) => [$t->id => $t->nombre_completo])
                                ->all();
                        })
                        ->searchable()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('numero_documento')->label('Número de documento')->required(),
                            TextInput::make('nombres')->required(),
                            TextInput::make('apellidos')->required(),
                            TextInput::make('cargo')->required(),
                            TextInput::make('email')->email(),
                            TextInput::make('telefono'),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $empresaId = EmpresaActiva::id();
                            abort_unless($empresaId, 422, 'Seleccione una empresa en la barra superior primero.');

                            return Trabajador::create([
                                ...$data,
                                'empresa_id'     => $empresaId,
                                'tipo_documento' => 'C.C.',
                                'fecha_ingreso'  => now(),
                            ])->id;
                        }),
                ]),

            Step::make('tipo_contrato')
                ->label('Tipo de contrato')
                ->schema([
                    ToggleButtons::make('tipo')
                        ->label('Tipo de contrato')
                        ->options(ContratoLaboral::TIPOS)
                        ->inline()
                        ->required()
                        ->live(),

                    DatePicker::make('fecha_inicio')
                        ->label('Fecha de inicio')
                        ->required()
                        ->default(now()),

                    DatePicker::make('fecha_fin')
                        ->label('Fecha de fin')
                        ->visible(fn (Get $get) => $get('tipo') === 'fijo')
                        ->required(fn (Get $get) => $get('tipo') === 'fijo')
                        ->helperText('Máximo 4 años (Art. 46 CST, modificado por la Ley 2466 de 2025).'),

                    Textarea::make('descripcion_obra')
                        ->label('Descripción de la obra o labor')
                        ->visible(fn (Get $get) => $get('tipo') === 'obra_labor')
                        ->required(fn (Get $get) => $get('tipo') === 'obra_labor'),
                ]),

            Step::make('datos_contrato')
                ->label('Datos del contrato')
                ->schema([
                    TextInput::make('salario')
                        ->label('Salario')
                        ->numeric()
                        ->prefix('$')
                        ->required(),

                    Select::make('periodicidad_pago')
                        ->label('Periodicidad de pago')
                        ->options([
                            'mensual'   => 'Mensual',
                            'quincenal' => 'Quincenal',
                        ])
                        ->default('mensual')
                        ->required(),

                    TextInput::make('jornada')
                        ->label('Jornada')
                        ->placeholder('Ej: Lunes a viernes, 8:00am - 5:00pm'),

                    Textarea::make('funciones_cargo')
                        ->label('Funciones del cargo')
                        ->required()
                        ->helperText('Este texto es el insumo principal para que la IA redacte el objeto del contrato en el siguiente paso.')
                        ->rows(4),

                    Section::make('Seguridad social (opcional)')
                        ->collapsed()
                        ->schema([
                            TextInput::make('eps')->label('EPS'),
                            TextInput::make('arl')->label('ARL'),
                            TextInput::make('fondo_pension')->label('Fondo de pensión'),
                            TextInput::make('caja_compensacion')->label('Caja de compensación'),
                        ]),
                ]),

            // Paso 4 se agrega en Task 9...
        ];
    }
}
