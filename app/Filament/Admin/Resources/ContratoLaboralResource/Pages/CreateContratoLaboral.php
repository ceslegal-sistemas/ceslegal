<?php

namespace App\Filament\Admin\Resources\ContratoLaboralResource\Pages;

use App\Filament\Admin\Resources\ContratoLaboralResource;
use App\Models\ContratoLaboral as ContratoLaboralModel;
use App\Models\Trabajador;
use App\Services\ContratoLaboralGeneratorService;
use App\Support\EmpresaActiva;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\View as FormView;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasWizard;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\HtmlString;

class CreateContratoLaboral extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ContratoLaboralResource::class;

    public ?int $contratoBorradorId = null;

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::can('create'), 403);
    }

    protected function getSteps(): array
    {
        return [
            // ── Paso 1 (Task 6) — Trabajador ──────────────────────────────
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

            // ── Paso 2 (Task 7) — Tipo de contrato ────────────────────────
            Step::make('tipo_contrato')
                ->label('Tipo de contrato')
                ->schema([
                    ToggleButtons::make('tipo')
                        ->label('Tipo de contrato')
                        ->options(ContratoLaboralModel::TIPOS)
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

            // ── Paso 3 (Task 8) — Datos del contrato ──────────────────────
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

            // ── Paso 4 (Task 9) — Generar cláusulas con IA ────────────────
            // Botón como View + wire:click contra un método público real
            // (generarClausulas() abajo) - NO una Filament Action anidada en
            // el schema: ese patrón no tiene precedente en este proyecto y
            // no es testeable de forma simple con Livewire::test().
            Step::make('generar_ia')
                ->label('Generar cláusulas')
                ->schema([
                    FormView::make('filament.components.contrato-generar-clausulas')
                        ->viewData(fn () => ['contratoBorradorId' => $this->contratoBorradorId]),

                    Placeholder::make('clausulas_preview')
                        ->label('Cláusulas generadas')
                        ->content(function () {
                            if (!$this->contratoBorradorId) {
                                return 'Haga clic en "Generar cláusulas con IA" arriba.';
                            }
                            $contrato = ContratoLaboralModel::find($this->contratoBorradorId);
                            return new HtmlString(nl2br(e($contrato->clausulas_generadas ?? '')));
                        }),
                ]),

            // ── Paso 5 (Task 9) — Revisión y generación final ─────────────
            Step::make('revision')
                ->label('Revisión y generación')
                ->schema([
                    Placeholder::make('resumen')
                        ->label('Resumen del contrato')
                        ->content(function (Get $get) {
                            $trabajador = Trabajador::find($get('trabajador_id'));
                            $tipoLabel  = ContratoLaboralModel::TIPOS[$get('tipo')] ?? $get('tipo');
                            return new HtmlString(
                                "<strong>Trabajador:</strong> {$trabajador?->nombre_completo}<br>" .
                                "<strong>Tipo:</strong> {$tipoLabel}<br>" .
                                "<strong>Salario:</strong> \${$get('salario')}"
                            );
                        }),
                ]),
        ];
    }

    /**
     * Dispara desde el botón del Paso 4 (ver vista
     * contrato-generar-clausulas.blade.php). Crea/actualiza el registro
     * "borrador" con los datos ya capturados en los pasos 1-3
     * ($this->form->getState() trae el estado completo del wizard, no solo
     * del paso actual - HasWizard usa un único form subyacente) y llama a
     * la IA para redactar las cláusulas.
     */
    public function generarClausulas(): void
    {
        $data = $this->form->getState();

        $campos = [
            'trabajador_id'      => $data['trabajador_id'] ?? null,
            'empresa_id'         => EmpresaActiva::id(),
            'tipo'               => $data['tipo'] ?? null,
            'salario'            => $data['salario'] ?? null,
            'periodicidad_pago'  => $data['periodicidad_pago'] ?? null,
            'jornada'            => $data['jornada'] ?? null,
            'funciones_cargo'    => $data['funciones_cargo'] ?? null,
            'fecha_inicio'       => $data['fecha_inicio'] ?? null,
            'fecha_fin'          => $data['fecha_fin'] ?? null,
            'descripcion_obra'   => $data['descripcion_obra'] ?? null,
            'eps'                => $data['eps'] ?? null,
            'arl'                => $data['arl'] ?? null,
            'fondo_pension'      => $data['fondo_pension'] ?? null,
            'caja_compensacion'  => $data['caja_compensacion'] ?? null,
        ];

        $contrato = $this->contratoBorradorId
            ? ContratoLaboralModel::find($this->contratoBorradorId)
            : ContratoLaboralModel::create($campos + ['estado' => 'borrador']);

        if ($this->contratoBorradorId) {
            $contrato->update($campos);
        }

        $this->contratoBorradorId = $contrato->id;

        app(ContratoLaboralGeneratorService::class)->generarClausulas($contrato);

        Notification::make()
            ->success()
            ->title('Cláusulas generadas')
            ->send();
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // El registro "borrador" ya existe (creado en el Paso 4 al generar
        // cláusulas) - el submit final actualiza sus datos y genera el PDF,
        // no crea uno nuevo.
        abort_unless($this->contratoBorradorId, 422, 'Debe generar las cláusulas antes de continuar.');

        $contrato = ContratoLaboralModel::find($this->contratoBorradorId);
        $contrato->update($data);

        app(ContratoLaboralGeneratorService::class)->generarPDF($contrato);

        return $contrato->fresh();
    }
}
