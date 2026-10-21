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

        $estado = [
            'whatsapp_habilitado'            => (bool) Configuracion::obtener('whatsapp_habilitado', false),
            'whatsapp_numero_telefono'       => Configuracion::obtener('whatsapp_numero_telefono', ''),
            'whatsapp_phone_number_id'       => Configuracion::obtener('whatsapp_phone_number_id', ''),
            'whatsapp_business_account_id'   => Configuracion::obtener('whatsapp_business_account_id', ''),
            'whatsapp_access_token'          => Configuracion::obtener('whatsapp_access_token', ''),
            'whatsapp_plantilla_otp_nombre'  => Configuracion::obtener('whatsapp_plantilla_otp_nombre', config('services.whatsapp.plantilla_otp', 'otp_verificacion')),
            'whatsapp_plantilla_alerta_rrhh_nombre' => Configuracion::obtener('whatsapp_plantilla_alerta_rrhh_nombre', ''),
        ];

        // Fase 3 (2026-10-21): una plantilla configurable por evento del
        // proceso disciplinario, mismo patrón del OTP/RRHH de arriba -
        // precarga el nombre guardado o, si nunca se ha guardado, el
        // nombre por defecto de cada definición.
        foreach ($this->getDefinicionesPlantillasDescargos() as $definicion) {
            $estado[$definicion['clave']] = Configuracion::obtener($definicion['clave'], $definicion['nombre_defecto']);
        }

        $this->form->fill($estado);
    }

    /**
     * Las 7 plantillas de notificación del proceso disciplinario (Fase 3 de
     * WhatsApp, 2026-10-21) - citación, sanción, constancia de no sanción,
     * resolución de impugnación, recordatorio y estado de descargos al
     * TRABAJADOR, más un aviso compartido para la EMPRESA/RRHH (estado al
     * cliente y descargos no realizados reusan la misma plantilla, pedido
     * explícito del usuario). Solo citación, sanción y recordatorio tienen
     * botón con enlace real al portal (las otras 4 no tienen ningún visor
     * público al que enlazar hoy - el documento solo va adjunto por
     * correo - así que su aviso de WhatsApp es un FYI de texto sin botón).
     *
     * @return array<string, array{clave: string, nombre_defecto: string, label: string, texto: string, ejemplo: array<int,string>, boton: array{texto: string, url: string, ejemplo_url: string}|null}>
     */
    protected function getDefinicionesPlantillasDescargos(): array
    {
        $dominio = rtrim(config('app.url'), '/');

        return [
            'citacion' => [
                'clave' => 'whatsapp_plantilla_citacion_nombre',
                'nombre_defecto' => 'citacion_descargos',
                'label' => 'Citación a descargos',
                'texto' => 'Hola {{1}}, ha sido citado a audiencia de descargos en el proceso {{2}}. Consulte el documento completo en el enlace.',
                'ejemplo' => ['Juan Pérez', 'PD-2026-0001'],
                'boton' => [
                    'texto' => 'Ver citación',
                    'url' => $dominio . '/descargos/{{1}}',
                    'ejemplo_url' => $dominio . '/descargos/abc123tokenejemplo',
                ],
            ],
            'sancion' => [
                'clave' => 'whatsapp_plantilla_sancion_nombre',
                'nombre_defecto' => 'notificacion_sancion',
                'label' => 'Notificación de sanción',
                'texto' => 'Hola {{1}}, se ha emitido una notificación de {{2}} en su proceso {{3}}. Consulte el documento completo en el enlace.',
                'ejemplo' => ['Juan Pérez', 'Suspensión', 'PD-2026-0001'],
                'boton' => [
                    'texto' => 'Ver resolución',
                    'url' => $dominio . '/impugnar/{{1}}',
                    'ejemplo_url' => $dominio . '/impugnar/abc123tokenejemplo',
                ],
            ],
            'no_sancion' => [
                'clave' => 'whatsapp_plantilla_no_sancion_nombre',
                'nombre_defecto' => 'constancia_no_sancion',
                'label' => 'Constancia de no sanción',
                'texto' => 'Hola {{1}}, el proceso disciplinario {{2}} en su contra ha finalizado sin sanción. Revise su correo para ver la constancia completa.',
                'ejemplo' => ['Juan Pérez', 'PD-2026-0001'],
                'boton' => null,
            ],
            'resolucion_impugnacion' => [
                'clave' => 'whatsapp_plantilla_resolucion_impugnacion_nombre',
                'nombre_defecto' => 'resolucion_impugnacion',
                'label' => 'Resolución de impugnación',
                'texto' => 'Hola {{1}}, se ha emitido la resolución de su impugnación en el proceso {{2}}: {{3}}. Revise su correo para ver el documento completo.',
                'ejemplo' => ['Juan Pérez', 'PD-2026-0001', 'Sanción Confirmada'],
                'boton' => null,
            ],
            'recordatorio' => [
                'clave' => 'whatsapp_plantilla_recordatorio_nombre',
                'nombre_defecto' => 'recordatorio_descargos',
                'label' => 'Recordatorio de descargos',
                'texto' => 'Hola {{1}}, tiene pendiente presentar sus descargos en el proceso {{2}}. Hágalo antes de la fecha límite en el enlace.',
                'ejemplo' => ['Juan Pérez', 'PD-2026-0001'],
                'boton' => [
                    'texto' => 'Presentar descargos',
                    'url' => $dominio . '/descargos/{{1}}',
                    'ejemplo_url' => $dominio . '/descargos/abc123tokenejemplo',
                ],
            ],
            'estado_descargos' => [
                'clave' => 'whatsapp_plantilla_estado_descargos_nombre',
                'nombre_defecto' => 'estado_descargos',
                'label' => 'Estado de descargos (al trabajador)',
                'texto' => 'Hola {{1}}, su proceso {{2}} tuvo la siguiente actualización: {{3}}. Revise su correo para más detalles.',
                'ejemplo' => ['Juan Pérez', 'PD-2026-0001', 'Descargos Completados'],
                'boton' => null,
            ],
            'aviso_empresa' => [
                'clave' => 'whatsapp_plantilla_aviso_empresa_nombre',
                'nombre_defecto' => 'aviso_empresa_proceso',
                'label' => 'Aviso a la empresa / RRHH',
                'texto' => 'Alerta LUPE Legal: el proceso disciplinario de {{1}} (cédula {{2}}) tuvo la siguiente actualización: {{3}}. Ingrese al panel para más detalles.',
                'ejemplo' => ['Juan Pérez', '1020304050', 'Descargos no realizados'],
                'boton' => null,
            ],
        ];
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

    /**
     * Crea en Meta la plantilla "Authentication" para el OTP con la
     * configuración acordada (2026-10-21): es_CO, código expira en 10
     * minutos, con recomendación de seguridad y botón "Copiar código".
     * Meta la deja en PENDING hasta revisarla - no se puede acelerar esa
     * parte desde aquí, solo someterla.
     */
    public function crearPlantillaOtp(): void
    {
        $nombre = $this->form->getState()['whatsapp_plantilla_otp_nombre']
            ?: Configuracion::obtener('whatsapp_plantilla_otp_nombre', 'otp_verificacion');

        $resultado = app(WhatsAppService::class)->crearPlantillaAuthentication($nombre, 'es_CO', 10, true);

        $this->notificarResultadoCreacionPlantilla($resultado, $nombre);
    }

    /**
     * Crea en Meta la plantilla "Utility" de aviso a RRHH, con el texto
     * acordado (2026-10-21): {{1}} = nombre del trabajador, {{2}} = cédula,
     * en ese orden.
     */
    public function crearPlantillaRrhh(): void
    {
        $nombre = 'aviso_rrhh_no_comprendio_rit';
        $texto = 'Alerta LUPE Legal: el trabajador {{1}}, identificado con cédula {{2}}, no comprendió el Reglamento Interno de Trabajo tras dos intentos. Por favor contacte a RRHH para dar seguimiento.';

        $resultado = app(WhatsAppService::class)->crearPlantillaUtility(
            $nombre,
            'es_CO',
            $texto,
            ['Juan Pérez', '1020304050'],
        );

        $this->notificarResultadoCreacionPlantilla($resultado, $nombre);
    }

    /**
     * Crea en Meta las 7 plantillas de notificación del proceso
     * disciplinario (Fase 3, 2026-10-21) que todavía no existan - omite
     * silenciosamente las que ya están sincronizadas (por nombre) para no
     * reenviar duplicados a Meta, que las rechaza de todas formas.
     */
    public function crearPlantillasDescargosPendientes(): void
    {
        $service = app(WhatsAppService::class);
        $nombresSincronizados = collect($this->getOpcionesPlantillas())->keys();
        $estadoForm = $this->form->getState();

        $creadas = [];
        $omitidas = [];
        $fallidas = [];

        foreach ($this->getDefinicionesPlantillasDescargos() as $definicion) {
            $nombre = $estadoForm[$definicion['clave']] ?? null;
            $nombre = $nombre !== null && $nombre !== '' ? $nombre : $definicion['nombre_defecto'];

            if ($nombresSincronizados->contains($nombre)) {
                $omitidas[] = $nombre;
                continue;
            }

            $resultado = $service->crearPlantillaUtility(
                $nombre,
                'es_CO',
                $definicion['texto'],
                $definicion['ejemplo'],
                $definicion['boton'],
            );

            if ($resultado['ok']) {
                $creadas[] = $nombre;
            } else {
                $fallidas[] = "{$nombre}: " . ($resultado['mensaje'] ?? 'error desconocido');
            }
        }

        if ($creadas !== []) {
            $this->sincronizarPlantillas();
        }

        if ($fallidas !== []) {
            Notification::make()
                ->title('Algunas plantillas no se pudieron crear')
                ->body(implode(' | ', $fallidas))
                ->danger()
                ->send();

            return;
        }

        if ($creadas === []) {
            Notification::make()
                ->title('Nada por crear')
                ->body('Las 7 plantillas ya existían en Meta (omitidas: ' . implode(', ', $omitidas) . ').')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Plantillas enviadas a revisión de Meta')
            ->body(count($creadas) . ' plantilla(s) nueva(s): ' . implode(', ', $creadas) . '.')
            ->success()
            ->send();
    }

    protected function notificarResultadoCreacionPlantilla(array $resultado, string $nombre): void
    {
        if (!$resultado['ok']) {
            Notification::make()
                ->title('No se pudo crear la plantilla')
                ->body($resultado['mensaje'] ?? 'Error desconocido.')
                ->danger()
                ->send();

            return;
        }

        // Se refresca el cache de plantillas para que la nueva aparezca de
        // inmediato en el Select, aunque Meta todavía no la haya aprobado
        // (queda visible como "pending" gracias al campo estado).
        $this->sincronizarPlantillas();

        Notification::make()
            ->title('Plantilla enviada a revisión de Meta')
            ->body("\"{$nombre}\" quedó en estado \"{$resultado['estado']}\" - Meta puede tardar minutos u horas en aprobarla.")
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
                    ->columnSpanFull()
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
                    ->columnSpanFull()
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
                //
                // CAUSA RAÍZ REAL del bug de "1 sola columna" (confirmada
                // con un dump real del HTML, tras 5 intentos fallidos
                // peleando con Forms\Components\Grid y extraAttributes):
                // Filament\Forms\Components\Section::setUp() llama
                // $this->columnSpan('full') en SU PROPIO constructor
                // (vendor/filament/forms/src/Components/Section.php:61).
                // Toda Section es "columnSpanFull" por defecto, sin que el
                // desarrollador lo pida. Por eso, aunque el contenedor
                // (Grid o, como aquí, el formulario raíz) tuviera
                // correctamente 2 columnas, cada Section seguía ocupando
                // "grid-column: 1 / -1" (las DOS columnas) por su propia
                // cuenta - de ahí que se vieran apiladas una sobre otra
                // como si solo hubiera 1 columna, sin importar cuánto se
                // ajustara el contenedor. Hace falta pedir expresamente
                // ->columnSpan(1) en cada Section para anular ese default.
                Forms\Components\Section::make('Código de verificación (OTP)')
                    ->description('El código de un solo uso SIEMPRE se envía por una plantilla "Authentication" aprobada por Meta - nunca por texto libre. Pulse "Sincronizar plantillas" para elegir de una lista real en vez de escribir el nombre a mano.')
                    ->columnSpan(1)
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

                            // Crea la plantilla "Authentication" del OTP en Meta
                            // (pedido del usuario, 2026-10-21) - queda PENDING
                            // hasta que Meta la revise, no es instantáneo.
                            Forms\Components\Actions\Action::make('crearPlantillaOtp')
                                ->label('Crear plantilla en Meta')
                                ->icon('heroicon-m-plus-circle')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->modalDescription('Se enviará a revisión de Meta la plantilla "otp_verificacion" (es_CO, código expira en 10 minutos, con botón "Copiar código"). Si ya existe una con ese nombre, Meta la rechazará por duplicada.')
                                ->action('crearPlantillaOtp'),
                        ]),
                    ]),

                Forms\Components\Section::make('Avisos a RRHH')
                    ->description('Plantilla "Utility" para avisos del sistema (ej. "trabajador no entendió el RIT dos veces") a la empresa que dio opt-in en Mi Empresa → Información de Contacto. El correo de contacto sigue siendo el canal garantizado - esto es un extra.')
                    ->columnSpan(1)
                    ->extraAttributes(['class' => 'rit-hero-section'])
                    ->schema([
                        Forms\Components\Select::make('whatsapp_plantilla_alerta_rrhh_nombre')
                            ->label('Plantilla de alerta a RRHH')
                            ->options(fn () => $this->getOpcionesPlantillas())
                            ->searchable()
                            ->native(false)
                            ->helperText('Debe recibir el nombre del trabajador y su cédula como parámetros {{1}} y {{2}}, en ese orden.')
                            ->columnSpanFull(),

                        // Crea la plantilla "Utility" de aviso a RRHH en Meta
                        // (pedido del usuario, 2026-10-21), con el texto
                        // acordado: {{1}} = nombre del trabajador, {{2}} = cédula.
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('crearPlantillaRrhh')
                                ->label('Crear plantilla en Meta')
                                ->icon('heroicon-m-plus-circle')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->modalDescription('Se enviará a revisión de Meta la plantilla "aviso_rrhh_no_comprendio_rit" (es_CO, categoría Utility) con el texto: "Alerta LUPE Legal: el trabajador {{1}}, identificado con cédula {{2}}, no comprendió el Reglamento Interno de Trabajo tras dos intentos. Por favor contacte a RRHH para dar seguimiento." Si ya existe una con ese nombre, Meta la rechazará por duplicada.')
                                ->action('crearPlantillaRrhh'),
                        ]),
                    ]),

                // ── Fila 2.5: Notificaciones del proceso disciplinario ────────────
                // Pedido del usuario (2026-10-21): "necesito plantillas para la
                // notificación de citación a descargos, emisión de la sanción,
                // impugnación, etc. - todas las partes donde se notifica al
                // cliente [y a] los trabajadores del cliente por correo también
                // que sea por WhatsApp". Un Select por evento (mismo patrón de
                // OTP/RRHH arriba) + un solo botón que crea en Meta las que
                // todavía no existan (ver getDefinicionesPlantillasDescargos()
                // y crearPlantillasDescargosPendientes()).
                Forms\Components\Section::make('Notificaciones del proceso disciplinario')
                    ->description('El correo sigue siendo el canal garantizado para estos eventos (lleva el documento adjunto) - WhatsApp es un aviso adicional, solo si el trabajador o la empresa dieron su opt-in.')
                    ->columnSpanFull()
                    ->schema([
                        ...collect($this->getDefinicionesPlantillasDescargos())
                            ->map(fn (array $definicion, string $clave) => Forms\Components\Select::make($definicion['clave'])
                                ->label($definicion['label'])
                                ->options(fn () => $this->getOpcionesPlantillas())
                                ->searchable()
                                ->native(false)
                                // Las 6 primeras van al trabajador (2 por fila);
                                // "aviso_empresa" es la única para la empresa/RRHH
                                // - a ancho completo para no dejarla con un hueco
                                // vacío al lado (7 campos = número impar en una
                                // grilla de 2 columnas) y para que se note que es
                                // conceptualmente distinta de las demás.
                                ->columnSpan($clave === 'aviso_empresa' ? 'full' : 1))
                            ->values()
                            ->all(),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('crearPlantillasDescargosPendientes')
                                ->label('Crear plantillas pendientes en Meta')
                                ->icon('heroicon-m-plus-circle')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->modalDescription('Se enviarán a revisión de Meta las plantillas de esta sección que todavía no existan en la cuenta (las que ya existen se omiten, no se duplican).')
                                ->action('crearPlantillasDescargosPendientes'),
                        ])->columnSpanFull(),
                    ])
                    ->columns(['default' => 2]),

                // ── Fila 3: Webhook (1 columna completa) ─────────────────────────
                Forms\Components\Section::make('Webhook')
                    ->description('Configure el webhook en Meta para recibir mensajes y actualizaciones de estado.')
                    ->collapsed()
                    ->columnSpanFull()
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
            ->columns(['default' => 2])
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

        foreach ($this->getDefinicionesPlantillasDescargos() as $definicion) {
            $mapa[$definicion['clave']] = ['tipo' => 'text'];
        }

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
