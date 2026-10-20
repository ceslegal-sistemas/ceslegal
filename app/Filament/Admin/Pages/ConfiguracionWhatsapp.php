<?php

namespace App\Filament\Admin\Pages;

use App\Models\Configuracion;
use App\Services\WhatsAppService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

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

    /**
     * Solo super_admin - credenciales de API y configuración crítica de
     * cumplimiento con Meta, no algo que deba quedar expuesto por un
     * permiso de Shield mal asignado. Mismo criterio ya aplicado a
     * ConfiguracionTextoResource::canAccess().
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(): void
    {
        // Token del webhook auto-generado (pedido del usuario, 2026-10-19:
        // "como un chatwoot") - ya no lo escribe el admin a mano, se genera
        // una sola vez y se muestra de solo lectura con botón de copiar
        // (ver getWebhookToken()/regenerarWebhookToken()). Aleatorio es
        // más seguro que cualquier cadena que alguien escriba.
        if (!Configuracion::obtener('whatsapp_webhook_verify_token')) {
            Configuracion::updateOrCreate(
                ['clave' => 'whatsapp_webhook_verify_token'],
                ['valor' => Str::random(32), 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => false]
            );
        }

        $this->form->fill([
            'whatsapp_habilitado'            => (bool) Configuracion::obtener('whatsapp_habilitado', false),
            'whatsapp_numero_telefono'       => Configuracion::obtener('whatsapp_numero_telefono', ''),
            'whatsapp_phone_number_id'       => Configuracion::obtener('whatsapp_phone_number_id', ''),
            'whatsapp_business_account_id'   => Configuracion::obtener('whatsapp_business_account_id', ''),
            'whatsapp_access_token'          => Configuracion::obtener('whatsapp_access_token', ''),
            'whatsapp_plantilla_otp_nombre'  => Configuracion::obtener('whatsapp_plantilla_otp_nombre', config('services.whatsapp.plantilla_otp', 'otp_verificacion')),
            'whatsapp_plantilla_alerta_rrhh_nombre' => Configuracion::obtener('whatsapp_plantilla_alerta_rrhh_nombre', ''),
        ]);
    }

    public function getCalidadActual(): ?string
    {
        return Configuracion::obtener('whatsapp_calidad_actual');
    }

    public function getLimiteMensajeria(): ?string
    {
        return Configuracion::obtener('whatsapp_messaging_limit_tier');
    }

    public function getWebhookToken(): string
    {
        return (string) Configuracion::obtener('whatsapp_webhook_verify_token', '');
    }

    public function regenerarWebhookToken(): void
    {
        Configuracion::updateOrCreate(
            ['clave' => 'whatsapp_webhook_verify_token'],
            ['valor' => Str::random(32), 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => false]
        );

        Notification::make()
            ->title('Token regenerado')
            ->body('Actualice el token en la consola de Meta con el nuevo valor.')
            ->warning()
            ->send();
    }

    /** Plantillas sincronizadas desde Meta (nombre => "nombre (categoría, idioma)"). */
    public function getOpcionesPlantillas(): array
    {
        $cache = json_decode((string) Configuracion::obtener('whatsapp_plantillas_cache', '[]'), true) ?: [];

        return collect($cache)
            ->mapWithKeys(fn (array $p) => [
                $p['nombre'] => "{$p['nombre']} · {$p['categoria']} · {$p['idioma']} · {$p['estado']}",
            ])
            ->all();
    }

    public function sincronizarPlantillas(): void
    {
        $plantillas = app(WhatsAppService::class)->listarPlantillas();

        // tipo 'text' a propósito, no 'json': Configuracion::obtener() devuelve
        // valor_parsed, que para tipo 'json' ya decodifica a array - aquí se
        // quiere la cadena cruda para decodificarla a mano en getOpcionesPlantillas().
        Configuracion::updateOrCreate(
            ['clave' => 'whatsapp_plantillas_cache'],
            ['valor' => json_encode($plantillas), 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => false]
        );

        if ($plantillas === []) {
            Notification::make()
                ->title('No se encontraron plantillas')
                ->body('Verifique que las credenciales sean correctas y que existan plantillas creadas en Meta.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Plantillas sincronizadas')
            ->body(count($plantillas) . ' plantilla(s) encontradas en Meta.')
            ->success()
            ->send();
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                // ── Fila 1: Estado del servicio (1 columna completa) ─────────────
                // Pedido del usuario (2026-10-20): emparejar "Estado del
                // servicio" (1 solo toggle) junto a "Credenciales" (4 campos)
                // se seguía viendo desproporcionado aunque ya no se estiraran
                // a la misma altura - el ancho 1/3 vs 2/3 ya se notaba
                // desigual de por sí. Cada una ahora ocupa su propia fila
                // completa en vez de competir por espacio una junto a otra.
                Forms\Components\Section::make('Estado del servicio')
                    ->schema([
                        Forms\Components\Toggle::make('whatsapp_habilitado')
                            ->label('Activar notificaciones por WhatsApp')
                            ->helperText('Al desactivar, ninguna notificación se enviará por WhatsApp aunque las credenciales sean correctas.')
                            ->onIcon('heroicon-m-check')
                            ->offIcon('heroicon-m-x-mark')
                            ->onColor('success'),
                    ]),

                // ── Fila 2: Credenciales (1 columna completa) ────────────────────
                Forms\Components\Section::make('Credenciales - Meta Cloud API')
                    ->description('Obtenga estos datos en Meta for Developers → su app → WhatsApp → API Setup.')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_numero_telefono')
                            ->label('Número de teléfono')
                            ->helperText('El número visible para el usuario final (ej: +57 300 123 4567) - distinto del Phone Number ID de abajo.')
                            ->placeholder('+57 300 123 4567')
                            ->maxLength(30)
                            ->columnSpanFull(),

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

                // ── Fila 2: OTP + Avisos a RRHH (2 columnas, estilo "hero") ──────
                // Pedido del usuario: algunas tarjetas con el mismo estilo de
                // "card hero" (degradado + orbes) que ya usa Mi Reglamento
                // Interno para destacar las 2 FUNCIONES reales de esta página
                // (enviar OTP, avisar a RRHH), distinto de las secciones de
                // configuración plana de arriba/abajo.
                // Grid::make(2) por sí solo NO siempre da 2 columnas: Filament
                // interpreta un entero suelto como "columnas en 'lg' en
                // adelante" (HasColumns::columns(), vendor/filament/forms),
                // dejando 1 columna por debajo de esa resolución - de ahí que
                // se viera en una sola columna. Se fuerza 'default' => 2
                // explícitamente para que sean 2 columnas siempre.
                Forms\Components\Grid::make(['default' => 2])
                    ->extraAttributes(['class' => 'rit-grid-top'])
                    ->schema([
                        Forms\Components\Section::make('Código de verificación (OTP)')
                            ->description('El código de un solo uso SIEMPRE se envía por una plantilla "Authentication" aprobada por Meta - nunca por texto libre. Pulse "Sincronizar plantillas" para elegir de una lista real en vez de escribir el nombre a mano.')
                            ->extraAttributes(['class' => 'rit-hero-section'])
                            ->schema([
                                Forms\Components\Select::make('whatsapp_plantilla_otp_nombre')
                                    ->label('Plantilla del OTP')
                                    ->options(fn () => $this->getOpcionesPlantillas())
                                    ->searchable()
                                    ->native(false)
                                    ->helperText(fn () => $this->getOpcionesPlantillas() === []
                                        ? 'Aún no ha sincronizado plantillas - pulse el botón abajo.'
                                        : 'Solo se muestran las que Meta ya sincronizó con esta cuenta.')
                                    ->columnSpanFull(),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('sincronizarPlantillas')
                                        ->label('Sincronizar plantillas')
                                        ->icon('heroicon-m-arrow-path')
                                        ->color('gray')
                                        ->action('sincronizarPlantillas'),
                                ]),
                            ]),

                        Forms\Components\Section::make('Avisos a RRHH')
                            ->description('Plantilla "Utility" para avisos del sistema (ej. "trabajador no entendió el RIT dos veces") a la empresa que dio opt-in en Mi Empresa → Información de Contacto. El correo de contacto sigue siendo el canal garantizado - esto es un extra.')
                            ->extraAttributes(['class' => 'rit-hero-section'])
                            ->schema([
                                Forms\Components\Select::make('whatsapp_plantilla_alerta_rrhh_nombre')
                                    ->label('Plantilla de alerta a RRHH')
                                    ->options(fn () => $this->getOpcionesPlantillas())
                                    ->searchable()
                                    ->native(false)
                                    ->helperText('Debe recibir el nombre del trabajador y su cédula como parámetros {{1}} y {{2}}, en ese orden.')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                // ── Fila 3: Webhook (1 columna completa) ─────────────────────────
                Forms\Components\Section::make('Webhook')
                    ->description('Configure el webhook en Meta para recibir mensajes y actualizaciones de estado.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('webhook_token')
                            ->label('Token de verificación del webhook')
                            ->content(fn () => new \Illuminate\Support\HtmlString(
                                view('filament.components.copiar-texto', ['texto' => $this->getWebhookToken()])->render()
                            ))
                            ->helperText('Generado automáticamente - cópielo exactamente en la consola de Meta. Si sospecha que se filtró, use "Regenerar" abajo (debe actualizarlo también en Meta).')
                            ->columnSpanFull(),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('regenerarWebhookToken')
                                ->label('Regenerar token')
                                ->icon('heroicon-m-arrow-path')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->modalDescription('El token actual dejará de funcionar. Debe actualizarlo también en la consola de Meta.')
                                ->action('regenerarWebhookToken'),
                        ]),

                        Forms\Components\Placeholder::make('webhook_url')
                            ->label('URL del webhook')
                            ->content(fn() => new \Illuminate\Support\HtmlString(
                                view('filament.components.copiar-texto', [
                                    'texto' => rtrim(config('app.url'), '/') . '/whatsapp/webhook',
                                ])->render()
                            ))
                            ->helperText('Configure esta URL en Meta for Developers → Webhooks, suscrita a los eventos messages y account_update.')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function guardar(): void
    {
        $datos = $this->form->getState();

        $mapa = [
            'whatsapp_habilitado'            => ['tipo' => 'boolean'],
            'whatsapp_numero_telefono'       => ['tipo' => 'text'],
            'whatsapp_phone_number_id'       => ['tipo' => 'text'],
            'whatsapp_business_account_id'   => ['tipo' => 'text'],
            'whatsapp_access_token'          => ['tipo' => 'text'],
            'whatsapp_plantilla_otp_nombre'  => ['tipo' => 'text'],
            'whatsapp_plantilla_alerta_rrhh_nombre' => ['tipo' => 'text'],
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
            $this->estadoConexion = $service->obtenerSaludCuenta();
        } finally {
            $this->verificando = false;
        }

        if ($this->estadoConexion['ok']) {
            // Mismas claves que escribe ProcesarWebhookWhatsAppJob al
            // recibir account_update - una sola fuente de verdad, se
            // actualice por webhook o por esta verificación manual.
            if (!empty($this->estadoConexion['calidad'])) {
                Configuracion::updateOrCreate(
                    ['clave' => 'whatsapp_calidad_actual'],
                    ['valor' => $this->estadoConexion['calidad'], 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => false]
                );
            }
            if (!empty($this->estadoConexion['limite_mensajeria'])) {
                Configuracion::updateOrCreate(
                    ['clave' => 'whatsapp_messaging_limit_tier'],
                    ['valor' => $this->estadoConexion['limite_mensajeria'], 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => false]
                );
            }

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
