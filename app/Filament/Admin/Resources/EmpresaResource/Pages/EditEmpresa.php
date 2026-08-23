<?php

namespace App\Filament\Admin\Resources\EmpresaResource\Pages;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Models\ActividadEconomica;
use App\Models\SolicitudCambioEmpresa;
use App\Services\NotificacionService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\View as FormView;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class EditEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;

    // Vista custom: oculta el stepper nativo de Filament (misma clase
    // ces-hide-wizard-steps que ya usa el wizard de citación de descargos) -
    // este wizard usa su propio encabezado "Paso X de Y" (step-header).
    protected static string $view = 'filament.admin.resources.empresas.pages.edit-empresa';

    public function getTitle(): string
    {
        // Para el cliente es "su" empresa; para staff, el nombre de la empresa.
        if (auth()->user()?->hasRole('cliente')) {
            return 'Mi empresa';
        }

        return $this->record->razon_social ?? parent::getTitle();
    }

    public function getBreadcrumb(): string
    {
        return auth()->user()?->hasRole('cliente') ? 'Mi empresa' : parent::getBreadcrumb();
    }

    protected function getHeaderActions(): array
    {
        return [
            // La visibilidad la controla Shield (permiso delete_empresa vía policy).
            Actions\DeleteAction::make(),
        ];
    }

    // Se quita la barra de acciones estándar de EditRecord (quedaba "por
    // fuera" del wizard, visible en todos los pasos). "Guardar cambios" y
    // "Cancelar" ahora viven dentro del propio wizard, en el pie del último
    // paso (ver ->submitAction() más abajo).
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * Wizard de 5 pasos (una sola pantalla, siempre editable - ya no hay
     * "Ver" separado, ver ViewEmpresa eliminado). Reutiliza los mismos campos
     * de EmpresaResource::formSchema() para los pasos 1-4 (copiados aquí, no
     * se toca formSchema() para no afectar el wizard de alta en CreateEmpresa),
     * con el header visual real (step-header.blade.php) que ya usan los
     * wizards de RIT y de creación de citación de descargos. El paso 5 no
     * duplica el uploader de RIT - solo resume el estado y enlaza a
     * "Mi Reglamento Interno".
     */
    public function form(Form $form): Form
    {
        return $form->schema([
            Wizard::make([
                Step::make('datos')
                    ->label('Datos de la Empresa')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        FormView::make('filament.components.step-header')
                            ->key('me_step_header_datos')
                            ->viewData([
                                'step' => 1,
                                'total' => 5,
                                'title' => 'Datos de la Empresa',
                                'accent' => '#e11d48',
                                'lord' => 'https://cdn.lordicon.com/moedrfvp.json',
                                'subtitle' => 'Razón social, tipo societario, NIT y estado de la empresa.',
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Section::make()
                            ->key('me_section_datos')
                            ->headerActions([
                                Forms\Components\Actions\Action::make('solicitar_cambio')
                                    ->label('Solicitar cambio')
                                    ->icon('heroicon-o-pencil-square')
                                    ->color('gray')
                                    ->link()
                                    ->visible(fn() => auth()->user()?->isCliente() ?? false)
                                    ->form([
                                        Forms\Components\Textarea::make('mensaje')
                                            ->label('¿Qué dato quiere cambiar y a qué valor?')
                                            ->placeholder('Ej: La razón social debe decir "MI EMPRESA S.A.S." en vez de "MI EMPRESA SAS"')
                                            ->required()
                                            ->rows(3),
                                    ])
                                    ->modalHeading('Solicitar cambio de datos de la empresa')
                                    ->modalSubmitActionLabel('Enviar solicitud')
                                    ->action(function (array $data): void {
                                        $solicitud = SolicitudCambioEmpresa::create([
                                            'empresa_id' => $this->record->id,
                                            'user_id' => auth()->id(),
                                            'mensaje' => $data['mensaje'],
                                            'estado' => 'pendiente',
                                        ]);

                                        app(NotificacionService::class)->notificarSolicitudCambioEmpresa($solicitud);

                                        \Filament\Notifications\Notification::make()
                                            ->success()
                                            ->title('Solicitud enviada')
                                            ->body('Le avisaremos cuando sea revisada.')
                                            ->send();
                                    }),
                            ])
                            ->schema([
                                Forms\Components\TextInput::make('razon_social')
                                    ->label('Razón Social')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ej: EMPRESA ABC')
                                    ->helperText(fn() => (auth()->user()?->isCliente() ?? false)
                                        ? 'Nombre legal sin tipo societario. Para corregirlo, contacte a soporte.'
                                        : 'Nombre legal sin tipo societario')
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->disabled(fn() => auth()->user()?->isCliente() ?? false)
                                    ->columnSpan(['default' => 1, 'sm' => 2]),

                                Forms\Components\Select::make('tipo_societario')
                                    ->label('Tipo Societario')
                                    ->options(\App\Models\Empresa::TIPOS_SOCIETARIOS)
                                    ->searchable()
                                    ->placeholder('Seleccione...')
                                    ->helperText(fn() => (auth()->user()?->isCliente() ?? false)
                                        ? 'Forma jurídica. Para corregirla, contacte a soporte.'
                                        : 'Forma jurídica')
                                    ->live()
                                    ->disabled(fn() => auth()->user()?->isCliente() ?? false),

                                Forms\Components\TextInput::make('nit')
                                    ->label('NIT')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    // Sin ->mask(): ver nota en EmpresaResource.php - un mask fijo
                                    // obligaba a escribir exactamente 9 dígitos antes del guion.
                                    ->placeholder(fn(Get $get) => ($get('tipo_societario') && $get('tipo_societario') !== 'Persona Natural')
                                        ? 'Ej: 900123456-7'
                                        : 'Ej: 1023456789')
                                    ->helperText(fn(Get $get) => (auth()->user()?->isCliente() ?? false)
                                        ? 'Para corregirlo, contacte a soporte.'
                                        : (($get('tipo_societario') && $get('tipo_societario') !== 'Persona Natural')
                                            ? 'Incluya el dígito de verificación separado por guion. Los NIT antiguos pueden tener menos de 9 dígitos antes del guion.'
                                            : 'Número de cédula de ciudadanía'))
                                    ->rules(fn(Get $get) => ($get('tipo_societario') && $get('tipo_societario') !== 'Persona Natural')
                                        ? ['regex:/^\d{6,12}-\d$/']
                                        : [])
                                    ->validationMessages(['regex' => 'El NIT debe incluir el dígito de verificación separado por guion (ej: 900123456-7). Puede tener entre 6 y 12 dígitos antes del guion.'])
                                    ->suffixIcon('heroicon-o-identification')
                                    ->disabled(fn() => auth()->user()?->isCliente() ?? false),

                                Forms\Components\TextInput::make('representante_legal')
                                    ->label('Representante Legal')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ej: Juan Pérez García')
                                    ->helperText(fn() => (auth()->user()?->isCliente() ?? false)
                                        ? 'Nombre del representante legal. Para corregirlo, contacte a soporte.'
                                        : 'Nombre del representante legal')
                                    ->suffixIcon('heroicon-o-user')
                                    ->disabled(fn() => auth()->user()?->isCliente() ?? false),

                                Forms\Components\TextInput::make('representante_legal_cedula')
                                    ->label('Cédula del Representante Legal')
                                    ->maxLength(50)
                                    ->placeholder('Ej: 1234567890')
                                    ->helperText('Necesaria para generar contratos de trabajo a término fijo')
                                    ->suffixIcon('heroicon-o-identification')
                                    ->disabled(fn() => auth()->user()?->isCliente() ?? false),

                                Forms\Components\Toggle::make('active')
                                    ->label('Empresa Activa')
                                    ->default(true)
                                    ->helperText('Desactive si la empresa ya no está en servicio')
                                    ->inline(false)
                                    // Solo bufete/super_admin/abogado deciden si una empresa
                                    // queda activa - un cliente no puede auto-desactivarse.
                                    ->disabled(fn() => auth()->user()?->isCliente() ?? false),

                                Forms\Components\Hidden::make('dias_laborales')
                                    ->default('lunes_viernes'),
                            ])->columns(['default' => 1, 'sm' => 2]),
                    ]),

                Step::make('contacto')
                    ->label('Contacto')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        FormView::make('filament.components.step-header')
                            ->key('me_step_header_contacto')
                            ->viewData([
                                'step' => 2,
                                'total' => 5,
                                'title' => 'Contacto',
                                'accent' => '#f97316',
                                'lord' => asset('lordicons/wired-outline-3095-mail-open-bell-hover-pinch.json'),
                                'lordState' => 'hover-pinch',
                                'subtitle' => 'Teléfono, email y dirección para comunicarnos con la empresa.',
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\TextInput::make('telefono')
                                    ->label('Teléfono / Celular')
                                    ->tel()
                                    ->mask('9999999999')
                                    ->maxLength(10)
                                    ->rules(['nullable', 'regex:/^[0-9]{10}$/'])
                                    ->validationMessages([
                                        'regex' => 'El teléfono debe tener exactamente 10 dígitos numéricos (sin +57, espacios, guiones ni letras).',
                                    ])
                                    ->placeholder('3001234567')
                                    ->helperText('10 dígitos, solo números. Se usa para las notificaciones por WhatsApp.')
                                    ->suffixIcon('heroicon-o-phone'),

                                Forms\Components\TextInput::make('email_contacto')
                                    ->label('Email de Contacto')
                                    ->email()
                                    ->maxLength(255)
                                    ->placeholder('contacto@empresa.com')
                                    ->helperText('Correo electrónico principal')
                                    ->suffixIcon('heroicon-o-envelope'),

                                Forms\Components\Textarea::make('direccion')
                                    ->label('Dirección')
                                    ->rows(2)
                                    ->placeholder('Ej: Calle 123 # 45-67, Edificio ABC, Piso 3')
                                    ->helperText('Dirección completa de la empresa')
                                    ->columnSpanFull(),
                            ])->columns(['default' => 1, 'sm' => 2]),
                    ]),

                Step::make('ubicacion')
                    ->label('Ubicación')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        FormView::make('filament.components.step-header')
                            ->key('me_step_header_ubicacion')
                            ->viewData([
                                'step' => 3,
                                'total' => 5,
                                'title' => 'Ubicación',
                                'accent' => '#eab308',
                                'lord' => asset('lordicons/wired-outline-18-location-pin-hover-jump-roll.json'),
                                'lordState' => 'hover-jump-roll',
                                'subtitle' => 'Departamento y ciudad donde opera la empresa.',
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Select::make('departamento')
                                    ->label('Departamento')
                                    ->required()
                                    ->searchable()
                                    ->options(EmpresaResource::getDepartamentos())
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set) => $set('ciudad', null))
                                    ->helperText('Seleccione el departamento'),

                                Forms\Components\Select::make('ciudad')
                                    ->label('Ciudad')
                                    ->required()
                                    ->searchable()
                                    ->options(function (Get $get) {
                                        $departamento = $get('departamento');
                                        return EmpresaResource::getCiudadesPorDepartamento($departamento);
                                    })
                                    ->disabled(fn(Get $get) => empty($get('departamento')))
                                    ->helperText('Seleccione primero el departamento')
                                    ->placeholder('Seleccione una ciudad...'),
                            ])->columns(['default' => 1, 'sm' => 2]),
                    ]),

                Step::make('ciiu')
                    ->label('Actividad Económica')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        FormView::make('filament.components.step-header')
                            ->key('me_step_header_ciiu')
                            ->viewData([
                                'step' => 4,
                                'total' => 5,
                                'title' => 'Actividad Económica (CIIU)',
                                'accent' => '#84cc16',
                                'lord' => 'https://cdn.lordicon.com/vgwutnhw.json',
                                'subtitle' => 'Clasificación CIIU y número de empleados (determina la obligación de RIT).',
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Select::make('actividad_economica_id')
                                    ->label('Actividad Económica Principal')
                                    ->relationship('actividadEconomica', 'nombre')
                                    ->getOptionLabelFromRecordUsing(fn(ActividadEconomica $record) => "{$record->codigo} - {$record->nombre}")
                                    ->getOptionLabelUsing(function ($value): ?string {
                                        $a = ActividadEconomica::find($value);
                                        return $a ? "{$a->codigo} - {$a->nombre}" : null;
                                    })
                                    ->searchable(['codigo', 'nombre'])
                                    ->preload(false)
                                    ->nullable()
                                    ->live()
                                    ->placeholder('Buscar por código o nombre...')
                                    ->helperText('Actividad principal según el RUT de la empresa')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('numero_empleados')
                                    ->label('Número de empleados')
                                    ->numeric()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->placeholder('Ej: 12')
                                    ->helperText('Empleados permanentes. Determina si la empresa está obligada a tener RIT.')
                                    ->suffixIcon('heroicon-o-user-group'),

                                Forms\Components\Placeholder::make('obligacion_rit')
                                    ->hiddenLabel()
                                    ->content(function (Get $get) {
                                        $seccion = $get('actividad_economica_id')
                                            ? ActividadEconomica::find($get('actividad_economica_id'))?->seccion
                                            : null;
                                        $n = $get('numero_empleados');
                                        $n = is_numeric($n) ? (int) $n : null;

                                        return new HtmlString(
                                            \App\Support\ObligacionRit::avisoHtml($n, $seccion)
                                        );
                                    })
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('actividadesSecundarias')
                                    ->label('Actividades Secundarias')
                                    ->relationship('actividadesSecundarias', 'nombre')
                                    ->getOptionLabelFromRecordUsing(fn(ActividadEconomica $record) => "{$record->codigo} - {$record->nombre}")
                                    ->getOptionLabelUsing(function ($value): ?string {
                                        $a = ActividadEconomica::find($value);
                                        return $a ? "{$a->codigo} - {$a->nombre}" : null;
                                    })
                                    ->searchable(['codigo', 'nombre'])
                                    ->preload(false)
                                    ->multiple()
                                    ->nullable()
                                    ->placeholder('Buscar por código o nombre...')
                                    ->helperText('Actividades complementarias que también ejerce la empresa')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Step::make('rit')
                    ->label('Reglamento Interno')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        FormView::make('filament.components.step-header')
                            ->key('me_step_header_rit')
                            ->viewData([
                                'step' => 5,
                                'total' => 5,
                                'title' => 'Reglamento Interno',
                                'accent' => '#22c55e',
                                'lord' => 'https://cdn.lordicon.com/edcgvlnw.json',
                                'subtitle' => 'Estado de su RIT. Para subirlo, mejorarlo o auditarlo, use Mi Reglamento Interno.',
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Section::make()
                            ->schema([
                                FormView::make('filament.components.mi-empresa-paso-rit')
                                    ->key('me_paso_rit_contenido')
                                    ->viewData(fn() => ['empresa' => $this->record])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
                ->persistStepInQueryString('paso')
                ->submitAction(new HtmlString(
                    Blade::render(<<<'BLADE'
                        <div class="flex items-center gap-3">
                            <x-filament::button
                                tag="a"
                                href="{{ $cancelUrl }}"
                                color="gray"
                            >
                                Cancelar
                            </x-filament::button>

                            <x-filament::button type="submit">
                                Guardar cambios
                            </x-filament::button>
                        </div>
                    BLADE, ['cancelUrl' => static::getResource()::getUrl('index')])
                ))
                ->columnSpanFull(),
        ]);
    }
}
