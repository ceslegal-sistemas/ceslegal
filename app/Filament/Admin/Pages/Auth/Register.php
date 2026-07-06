<?php

namespace App\Filament\Admin\Pages\Auth;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Models\ActividadEconomica;
use App\Models\Bufete;
use App\Models\Empresa;
use App\Models\Suscripcion;
use App\Models\User;
use App\Services\PayUService;
use App\Services\ReglamentoInternoService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Registro de clientes nuevos — wizard de 5 pasos.
 * Crea simultáneamente la Empresa y el usuario con rol 'cliente'.
 *
 * Planes:
 *  - Básico : 7 días de prueba gratis → $29.000/mes
 *  - Pro    : pago inmediato → $59.000/mes
 *  - Firma  : pago inmediato → $99.000/mes
 * Ciclo: mensual o anual (anual = 15 % descuento)
 */
class Register extends BaseRegister
{
    protected ?string $maxWidth = '4xl';

    /** URL de checkout PayU; vacía = ir al panel admin. */
    private string $redirectUrl = '';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([

                    // ── Paso 0: Tipo de cuenta ────────────────────────────────────
                    Forms\Components\Wizard\Step::make('TipoCuenta')
                        ->label('Tipo de cuenta')
                        ->icon('heroicon-o-identification')
                        ->description('Seleccione cómo usará la plataforma')
                        ->schema([
                            Forms\Components\Placeholder::make('tipo_cuenta_label')
                                ->label('')
                                ->content(new HtmlString('<p class="rtc-question" style="font-size:.95rem;font-weight:600;margin:0 0 .25rem">¿Qué tipo de cuenta desea crear?</p>'))
                                ->columnSpanFull(),

                            // Estado del selector: lo escribe la vista de cards (Alpine $wire.entangle).
                            Forms\Components\Hidden::make('tipo_cuenta')
                                ->default('empresa')
                                ->live(),

                            Forms\Components\View::make('filament.components.register-tipo-cuenta')
                                ->columnSpanFull(),
                        ]),

                    // ── Paso 1: Cuenta ────────────────────────────────────────────
                    Forms\Components\Wizard\Step::make('Cuenta')
                        ->label('Cuenta de acceso')
                        ->icon('heroicon-o-user-circle')
                        ->description('Credenciales para ingresar a la plataforma')
                        ->schema([
                            $this->getNameFormComponent(),
                            $this->getEmailFormComponent(),
                            $this->getPasswordFormComponent(),
                            $this->getPasswordConfirmationFormComponent(),
                        ]),

                    // ── Paso 2: Empresa ───────────────────────────────────────────
                    Forms\Components\Wizard\Step::make('Empresa')
                        ->label('Datos de la empresa')
                        ->icon('heroicon-o-building-office-2')
                        ->description('Información legal y de contacto')
                        ->visible(fn (Forms\Get $get) => ($get('tipo_cuenta') ?? 'empresa') === 'empresa')
                        ->schema([
                            Forms\Components\TextInput::make('razon_social')
                                ->label('Razón Social')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Ej: EMPRESA ABC')
                                ->helperText('Nombre legal de la empresa sin tipo societario')
                                ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                ->columnSpan(2),

                            Forms\Components\Select::make('tipo_societario')
                                ->label('Tipo Societario')
                                ->options(\App\Models\Empresa::TIPOS_SOCIETARIOS)
                                ->searchable()
                                ->placeholder('Seleccione...')
                                ->helperText('Forma jurídica de la empresa')
                                ->live(),

                            Forms\Components\TextInput::make('nit')
                                ->label('NIT')
                                ->required()
                                ->unique('empresas', 'nit')
                                ->maxLength(50)
                                ->mask(fn(Get $get) => ($get('tipo_societario') && $get('tipo_societario') !== 'Persona Natural')
                                    ? '999999999-9'
                                    : null)
                                ->placeholder(fn(Get $get) => ($get('tipo_societario') && $get('tipo_societario') !== 'Persona Natural')
                                    ? 'Ej: 900123456-7'
                                    : 'Ej: 1023456789')
                                ->helperText(fn(Get $get) => ($get('tipo_societario') && $get('tipo_societario') !== 'Persona Natural')
                                    ? 'Incluya el dígito de verificación separado por guion'
                                    : 'Número de cédula de ciudadanía')
                                ->rules(fn(Get $get) => ($get('tipo_societario') && $get('tipo_societario') !== 'Persona Natural')
                                    ? ['regex:/^\d{6,12}-\d$/']
                                    : [])
                                ->validationMessages(['regex' => 'El NIT debe incluir el dígito de verificación (ej: 900123456-7).'])
                                ->suffixIcon('heroicon-o-identification'),

                            Forms\Components\TextInput::make('representante_legal')
                                ->label('Representante Legal')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Ej: Juan Pérez García')
                                ->suffixIcon('heroicon-o-user'),

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
                                ->suffixIcon('heroicon-o-envelope'),

                            Forms\Components\Select::make('departamento')
                                ->label('Departamento')
                                ->required()
                                ->searchable()
                                ->options(EmpresaResource::getDepartamentos())
                                ->live()
                                ->afterStateUpdated(fn(Set $set) => $set('ciudad', null)),

                            Forms\Components\Select::make('ciudad')
                                ->label('Ciudad')
                                ->required()
                                ->searchable()
                                ->options(fn(Get $get) => EmpresaResource::getCiudadesPorDepartamento($get('departamento')))
                                ->disabled(fn(Get $get) => empty($get('departamento')))
                                ->placeholder('Seleccione primero el departamento'),

                            Forms\Components\Textarea::make('direccion')
                                ->label('Dirección')
                                ->rows(2)
                                ->placeholder('Calle 123 # 45-67, Edificio ABC, Piso 3')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('dias_laborales')
                                ->label('Días Laborales')
                                ->options([
                                    'lunes_viernes' => 'Lunes a Viernes',
                                    'lunes_sabado'  => 'Lunes a Sábado',
                                ])
                                ->default('lunes_viernes')
                                ->required()
                                ->native(false)
                                ->helperText('Días que opera normalmente la empresa'),
                        ])->columns(['default' => 1, 'sm' => 2]),

                    // ── Paso 3: Actividad Económica CIIU ──────────────────────────
                    Forms\Components\Wizard\Step::make('CIIU')
                        ->label('Actividad Económica')
                        ->icon('heroicon-o-chart-bar')
                        ->description('Clasificación Industrial Internacional Uniforme')
                        ->visible(fn (Forms\Get $get) => ($get('tipo_cuenta') ?? 'empresa') === 'empresa')
                        ->schema([
                            Forms\Components\Placeholder::make('ciiu_aviso')
                                ->label('')
                                ->content(new HtmlString(
                                    view('filament.components.register-step-ciiu-info')->render()
                                ))
                                ->columnSpanFull(),

                            Forms\Components\Select::make('actividad_economica_id')
                                ->label('Actividad Económica Principal')
                                ->searchable()
                                ->nullable()
                                ->getSearchResultsUsing(
                                    fn(string $search) => ActividadEconomica::where('codigo', 'like', "%{$search}%")
                                        ->orWhere('nombre', 'like', "%{$search}%")
                                        ->orderBy('codigo')
                                        ->limit(20)
                                        ->get()
                                        ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} — {$a->nombre}"])
                                        ->toArray()
                                )
                                ->getOptionLabelUsing(function ($value) {
                                    $a = ActividadEconomica::find($value);
                                    return $a ? "{$a->codigo} — {$a->nombre}" : null;
                                })
                                ->placeholder('Buscar por código o nombre CIIU...')
                                ->helperText('Actividad principal registrada en el RUT de la empresa')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('actividades_secundarias_ids')
                                ->label('Actividades Económicas Secundarias')
                                ->searchable()
                                ->multiple()
                                ->nullable()
                                ->getSearchResultsUsing(
                                    fn(string $search) => ActividadEconomica::where('codigo', 'like', "%{$search}%")
                                        ->orWhere('nombre', 'like', "%{$search}%")
                                        ->orderBy('codigo')
                                        ->limit(20)
                                        ->get()
                                        ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} — {$a->nombre}"])
                                        ->toArray()
                                )
                                ->placeholder('Buscar actividades secundarias...')
                                ->helperText('Actividades complementarias que también ejerce la empresa')
                                ->columnSpanFull(),
                        ]),

                    // ── Paso 4: Plan de Suscripción ───────────────────────────────
                    // TEMPORALMENTE COMENTADO — se activará cuando el módulo de pagos esté listo
                    // Al estar comentado, el registro usa plan 'basico' + ciclo 'mensual' por defecto
                    
                    /*
                    Forms\Components\Wizard\Step::make('Plan')
                        ->label('Plan')
                        ->icon('heroicon-o-credit-card')
                        ->description('Seleccione el plan que mejor se adapte a su empresa')
                        ->schema([
                            // Valores capturados via Alpine/$wire.set desde la vista blade
                            Forms\Components\Hidden::make('plan_suscripcion')
                                ->default('basico'),

                            Forms\Components\Hidden::make('ciclo_facturacion')
                                ->default('mensual'),

                            Forms\Components\Placeholder::make('selector_planes')
                                ->label('')
                                ->content(fn() => new HtmlString(
                                    view('filament.components.plan-selector', [
                                        'planes' => config('ces.planes'),
                                    ])->render()
                                ))
                                ->columnSpanFull(),
                        ]),
                    */

                    // ── Paso 4: Bufete de abogados ────────────────────────────────
                    Forms\Components\Wizard\Step::make('Bufete')
                        ->label('Datos del bufete')
                        ->icon('heroicon-o-briefcase')
                        ->description('Información del bufete de abogados')
                        ->visible(fn (Forms\Get $get) => ($get('tipo_cuenta') ?? 'empresa') === 'bufete')
                        ->schema([
                            Forms\Components\TextInput::make('bufete_nombre')
                                ->label('Nombre del Bufete')
                                ->required(fn (Forms\Get $get) => ($get('tipo_cuenta') ?? 'empresa') === 'bufete')
                                ->maxLength(255)
                                ->placeholder('Ej: Rendón & Asociados')
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('bufete_nit')
                                ->label('NIT del Bufete')
                                ->nullable()
                                ->maxLength(50)
                                ->placeholder('Ej: 900111222-3')
                                ->suffixIcon('heroicon-o-identification'),

                            Forms\Components\TextInput::make('bufete_representante')
                                ->label('Representante Legal')
                                ->nullable()
                                ->maxLength(255)
                                ->placeholder('Ej: Ana García López')
                                ->suffixIcon('heroicon-o-user'),

                            Forms\Components\TextInput::make('bufete_email_contacto')
                                ->label('Email de Contacto')
                                ->email()
                                ->nullable()
                                ->maxLength(255)
                                ->placeholder('contacto@bufete.co')
                                ->suffixIcon('heroicon-o-envelope'),

                            Forms\Components\TextInput::make('bufete_telefono')
                                ->label('Teléfono / Celular')
                                ->tel()
                                ->nullable()
                                ->maxLength(20)
                                ->placeholder('3001234567')
                                ->suffixIcon('heroicon-o-phone'),
                        ])->columns(['default' => 1, 'sm' => 2]),

                    // ── Paso 5: Reglamento Interno ────────────────────────────────
                    Forms\Components\Wizard\Step::make('RIT')
                        ->label('Reglamento Interno')
                        ->icon('heroicon-o-document-text')
                        ->description('Reglamento Interno de Trabajo')
                        ->visible(fn (Forms\Get $get) => ($get('tipo_cuenta') ?? 'empresa') === 'empresa')
                        ->schema([
                            Forms\Components\Placeholder::make('rit_info')
                                ->label('')
                                ->content(new HtmlString(
                                    view('filament.components.register-step-rit-info')->render()
                                ))
                                ->columnSpanFull(),

                            Forms\Components\Radio::make('rit_opcion')
                                ->label('¿Su empresa tiene Reglamento Interno de Trabajo (RIT)?')
                                ->options([
                                    'tiene'     => 'Sí, ya lo tengo — lo subo ahora',
                                    'construir' => 'No lo tengo — quiero construirlo con IA (recomendado)',
                                    'despues'   => 'Lo haré después (solo podré aplicar terminación de contrato)',
                                ])
                                ->descriptions([
                                    'tiene'     => 'Suba el archivo .docx o .pdf de su RIT aprobado por el Ministerio del Trabajo.',
                                    'construir' => 'Al crear la cuenta, lo redirigiremos a un cuestionario guiado. La IA redactará su RIT completo.',
                                    'despues'   => 'Puede subir o construir el RIT más adelante desde el panel de administración.',
                                ])
                                ->default('despues')
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('reglamento_docx_temp')
                                ->label('Subir Reglamento Interno (.docx o .pdf)')
                                ->helperText('Formatos aceptados: .docx y .pdf — máx. 10 MB.')
                                ->acceptedFileTypes([
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'application/pdf',
                                ])
                                ->disk('local')
                                ->directory('reglamentos-temp')
                                ->visibility('private')
                                ->maxSize(10240)
                                ->nullable()
                                ->visible(fn (Forms\Get $get) => $get('rit_opcion') === 'tiene')
                                ->columnSpanFull(),
                        ]),

                ])
                ->submitAction(new HtmlString(
                    '<button type="button" wire:click="register" wire:loading.attr="disabled"
                        class="fi-btn fi-btn-size-md fi-btn-color-primary fi-color-primary fi-btn-filled
                               inline-flex items-center justify-center gap-1.5 font-semibold rounded-lg
                               text-sm px-3 py-2 bg-primary-600 text-white shadow-sm
                               hover:bg-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/50
                               dark:bg-primary-500 dark:hover:bg-primary-400
                               dark:focus-visible:ring-primary-400/50 disabled:opacity-70 cursor-pointer">' .
                    '<span wire:loading.remove wire:target="register">Crear cuenta</span>' .
                    '<span wire:loading wire:target="register">Creando cuenta...</span>' .
                    '</button>'
                )),
            ]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function handleRegistration(array $data): User
    {
        if (($data['tipo_cuenta'] ?? 'empresa') === 'bufete') {
            return $this->crearCuentaBufete($data);
        }

        return $this->crearCuentaEmpresa($data);
    }

    public function crearCuentaEmpresa(array $data): User
    {
        $empresa = Empresa::create([
            'razon_social'           => $data['razon_social'],
            'tipo_societario'        => $data['tipo_societario'] ?? null,
            'nit'                    => $data['nit'],
            'representante_legal'    => $data['representante_legal'],
            'telefono'               => $data['telefono'] ?? null,
            'email_contacto'         => $data['email_contacto'] ?? null,
            'direccion'              => $data['direccion'] ?? null,
            'departamento'           => $data['departamento'] ?? null,
            'ciudad'                 => $data['ciudad'] ?? null,
            'actividad_economica_id' => $data['actividad_economica_id'] ?? null,
            'dias_laborales'         => $data['dias_laborales'] ?? 'lunes_viernes',
            'active'                 => true,
        ]);

        if (!empty($data['actividades_secundarias_ids'])) {
            $empresa->actividadesSecundarias()->sync($data['actividades_secundarias_ids']);
        }

        $plan   = $data['plan_suscripcion'] ?? 'basico';
        $ciclo  = $data['ciclo_facturacion'] ?? 'mensual';
        $email  = $data['email'];

        $this->crearSuscripcion($empresa, $plan, $ciclo, $email);

        $ritOpcion = $data['rit_opcion'] ?? 'despues';
        $rutaDocx  = $data['reglamento_docx_temp'] ?? null;

        // Celebrar con confeti al aterrizar tras el registro (un solo uso). Aplica a
        // los tres casos: 'tiene' (Auditar RIT), 'construir' (Mi/Generar RIT) y
        // 'despues' (Dashboard/Panel de control).
        session(['celebrar_registro_rit' => true]);

        if ($ritOpcion === 'tiene' && $rutaDocx) {
            $nombreArchivo  = basename($rutaDocx);
            $rutaPermanente = 'reglamentos/' . $empresa->id . '/' . $nombreArchivo;

            // Mover de temp a directorio permanente para que la descarga directa funcione
            Storage::disk('local')->move($rutaDocx, $rutaPermanente);

            $rutaAbsoluta = Storage::disk('local')->path($rutaPermanente);

            app(ReglamentoInternoService::class)->procesarDocumento(
                $rutaAbsoluta,
                $empresa->id,
                $nombreArchivo,
                $rutaPermanente,
            );

            // Redirigir a la auditoría para que se ejecute automáticamente al ingresar
            if (empty($this->redirectUrl)) {
                $this->redirectUrl = route('filament.admin.pages.auditar-r-i-t');
            }
        } elseif ($ritOpcion === 'construir') {
            // Tras el registro, redirigir a Mi Reglamento Interno
            // (se sobreescribe solo si no hay ya redirect a PayU)
            if (empty($this->redirectUrl)) {
                $this->redirectUrl = route('filament.admin.pages.mi-reglamento-interno');
            } else {
                // Hay redirect a PayU; guardar en sesión para redirigir post-pago
                session(['rit_construir_despues_pago' => true]);
            }
        }

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $email,
            'password'   => $data['password'],
            'role'       => 'cliente',
            'empresa_id' => $empresa->id,
            'active'     => true,
        ]);

        $user->assignRole('cliente');

        return $user;
    }

    public function crearCuentaBufete(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $bufete = Bufete::create([
                'nombre'          => $data['bufete_nombre'],
                'nit'             => $data['bufete_nit'] ?? null,
                'representante'   => $data['bufete_representante'] ?? null,
                'email_contacto'  => $data['bufete_email_contacto'] ?? null,
                'telefono'        => $data['bufete_telefono'] ?? null,
                'active'          => true,
            ]);

            $user = User::create([
                'name'       => $data['name'],
                'email'      => $data['email'],
                'password'   => $data['password'],
                'role'       => 'abogado',
                'bufete_id'  => $bufete->id,
                'empresa_id' => null,
                'active'     => true,
            ]);

            $user->assignRole('abogado');

            return $user;
        });
    }

    private function crearSuscripcion(Empresa $empresa, string $plan, string $ciclo, string $buyerEmail): void
    {
        if ($plan === 'basico') {
            $trialDias = (int) config('ces.planes.basico.trial_dias', 7);

            Suscripcion::create([
                'empresa_id'    => $empresa->id,
                'plan'          => 'basico',
                'ciclo_facturacion' => $ciclo,
                'estado'        => 'trial',
                'trial_ends_at' => now()->addDays($trialDias),
            ]);
            return;
        }

        // Pro / Firma: crear suscripción pendiente de pago y redirigir a PayU
        $payu       = app(PayUService::class);
        $referencia = $payu->generarReferencia($empresa->id);
        $nombrePlan = config("ces.planes.{$plan}.nombre", $plan);

        $suscripcion = Suscripcion::create([
            'empresa_id'        => $empresa->id,
            'plan'              => $plan,
            'ciclo_facturacion' => $ciclo,
            'estado'            => 'pendiente_pago',
            'payment_reference' => $referencia,
        ]);

        $descripcion = "CES Legal — Plan {$nombrePlan} ({$ciclo})";

        $this->redirectUrl = $payu->getCheckoutUrl($suscripcion, $buyerEmail, $descripcion);
    }

    public function getRedirectUrl(): string
    {
        if (!empty($this->redirectUrl)) {
            return $this->redirectUrl;
        }

        return parent::getRedirectUrl();
    }

}
