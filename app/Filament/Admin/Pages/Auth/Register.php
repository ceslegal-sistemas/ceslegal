<?php

namespace App\Filament\Admin\Pages\Auth;

use App\Filament\Admin\Resources\EmpresaResource;
use App\Models\ActividadEconomica;
use App\Models\Empresa;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Auth\Register as BaseRegister;

/**
 * Registro de clientes nuevos.
 * Crea simultáneamente la Empresa y el usuario con rol 'cliente'.
 */
class Register extends BaseRegister
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── Cuenta de acceso ──────────────────────────────────────────────
                Forms\Components\Section::make('Cuenta de acceso')
                    ->description('Credenciales con las que ingresará a la plataforma')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ]),

                // ── Datos de la empresa ───────────────────────────────────────────
                Forms\Components\Section::make('Datos de la empresa')
                    ->description('Información legal y de contacto de la empresa')
                    ->icon('heroicon-o-building-office-2')
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
                            ->placeholder('Ej: +57 300 123 4567')
                            ->suffixIcon('heroicon-o-phone'),

                        Forms\Components\TextInput::make('email_contacto')
                            ->label('Email de Contacto')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('contacto@empresa.com')
                            ->suffixIcon('heroicon-o-envelope'),

                        Forms\Components\Textarea::make('direccion')
                            ->label('Dirección')
                            ->rows(2)
                            ->placeholder('Ej: Calle 123 # 45-67, Edificio ABC, Piso 3')
                            ->columnSpanFull(),

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

                        Forms\Components\Select::make('actividad_economica_id')
                            ->label('Actividad Económica Principal (CIIU)')
                            ->options(
                                fn() => ActividadEconomica::orderBy('codigo')
                                    ->get()
                                    ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} — {$a->nombre}"])
                                    ->toArray()
                            )
                            ->searchable()
                            ->nullable()
                            ->placeholder('Buscar por código o nombre...')
                            ->helperText('Actividad principal según el RUT de la empresa (campo CIIU)')
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

                // ── Reglamento Interno de Trabajo ─────────────────────────────────
                Forms\Components\Section::make('Reglamento Interno de Trabajo (RIT)')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\Placeholder::make('rit_info')
                            ->label('')
                            ->content(fn() => new \Illuminate\Support\HtmlString($this->getRitCtaHtml())),
                    ]),
            ]);
    }

    /**
     * Crea la Empresa y el User con rol 'cliente' en una sola transacción.
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
            ? "<a href=\"{$purchaseUrl}\" target=\"_blank\" rel=\"noopener\" class=\"inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary-600 text-white font-semibold text-sm hover:bg-primary-700 transition-colors shadow-sm\">Adquirir Reglamento Interno</a>"
            : '';

        $script  = '<script>if(!window._liLoaded){window._liLoaded=true;var s=document.createElement("script");s.src="https://cdn.lordicon.com/lordicon.js";document.head.appendChild(s);}</script>';

        $html  = $script;
        $html .= '<div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-300 dark:border-blue-700 space-y-3">';
        $html .= '<div class="flex items-start gap-3">';
        $html .= '<lord-icon src="https://cdn.lordicon.com/hmpomorl.json" trigger="loop" delay="500" stroke="bold" colors="primary:#3b82f6,secondary:#93c5fd" style="width:36px;height:36px;flex-shrink:0;margin-top:2px"></lord-icon>';
        $html .= '<div>';
        $html .= '<p class="font-semibold text-blue-900 dark:text-blue-100 text-base">¿Su empresa cuenta con un Reglamento Interno de Trabajo?</p>';
        $html .= '<p class="text-sm text-blue-700 dark:text-blue-300 mt-1">El Reglamento Interno de Trabajo (RIT) es obligatorio para empresas con más de 5 trabajadores (Art. 105 CST). Sin él, la empresa solo puede aplicar terminación de contrato como medida disciplinaria.</p>';
        $html .= '<p class="text-sm text-blue-700 dark:text-blue-300 mt-1">Una vez registrado, podrá subir su RIT desde <strong>Empresas → Editar → Reglamento Interno</strong>. Si aún no lo tiene, puede adquirirlo a través de nuestra plataforma.</p>';
        $html .= '</div>';
        $html .= '</div>';

        if ($boton) {
            $html .= "<div class='pt-1'>{$boton}</div>";
        }

        $html .= '</div>';
        return $html;
    }
}
