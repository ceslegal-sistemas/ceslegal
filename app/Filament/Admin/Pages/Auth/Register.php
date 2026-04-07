<?php

namespace App\Filament\Admin\Pages\Auth;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Models\ActividadEconomica;
use App\Models\Empresa;
use App\Models\User;
use App\Services\ReglamentoInternoService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Registro de clientes nuevos — wizard de 4 pasos.
 * Crea simultáneamente la Empresa y el usuario con rol 'cliente'.
 */
class Register extends BaseRegister
{
    /**
     * Ancho del panel de registro (más amplio que el default 'lg').
     */
    protected ?string $maxWidth = '2xl';

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
                        ])->columns(2),

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

                    // ── Paso 4: Reglamento Interno ────────────────────────────────
                    Forms\Components\Wizard\Step::make('RIT')
                        ->label('Reglamento Interno')
                        ->icon('heroicon-o-document-text')
                        ->description('Reglamento Interno de Trabajo')
                        ->schema([
                            Forms\Components\Placeholder::make('rit_cta')
                                ->label('')
                                ->content(fn() => new HtmlString($this->getRitCtaHtml()))
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('reglamento_docx_temp')
                                ->label('Subir Reglamento Interno (.docx)')
                                ->helperText('Si ya cuenta con su RIT aprobado por el Ministerio del Trabajo, súbalo aquí. Si aún no lo tiene, puede continuar sin cargarlo.')
                                ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                ->disk('local')
                                ->directory('reglamentos-temp')
                                ->visibility('private')
                                ->maxSize(10240)
                                ->nullable()
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

    /**
     * Ocultar el botón de registro que Filament pone fuera del wizard.
     */
    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * Crea la Empresa + User con rol 'cliente' y procesa el RIT si fue cargado.
     */
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

        // Actividades secundarias (many-to-many)
        if (!empty($data['actividades_secundarias_ids'])) {
            $empresa->actividadesSecundarias()->sync($data['actividades_secundarias_ids']);
        }

        // Procesar RIT si se subió un archivo
        $rutaDocx = $data['reglamento_docx_temp'] ?? null;
        if ($rutaDocx) {
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
        }

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => $data['password'],
            'role'       => 'cliente',
            'empresa_id' => $empresa->id,
            'active'     => true,
        ]);

        $user->assignRole('cliente');

        return $user;
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
