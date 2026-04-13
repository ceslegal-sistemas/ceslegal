<?php

namespace App\Filament\Admin\Pages;

use App\Models\Configuracion;
use App\Services\WhatsAppService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ConfiguracionWhatsapp extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-chat-bubble-left-ellipsis';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $navigationLabel = 'WhatsApp';
    protected static ?string $title           = 'Configuración de WhatsApp Business';
    protected static ?string $slug            = 'configuracion-whatsapp';
    protected static ?int    $navigationSort  = 50;
    protected static string  $view            = 'filament.admin.pages.configuracion-whatsapp';

    public ?array $data = [];

    // Estado de la verificación de conexión
    public ?array $estadoConexion = null;
    public bool $verificando = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function mount(): void
    {
        $this->form->fill([
            'whatsapp_habilitado'           => (bool) Configuracion::obtener('whatsapp_habilitado', false),
            'whatsapp_phone_number_id'      => Configuracion::obtener('whatsapp_phone_number_id', ''),
            'whatsapp_business_account_id'  => Configuracion::obtener('whatsapp_business_account_id', ''),
            'whatsapp_access_token'         => Configuracion::obtener('whatsapp_access_token', ''),
            'whatsapp_webhook_verify_token' => Configuracion::obtener('whatsapp_webhook_verify_token', 'ces_legal_whatsapp'),
        ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                // ── Estado ────────────────────────────────────────────────────
                Forms\Components\Section::make('Estado del servicio')
                    ->icon('heroicon-o-signal')
                    ->schema([
                        Forms\Components\Toggle::make('whatsapp_habilitado')
                            ->label('Activar notificaciones por WhatsApp')
                            ->helperText('Al desactivar, ninguna notificación se enviará por WhatsApp aunque las credenciales sean correctas.')
                            ->onIcon('heroicon-m-check')
                            ->offIcon('heroicon-m-x-mark')
                            ->onColor('success'),
                    ]),

                // ── Credenciales Meta Cloud API ────────────────────────────────
                Forms\Components\Section::make('Credenciales — Meta Cloud API')
                    ->icon('heroicon-o-key')
                    ->description('Obtenga estos datos en Meta for Developers → su app → WhatsApp → API Setup.')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_phone_number_id')
                            ->label('Phone Number ID')
                            ->helperText('ID numérico del número de teléfono registrado en Meta (ej: 123456789012345).')
                            ->placeholder('123456789012345')
                            ->maxLength(30)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('whatsapp_business_account_id')
                            ->label('WhatsApp Business Account ID (WABA ID)')
                            ->helperText('ID de la cuenta de WhatsApp Business en Meta Business Suite.')
                            ->placeholder('987654321098765')
                            ->maxLength(30)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('whatsapp_access_token')
                            ->label('Access Token')
                            ->helperText('Token de usuario del sistema (System User Token). Use un token permanente para producción.')
                            ->placeholder('EAAxxxxxxxxxxxxxxxx...')
                            ->password()
                            ->revealable()
                            ->maxLength(512)
                            ->columnSpanFull(),
                    ]),

                // ── Webhook ────────────────────────────────────────────────────
                Forms\Components\Section::make('Webhook')
                    ->icon('heroicon-o-arrow-path')
                    ->description('Configure el webhook en Meta para recibir mensajes y actualizaciones de estado.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_webhook_verify_token')
                            ->label('Token de verificación del webhook')
                            ->helperText('Cadena personalizada que Meta usará para verificar su webhook. Cópiela exactamente en la consola de Meta.')
                            ->maxLength(128)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('webhook_url')
                            ->label('URL del webhook')
                            ->content(fn() => rtrim(config('app.url'), '/') . '/whatsapp/webhook')
                            ->helperText('Configure esta URL en Meta for Developers → Webhooks.')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function guardar(): void
    {
        $datos = $this->form->getState();

        $mapa = [
            'whatsapp_habilitado'           => ['tipo' => 'boolean'],
            'whatsapp_phone_number_id'      => ['tipo' => 'text'],
            'whatsapp_business_account_id'  => ['tipo' => 'text'],
            'whatsapp_access_token'         => ['tipo' => 'text'],
            'whatsapp_webhook_verify_token' => ['tipo' => 'text'],
        ];

        foreach ($mapa as $clave => $meta) {
            $valor = $datos[$clave] ?? null;

            if ($meta['tipo'] === 'boolean') {
                $valor = $valor ? '1' : '0';
            }

            Configuracion::updateOrCreate(
                ['clave' => $clave],
                [
                    'valor'    => (string) $valor,
                    'tipo'     => $meta['tipo'],
                    'categoria' => 'whatsapp',
                    'editable' => true,
                ]
            );
        }

        // Limpiar estado de conexión al guardar nuevas credenciales
        $this->estadoConexion = null;

        Notification::make()
            ->title('Configuración guardada')
            ->body('Los cambios de WhatsApp se han guardado correctamente.')
            ->success()
            ->send();
    }

    public function verificarConexion(): void
    {
        // Guardar primero para que el servicio use los valores actuales
        $this->guardar();

        $this->verificando = true;

        try {
            $service = new WhatsAppService();
            $this->estadoConexion = $service->verificarConexion();
        } finally {
            $this->verificando = false;
        }

        if ($this->estadoConexion['ok']) {
            Notification::make()
                ->title('Conexión exitosa')
                ->body('Las credenciales son válidas y el número está activo.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Error de conexión')
                ->body($this->estadoConexion['mensaje'])
                ->danger()
                ->send();
        }
    }
}
