<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ModificacionContractualResource\Pages;
use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Models\ModificacionContractual;
use App\Models\SolicitudContrato;
use App\Support\EmpresaActiva;
use App\Support\FormateoNumerico;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ModificacionContractualResource extends Resource
{
    protected static ?string $model = ModificacionContractual::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static ?string $navigationLabel = 'Modificaciones Contractuales';

    protected static ?string $modelLabel = 'Modificación Contractual';

    protected static ?string $pluralModelLabel = 'Modificaciones Contractuales';

    protected static ?string $navigationGroup = 'Gestión de Contratos';

    protected static ?int $navigationSort = 2;

    /** Mismas 6 opciones de SolicitudContratoResource::form() (tipo_contrato) - mantener sincronizadas si cambian ahí. */
    protected static function getTiposContrato(): array
    {
        return [
            'Contrato a Término Fijo' => 'Contrato a Término Fijo',
            'Contrato a Término Indefinido' => 'Contrato a Término Indefinido',
            'Contrato de Obra o Labor' => 'Contrato de Obra o Labor',
            'Contrato de Prestación de Servicios' => 'Contrato de Prestación de Servicios',
            'Contrato de Aprendizaje' => 'Contrato de Aprendizaje',
            'Contrato Ocasional o Transitorio' => 'Contrato Ocasional o Transitorio',
        ];
    }

    /**
     * Mapa tipo_modificacion -> columna real en SolicitudContrato. Usado por
     * Create/Edit para calcular valor_anterior.
     *
     * OJO: SolicitudContratoIAService::generarOtrosiPDF() tiene su PROPIA
     * copia idéntica de este mismo mapa (para actualizar el contrato
     * vigente al generar el PDF) - no la llama a este método (un Service no
     * debería depender de una clase de Filament\Admin\Resources). Si se
     * agrega un tipo_modificacion nuevo, hay que actualizar los 2 mapas a
     * mano, no solo este.
     */
    public static function campoPorTipo(): array
    {
        return [
            'salario'       => 'salario_propuesto',
            'cargo'         => 'cargo_contrato',
            'jornada'       => 'jornada',
            'tipo_contrato' => 'tipo_contrato',
        ];
    }

    /**
     * valor_anterior no lo edita el usuario directamente - se calcula del
     * dato VIGENTE en el contrato según el tipo_modificacion elegido.
     * Compartido entre Create y Edit: en Edit, el usuario puede cambiar
     * tipo_modificacion (el Wizard completo es editable), y sin recalcular
     * acá el valor_anterior quedaba pegado al tipo original - un dato
     * incorrecto que termina redactado en el otrosí real.
     */
    public static function calcularValorAnterior(?int $solicitudContratoId, ?string $tipoModificacion): ?string
    {
        if (!$solicitudContratoId || !$tipoModificacion) {
            return null;
        }

        $campo = self::campoPorTipo()[$tipoModificacion] ?? null;
        if (!$campo) {
            return null;
        }

        $solicitud = SolicitudContrato::find($solicitudContratoId);

        return $solicitud ? (string) $solicitud->{$campo} : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Wizard::make([
                Forms\Components\Wizard\Step::make('Contrato a Modificar')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\Select::make('solicitud_contrato_id')
                            ->label('Contrato')
                            ->relationship(
                                name: 'solicitudContrato',
                                titleAttribute: 'codigo',
                                // El scoping real ya lo hace el global scope de
                                // SolicitudContrato (ScopedToBufeteOrEmpresa): filtra
                                // a bufete por su(s) empresa(s), y no filtra nada para
                                // super_admin/abogado (ven todas las empresas, igual
                                // que en SolicitudContratoResource::form() con
                                // empresa_id). EmpresaActiva::id() solo se popula para
                                // bufete (SelectorEmpresa::permitidas()), así que este
                                // ->when() es un no-op siempre - se deja explícito por
                                // si en el futuro EmpresaActiva se habilita para otros
                                // roles, no porque hoy filtre algo.
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->whereIn('estado', ['contrato_generado', 'finalizado'])
                                    ->when(EmpresaActiva::id(), fn (Builder $q, int $empresaId) => $q->where('empresa_id', $empresaId)),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (SolicitudContrato $record): string =>
                                "{$record->codigo} — {$record->trabajador_nombres} {$record->trabajador_apellidos}"
                            )
                            ->searchable(['codigo', 'trabajador_nombres', 'trabajador_apellidos'])
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?int $state) {
                                if (!$state) {
                                    $set('empresa_id', null);
                                    return;
                                }
                                $solicitud = SolicitudContrato::find($state);
                                $set('empresa_id', $solicitud?->empresa_id);
                            })
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('resumen_contrato')
                            ->label('Datos vigentes del contrato')
                            ->content(function (Get $get) {
                                $solicitud = SolicitudContrato::find($get('solicitud_contrato_id'));
                                if (!$solicitud) {
                                    return 'Seleccione un contrato para ver sus datos vigentes.';
                                }

                                return sprintf(
                                    'Cargo: %s | Salario: $%s | Jornada: %s | Tipo: %s',
                                    $solicitud->cargo_contrato ?: '—',
                                    number_format((float) $solicitud->salario_propuesto, 0, ',', '.'),
                                    $solicitud->jornada ?: '—',
                                    $solicitud->tipo_contrato ?: '—',
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Wizard\Step::make('El Cambio')
                    ->icon('heroicon-o-pencil-square')
                    ->schema([
                        Forms\Components\Hidden::make('empresa_id'),

                        Forms\Components\Select::make('tipo_modificacion')
                            ->label('Tipo de Modificación')
                            ->options(ModificacionContractual::TIPOS)
                            ->required()
                            ->live()
                            ->native(false)
                            // Sin esto, cambiar de tipo (ej. de "salario" a "cargo")
                            // deja el valor anterior en el estado ("3.000.000")
                            // aunque ya no se vea en pantalla - el Select de "Nuevo
                            // Cargo" no tendría ninguna opción que calce con eso,
                            // pero ->required() solo valida "no vacío", así que se
                            // guardaría igual. Mismo patrón ya usado en
                            // SolicitudContratoResource para cargo_contrato/cargo_otro.
                            ->afterStateUpdated(fn (Set $set) => $set('valor_nuevo', null)),

                        Forms\Components\TextInput::make('valor_nuevo')
                            ->label('Nuevo Salario')
                            ->visible(fn (Get $get) => $get('tipo_modificacion') === 'salario')
                            ->required(fn (Get $get) => $get('tipo_modificacion') === 'salario')
                            // 150ms (no 500ms como antes) - mismo ajuste que
                            // SolicitudContratoResource, a pedido del usuario, para que el
                            // separador de miles aparezca casi en tiempo real al digitar.
                            ->live(debounce: '150ms')
                            // Sin afterStateHydrated a propósito: los 4 campos
                            // "valor_nuevo" (este + los 3 Select de abajo) comparten
                            // el mismo nombre de estado, así que un hydrator acá se
                            // dispararía SIEMPRE al abrir el formulario, sin importar
                            // la visibilidad - si el valor real fuera un cargo (texto),
                            // FormateoNumerico::miles() lo trataría como número y
                            // corrompería el dato antes de guardar.
                            //
                            // Guarda con Get $get: el mismo riesgo de arriba aplica
                            // también a afterStateUpdated, no solo a
                            // afterStateHydrated - HasState::callAfterStateUpdated()
                            // recorre TODOS los componentes con
                            // getComponents(withHidden: true) y ejecuta el
                            // afterStateUpdated del PRIMERO que coincida con el
                            // statePath 'valor_nuevo', sin importar cuál esté
                            // realmente visible. Como este TextInput de Salario es
                            // el primero de los 4 definido en el schema, su
                            // formateador numérico se disparaba SIEMPRE - cualquier
                            // usuario que eligiera cargo/jornada/tipo_contrato veía
                            // su valor real corrompido a null en cuanto lo escribía.
                            // Bug real confirmado con Livewire::test() + lectura del
                            // código fuente de Filament, no solo un hallazgo teórico.
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if ($get('tipo_modificacion') !== 'salario') {
                                    return;
                                }
                                $set('valor_nuevo', FormateoNumerico::miles($state));
                            })
                            ->stripCharacters('.')
                            ->rule('numeric')
                            ->minValue(0)
                            ->prefix('$'),

                        // Patrón "Otro" igual al de cargo_contrato/cargo_otro en
                        // SolicitudContratoResource, adaptado a un campo temporal
                        // propio (cargo_otro_temp) en vez de reusar cargo_otro,
                        // porque acá "valor_nuevo" ya es el nombre compartido por
                        // los 4 tipos - no se puede usar afterStateHydrated para
                        // detectar "es un valor personalizado" al editar (mismo
                        // riesgo ya documentado en el campo de salario: correría
                        // sin importar la visibilidad y podría malinterpretar el
                        // valor de otro tipo_modificacion). Al editar un registro
                        // con un cargo/jornada personalizado, el Select simplemente
                        // no mostrará ninguna opción marcada - limitación conocida,
                        // más segura que arriesgar corromper otros tipos.
                        Forms\Components\Select::make('valor_nuevo')
                            ->label('Nuevo Cargo')
                            ->visible(fn (Get $get) => $get('tipo_modificacion') === 'cargo')
                            ->required(fn (Get $get) => $get('tipo_modificacion') === 'cargo')
                            ->searchable()
                            ->options(function () {
                                $cargos = array_combine(SolicitudContratoResource::getCargos(), SolicitudContratoResource::getCargos());
                                $cargos['__otro__'] = '--- Otro (personalizado) ---';
                                return $cargos;
                            })
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('cargo_otro_temp', null))
                            ->dehydrateStateUsing(fn (Get $get, ?string $state) => $state === '__otro__' ? $get('cargo_otro_temp') : $state),

                        Forms\Components\TextInput::make('cargo_otro_temp')
                            ->label('Especifique el Cargo')
                            ->visible(fn (Get $get) => $get('tipo_modificacion') === 'cargo' && $get('valor_nuevo') === '__otro__')
                            ->required(fn (Get $get) => $get('tipo_modificacion') === 'cargo' && $get('valor_nuevo') === '__otro__')
                            ->placeholder('Ej: Jefe de Proyectos Especiales')
                            ->dehydrated(false),

                        Forms\Components\Select::make('valor_nuevo')
                            ->label('Nueva Jornada / Modalidad')
                            ->visible(fn (Get $get) => $get('tipo_modificacion') === 'jornada')
                            ->required(fn (Get $get) => $get('tipo_modificacion') === 'jornada')
                            ->options([
                                'Tiempo completo' => 'Tiempo completo',
                                'Medio tiempo' => 'Medio tiempo',
                                'Por horas' => 'Por horas',
                                '__otro__' => '--- Otro (personalizado) ---',
                            ])
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('jornada_otro_temp', null))
                            ->dehydrateStateUsing(fn (Get $get, ?string $state) => $state === '__otro__' ? $get('jornada_otro_temp') : $state),

                        Forms\Components\TextInput::make('jornada_otro_temp')
                            ->label('Especifique la Jornada')
                            ->visible(fn (Get $get) => $get('tipo_modificacion') === 'jornada' && $get('valor_nuevo') === '__otro__')
                            ->required(fn (Get $get) => $get('tipo_modificacion') === 'jornada' && $get('valor_nuevo') === '__otro__')
                            ->placeholder('Ej: Turnos rotativos')
                            ->dehydrated(false),

                        Forms\Components\Select::make('valor_nuevo')
                            ->label('Nuevo Tipo de Contrato')
                            ->visible(fn (Get $get) => $get('tipo_modificacion') === 'tipo_contrato')
                            ->required(fn (Get $get) => $get('tipo_modificacion') === 'tipo_contrato')
                            ->options(self::getTiposContrato()),

                        Forms\Components\Textarea::make('justificacion')
                            ->label('Justificación')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('fecha_efectiva')
                            ->label('Fecha Efectiva')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])->columns(2),
            ])
                ->columnSpanFull()
                ->persistStepInQueryString()
                // Sin esto, el último paso del Wizard queda con el slot de
                // envío vacío (Filament\Wizard::getSubmitAction() es null
                // por defecto) - el formulario no tendría ninguna forma de
                // enviarse, ya que CreateModificacionContractual también
                // quita los botones nativos de la página (mismo patrón ya
                // usado en SolicitudContratoResource).
                ->submitAction(new \Illuminate\Support\HtmlString('<button type="submit" class="filament-button filament-button-size-md inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset dark:focus:ring-offset-0 min-h-[2.25rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">Guardar Modificación</button>')),

            Forms\Components\Section::make('Otrosí')
                ->description('Redacción y documento final de la modificación')
                ->schema([
                    Forms\Components\View::make('filament.components.modificacion-contractual-ia-botones')
                        ->columnSpanFull(),

                    Forms\Components\RichEditor::make('texto_otrosi_redactado')
                        ->label('Texto del Otrosí')
                        ->toolbarButtons(['bold', 'bulletList', 'orderedList', 'italic', 'undo', 'redo'])
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('ruta_otrosi')
                        ->label('Otrosí Generado')
                        ->directory('solicitudes-contrato/otrosies')
                        ->disk('local')
                        ->downloadable()
                        ->openable()
                        ->columnSpanFull(),
                ])
                // 'view' además de 'create': esta sección tiene botones reales
                // (redactarOtrosiConIA()/generarOtrosiAction() vía wire:click crudo)
                // - la página "Ver" renderiza el form con ->disabled(), que no
                // neutraliza un View::make() con HTML embebido. Sin este fix esos
                // botones quedaban clicables ahí y llamaban métodos que
                // ViewModificacionContractual no define, tumbando la página con un
                // error de Livewire (mismo hallazgo aplicado a SolicitudContratoResource).
                ->hiddenOn(['create', 'view'])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('solicitudContrato.codigo')
                    ->label('Contrato')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('solicitudContrato.trabajador_nombres')
                    ->label('Trabajador')
                    ->formatStateUsing(fn ($record) => "{$record->solicitudContrato?->trabajador_nombres} {$record->solicitudContrato?->trabajador_apellidos}"),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('tipo_modificacion')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state) => ModificacionContractual::TIPOS[$state] ?? $state),

                Tables\Columns\TextColumn::make('valor_anterior')
                    ->label('Antes'),

                Tables\Columns\TextColumn::make('valor_nuevo')
                    ->label('Después'),

                Tables\Columns\TextColumn::make('fecha_efectiva')
                    ->label('Fecha Efectiva')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('estado')
                    ->colors(['secondary' => 'borrador', 'success' => 'otrosi_generado']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('empresa')
                    ->relationship('empresa', 'razon_social')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('tipo_modificacion')
                    ->options(ModificacionContractual::TIPOS),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // bufete tiene delete_any_modificacion::contractual (whitelist en
                // BufeteRoleSeeder.php) - sin esto el permiso no tenía forma de
                // usarse, Filament nunca muestra la acción sin bulkActions().
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('fecha_efectiva', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModificacionContractuals::route('/'),
            'create' => Pages\CreateModificacionContractual::route('/create'),
            'view' => Pages\ViewModificacionContractual::route('/{record}'),
            'edit' => Pages\EditModificacionContractual::route('/{record}/edit'),
        ];
    }
}
