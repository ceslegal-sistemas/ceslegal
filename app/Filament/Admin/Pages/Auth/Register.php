<?php

namespace App\Filament\Admin\Pages\Auth;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Models\ActividadEconomica;
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
use Illuminate\Support\Facades\Log;
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
                        ->schema([
                            Forms\Components\TextInput::make('razon_social')
                                ->label('Razón Social')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Ej: EMPRESA ABC S.A.S')
                                ->helperText('Nombre legal completo de la empresa')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('nit')
                                ->label('NIT')
                                ->required()
                                ->unique('empresas', 'nit')
                                ->maxLength(50)
                                ->placeholder('Ej: 900123456-7')
                                ->helperText('Número de Identificación Tributaria')
                                ->suffixIcon('heroicon-o-identification'),

                            Forms\Components\TextInput::make('representante_legal')
                                ->label('Representante Legal')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Ej: Juan Pérez García')
                                ->suffixIcon('heroicon-o-user'),

                            Forms\Components\TextInput::make('telefono')
                                ->label('Teléfono')
                                ->tel()
                                ->maxLength(50)
                                ->placeholder('+57 300 123 4567')
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
                        ->schema([
                            Forms\Components\Placeholder::make('ciiu_aviso')
                                ->label('')
                                ->content(new HtmlString(
                                    '<p class="text-sm text-gray-600 dark:text-gray-400">Busque y seleccione la actividad económica según el RUT de su empresa. Si no la encuentra, puede continuar sin seleccionarla y agregarla posteriormente.</p>'
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

                    // ── Paso 5: Reglamento Interno ────────────────────────────────
                    Forms\Components\Wizard\Step::make('RIT')
                        ->label('Reglamento Interno')
                        ->icon('heroicon-o-document-text')
                        ->description('Reglamento Interno de Trabajo')
                        ->schema([
                            Forms\Components\Placeholder::make('rit_info')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-700 text-sm text-blue-800 dark:text-blue-200">' .
                                    '<p class="font-semibold mb-1">¿Por qué es importante el Reglamento Interno de Trabajo (RIT)?</p>' .
                                    '<p>El RIT es obligatorio para empresas con más de 5 trabajadores (Art. 105 CST). <strong>Sin RIT activo, la plataforma solo puede aplicar terminación de contrato</strong> como medida disciplinaria. Con RIT puede aplicar también llamados de atención y suspensiones.</p>' .
                                    '</div>'
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
                                    'tiene'     => 'Suba el archivo .docx de su RIT aprobado por el Ministerio del Trabajo.',
                                    'construir' => 'Al crear la cuenta, lo redirigiremos a un cuestionario guiado. La IA redactará su RIT completo.',
                                    'despues'   => 'Puede subir o construir el RIT más adelante desde el panel de administración.',
                                ])
                                ->default('despues')
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('reglamento_docx_temp')
                                ->label('Subir Reglamento Interno (.docx)')
                                ->helperText('Suba el archivo .docx de su RIT aprobado por el Ministerio del Trabajo.')
                                ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
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
        $empresa = Empresa::create([
            'razon_social'           => $data['razon_social'],
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

        if ($ritOpcion === 'tiene' && $rutaDocx) {
            try {
                app(ReglamentoInternoService::class)->procesarDocumento(
                    storage_path("app/{$rutaDocx}"),
                    $empresa->id,
                    basename($rutaDocx)
                );
            } catch (\Exception $e) {
                Log::warning('No se pudo procesar el RIT durante el registro', [
                    'empresa_id' => $empresa->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        } elseif ($ritOpcion === 'construir') {
            // Tras el registro, redirigir al constructor de RIT
            // (se sobreescribe solo si no hay ya redirect a PayU)
            if (empty($this->redirectUrl)) {
                $this->redirectUrl = route('filament.admin.pages.rit-builder');
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

    private function getRitCtaHtml(): string
    {
        $purchaseUrl = config('ces.rit_purchase_url');

        $boton = $purchaseUrl
            ? "<a href=\"{$purchaseUrl}\" target=\"_blank\" rel=\"noopener\" class=\"inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary-600 text-white font-semibold text-sm hover:bg-primary-500 transition-colors shadow-sm\">Adquirir Reglamento Interno</a>"
            : '';

        $script = '<script>if(!window._liLoaded){window._liLoaded=true;var s=document.createElement("script");s.src="https://cdn.lordicon.com/lordicon.js";document.head.appendChild(s);}</script>';

        $html  = $script;
        $html .= '<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 space-y-3">';
        $html .= '<div class="flex items-start gap-3">';
        $html .= '<lord-icon src="https://cdn.lordicon.com/hmpomorl.json" trigger="loop" delay="500" stroke="bold" colors="primary:#3b82f6,secondary:#93c5fd" style="width:36px;height:36px;flex-shrink:0;margin-top:2px"></lord-icon>';
        $html .= '<div>';
        $html .= '<p class="font-semibold text-gray-900 dark:text-gray-100 text-base">¿Ya tiene Reglamento Interno de Trabajo?</p>';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-400 mt-1">El RIT es obligatorio para empresas con más de 5 trabajadores (Art. 105 CST). Sin él, la plataforma solo podrá aplicar terminación de contrato como medida disciplinaria.</p>';
        $html .= '<ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400 list-disc list-inside">';
        $html .= '<li><strong class="text-gray-800 dark:text-gray-200">Si ya tiene RIT:</strong> súbalo directamente en el campo de abajo.</li>';
        $html .= '<li><strong class="text-gray-800 dark:text-gray-200">Si no lo tiene:</strong> puede continuar sin él y adquirirlo después.</li>';
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';

        if ($boton) {
            $html .= "<div class='pt-1'>{$boton}</div>";
        }

        $html .= '</div>';
        return $html;
    }
}
