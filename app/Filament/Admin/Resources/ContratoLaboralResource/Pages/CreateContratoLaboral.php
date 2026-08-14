<?php

namespace App\Filament\Admin\Resources\ContratoLaboralResource\Pages;

use App\Filament\Admin\Resources\ContratoLaboralResource;
use App\Models\Trabajador;
use App\Support\EmpresaActiva;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard\Step;
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

            // Paso 2 se agrega en Task 7...
        ];
    }
}
