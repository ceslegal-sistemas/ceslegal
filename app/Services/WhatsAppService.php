<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $phoneNumberId;
    protected string $businessAccountId;
    protected string $accessToken;
    protected string $apiVersion;
    protected bool $habilitado;

    public function __construct()
    {
        // Preferencia: BD (configurable por UI) > .env
        $this->habilitado        = (bool) Configuracion::obtener('whatsapp_habilitado', false);
        $this->phoneNumberId     = Configuracion::obtener('whatsapp_phone_number_id')
            ?: config('services.whatsapp.phone_number_id', '');
        $this->businessAccountId = (string) (Configuracion::obtener('whatsapp_business_account_id')
            ?: config('services.whatsapp.business_account_id'));
        $this->accessToken       = Configuracion::obtener('whatsapp_access_token')
            ?: config('services.whatsapp.access_token', '');
        $this->apiVersion        = config('services.whatsapp.api_version', 'v20.0');
    }

    // ─── API pública ────────────────────────────────────────────────────────────

    /**
     * Envía un mensaje de texto simple.
     *
     * @param  string $numero  Número en formato internacional sin '+' (ej: 573001234567)
     * @param  string $texto   Texto del mensaje
     */
    public function enviarTexto(string $numero, string $texto): array
    {
        return $this->enviar($numero, [
            'type' => 'text',
            'text' => ['body' => $texto],
        ]);
    }

    /**
     * Envía un mensaje usando una plantilla aprobada por Meta.
     *
     * @param  string $numero      Número destino
     * @param  string $plantilla   Nombre de la plantilla en Meta
     * @param  string $idioma      Código de idioma (ej: 'es_CO')
     * @param  array  $componentes Componentes con variables de la plantilla
     */
    public function enviarPlantilla(
        string $numero,
        string $plantilla,
        string $idioma = 'es_CO',
        array $componentes = []
    ): array {
        $payload = [
            'type'     => 'template',
            'template' => [
                'name'     => $plantilla,
                'language' => ['code' => $idioma],
            ],
        ];

        if (!empty($componentes)) {
            $payload['template']['components'] = $componentes;
        }

        return $this->enviar($numero, $payload);
    }

    /**
     * Envía el código OTP por la plantilla "Authentication" aprobada por
     * Meta. OTP SIEMPRE va por plantilla, nunca por enviarTexto(): es un
     * mensaje iniciado por el negocio, fuera de cualquier sesión de 24h, y
     * Meta exige esa categoría específica para este caso - contenido casi
     * fijo (el código y poco más), sin marca ni marketing.
     *
     * El nombre de la plantilla es configurable (BD > .env) en vez de
     * quedar fijo en código, por si Meta exige resometerla con otro nombre.
     */
    public function enviarPlantillaOtp(string $numero, string $codigo): array
    {
        $plantilla = Configuracion::obtener('whatsapp_plantilla_otp_nombre')
            ?: config('services.whatsapp.plantilla_otp', 'otp_verificacion');

        return $this->enviarPlantilla($numero, $plantilla, 'es_CO', [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $codigo],
                ],
            ],
            // Botón de "copiar código" de la plantilla Authentication -
            // Meta lo exige configurado así en la propia plantilla; el
            // parámetro aquí es el mismo código.
            [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => $codigo],
                ],
            ],
        ]);
    }

    /**
     * Envía una plantilla "Utility" genérica con N parámetros de texto en
     * el cuerpo - reusable para cualquier aviso de negocio (Fase 2, ver
     * docs/superpowers/specs/2026-10-19-whatsapp-fase1-otp-descargos-design.md).
     * A diferencia de enviarPlantillaOtp(), no asume botón de código - cada
     * evento define su propia plantilla en Meta y su propio nombre
     * configurable; este método solo arma el componente body.
     *
     * $botonUrlSufijo (Fase 3, notificaciones del proceso disciplinario,
     * 2026-10-21): si la plantilla tiene un botón de tipo URL con variable
     * (ej. ".../descargos/{{1}}"), aquí se manda SOLO el valor que
     * reemplaza esa variable (el token), nunca la URL completa - Meta la
     * reconstruye del lado del botón usando la URL ya registrada en la
     * plantilla. Si la plantilla no tiene botón, se deja null.
     *
     * @param  array<int, string> $parametrosBody  En el mismo orden en que la plantilla los espera ({{1}}, {{2}}, ...)
     */
    public function enviarPlantillaUtility(
        string $numero,
        string $plantilla,
        array $parametrosBody = [],
        ?string $botonUrlSufijo = null,
    ): array {
        $componentes = [];

        if ($parametrosBody !== []) {
            $componentes[] = [
                'type' => 'body',
                'parameters' => array_map(fn (string $texto) => ['type' => 'text', 'text' => $texto], $parametrosBody),
            ];
        }

        if ($botonUrlSufijo !== null) {
            $componentes[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => $botonUrlSufijo],
                ],
            ];
        }

        return $this->enviarPlantilla($numero, $plantilla, 'es_CO', $componentes);
    }

    /**
     * Verifica que las credenciales sean válidas consultando la info del número.
     */
    public function verificarConexion(): array
    {
        if (!$this->credencialesCompletas()) {
            return [
                'ok'      => false,
                'mensaje' => 'Credenciales incompletas. Ingrese el Phone Number ID y el Access Token.',
            ];
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'ok'      => true,
                    'mensaje' => 'Conexión exitosa.',
                    'numero'  => $data['display_phone_number'] ?? null,
                    'nombre'  => $data['verified_name'] ?? null,
                ];
            }

            $error = $response->json('error.message', 'Error desconocido');
            return ['ok' => false, 'mensaje' => "Error de Meta API: {$error}"];

        } catch (\Exception $e) {
            Log::error('WhatsAppService::verificarConexion error', ['error' => $e->getMessage()]);
            return ['ok' => false, 'mensaje' => 'Error de conexión: ' . $e->getMessage()];
        }
    }

    public function estaHabilitado(): bool
    {
        return $this->habilitado && $this->credencialesCompletas();
    }

    /**
     * "Account Health": no solo si conecta, sino el estado real del número
     * ante Meta - calidad, límite de mensajería, estado del nombre visible
     * y del código de verificación. Mismos datos que expone el panel de
     * Chatwoot (pedido explícito del usuario, 2026-10-19: "como un
     * chatwoot") - vía el parámetro `fields` de la Graph API, que
     * verificarConexion() no pedía.
     */
    public function obtenerSaludCuenta(): array
    {
        if (!$this->credencialesCompletas()) {
            return [
                'ok'      => false,
                'mensaje' => 'Credenciales incompletas. Ingrese el Phone Number ID y el Access Token.',
            ];
        }

        $campos = 'display_phone_number,verified_name,quality_rating,messaging_limit_tier,code_verification_status,platform_type,throughput';
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}?fields={$campos}";

        try {
            $response = Http::withToken($this->accessToken)->timeout(10)->get($url);

            if (!$response->successful()) {
                $error = $response->json('error.message', 'Error desconocido');
                return ['ok' => false, 'mensaje' => "Error de Meta API: {$error}"];
            }

            $data = $response->json();

            $nombreWaba = null;
            if (!empty($this->businessAccountId)) {
                $resWaba = Http::withToken($this->accessToken)->timeout(10)
                    ->get("https://graph.facebook.com/{$this->apiVersion}/{$this->businessAccountId}?fields=name");
                $nombreWaba = $resWaba->successful() ? $resWaba->json('name') : null;
            }

            return [
                'ok' => true,
                'numero' => $data['display_phone_number'] ?? null,
                'nombre_verificado' => $data['verified_name'] ?? null,
                'calidad' => $data['quality_rating'] ?? null,
                'limite_mensajeria' => $data['messaging_limit_tier'] ?? null,
                'verificacion_codigo' => $data['code_verification_status'] ?? null,
                'tipo_plataforma' => $data['platform_type'] ?? null,
                'nombre_waba' => $nombreWaba,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsAppService::obtenerSaludCuenta error', ['error' => $e->getMessage()]);
            return ['ok' => false, 'mensaje' => 'Error de conexión: ' . $e->getMessage()];
        }
    }

    /**
     * Trae las plantillas realmente aprobadas (o en revisión/rechazadas)
     * en la cuenta de WhatsApp Business - para elegir la del OTP de una
     * lista real en vez de escribir el nombre a ciegas (mismo "Sincronizar
     * plantillas" que ya tiene Chatwoot).
     *
     * @return array<int, array{nombre: string, estado: string, categoria: string, idioma: string}>
     */
    public function listarPlantillas(): array
    {
        if (empty($this->businessAccountId) || empty($this->accessToken)) {
            return [];
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->businessAccountId}/message_templates"
            . '?fields=name,status,category,language&limit=100';

        try {
            $response = Http::withToken($this->accessToken)->timeout(15)->get($url);

            if (!$response->successful()) {
                Log::warning('WhatsApp: no se pudieron sincronizar las plantillas', [
                    'error' => $response->json('error.message', $response->body()),
                ]);
                return [];
            }

            return collect($response->json('data', []))
                ->map(fn (array $p) => [
                    'nombre' => $p['name'] ?? '',
                    'estado' => $p['status'] ?? '',
                    'categoria' => $p['category'] ?? '',
                    'idioma' => $p['language'] ?? '',
                ])
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::error('WhatsAppService::listarPlantillas error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Crea en Meta una plantilla "Authentication" para el envío de OTP.
     * A diferencia de una plantilla Utility/Marketing, Meta genera el texto
     * del cuerpo automáticamente según el idioma - aquí solo se configuran
     * las banderas que la propia API de Meta expone para esta categoría
     * (recomendación de seguridad, minutos de expiración, botón de copiar
     * código). Queda en estado "PENDING" hasta que Meta la revise - eso
     * puede tomar desde minutos hasta horas y no se puede acelerar.
     *
     * @return array{ok: bool, mensaje?: string, id?: string, estado?: string}
     */
    public function crearPlantillaAuthentication(
        string $nombre,
        string $idioma = 'es_CO',
        int $minutosExpiracion = 10,
        bool $recomendacionSeguridad = true,
    ): array {
        if (empty($this->businessAccountId) || empty($this->accessToken)) {
            return ['ok' => false, 'mensaje' => 'Credenciales incompletas. Ingrese el WABA ID y el Access Token.'];
        }

        $payload = [
            'name' => $nombre,
            'language' => $idioma,
            'category' => 'AUTHENTICATION',
            'components' => [
                [
                    'type' => 'BODY',
                    'add_security_recommendation' => $recomendacionSeguridad,
                ],
                [
                    'type' => 'FOOTER',
                    'code_expiration_minutes' => $minutosExpiracion,
                ],
                [
                    'type' => 'BUTTONS',
                    'buttons' => [
                        [
                            'type' => 'OTP',
                            'otp_type' => 'COPY_CODE',
                            'text' => 'Copiar código',
                        ],
                    ],
                ],
            ],
        ];

        return $this->crearPlantilla($payload);
    }

    /**
     * Crea en Meta una plantilla "Utility" genérica con texto de cuerpo
     * libre y parámetros posicionales ({{1}}, {{2}}, ...) - reusable para
     * cualquier aviso de negocio, no solo el de RRHH. Meta exige un valor
     * de ejemplo por cada parámetro para poder revisar la plantilla.
     *
     * @param  array<int, string> $ejemploParametros  Un valor de ejemplo por cada {{n}} del cuerpo, en orden.
     * @return array{ok: bool, mensaje?: string, id?: string, estado?: string}
     */
    /**
     * $boton (Fase 3, notificaciones del proceso disciplinario, 2026-10-21):
     * opcional, agrega un botón de tipo URL con variable al final de la
     * plantilla - ej. ['texto' => 'Ver citación', 'url' => 'https://dominio/descargos/{{1}}',
     * 'ejemplo_url' => 'https://dominio/descargos/abc123']. Meta exige la
     * URL completa (con el placeholder {{1}}) y un ejemplo completo
     * (con un valor real de muestra) para poder revisar la plantilla.
     */
    public function crearPlantillaUtility(
        string $nombre,
        string $idioma,
        string $textoBody,
        array $ejemploParametros = [],
        ?array $boton = null,
    ): array {
        if (empty($this->businessAccountId) || empty($this->accessToken)) {
            return ['ok' => false, 'mensaje' => 'Credenciales incompletas. Ingrese el WABA ID y el Access Token.'];
        }

        $bodyComponent = [
            'type' => 'BODY',
            'text' => $textoBody,
        ];

        if ($ejemploParametros !== []) {
            $bodyComponent['example'] = ['body_text' => [$ejemploParametros]];
        }

        $components = [$bodyComponent];

        if ($boton !== null) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => [
                    [
                        'type' => 'URL',
                        'text' => $boton['texto'],
                        'url' => $boton['url'],
                        'example' => [$boton['ejemplo_url']],
                    ],
                ],
            ];
        }

        $payload = [
            'name' => $nombre,
            'language' => $idioma,
            'category' => 'UTILITY',
            'components' => $components,
        ];

        return $this->crearPlantilla($payload);
    }

    /**
     * POST crudo a message_templates - usado por los dos métodos públicos
     * de arriba, cada uno arma el payload específico de su categoría.
     */
    protected function crearPlantilla(array $payload): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->businessAccountId}/message_templates";

        try {
            $response = Http::withToken($this->accessToken)->timeout(15)->post($url, $payload);

            if (!$response->successful()) {
                $error = $response->json('error.message', $response->body());
                Log::error('WhatsApp error al crear plantilla', ['payload' => $payload, 'error' => $error]);
                return ['ok' => false, 'mensaje' => $error];
            }

            $data = $response->json();
            Log::info('WhatsApp plantilla creada', ['nombre' => $payload['name'], 'id' => $data['id'] ?? null]);

            return [
                'ok' => true,
                'id' => $data['id'] ?? null,
                'estado' => $data['status'] ?? 'PENDING',
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp excepción al crear plantilla', ['payload' => $payload, 'error' => $e->getMessage()]);
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    // ─── Internos ────────────────────────────────────────────────────────────────

    protected function enviar(string $numero, array $mensajePayload): array
    {
        if (!$this->estaHabilitado()) {
            Log::info('WhatsAppService: servicio deshabilitado o sin credenciales.');
            return ['ok' => false, 'mensaje' => 'WhatsApp deshabilitado.'];
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $payload = array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->limpiarNumero($numero),
        ], $mensajePayload);

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('WhatsApp mensaje enviado', [
                    'to'         => $numero,
                    'message_id' => $data['messages'][0]['id'] ?? null,
                ]);
                return ['ok' => true, 'data' => $data];
            }

            $error = $response->json('error.message', $response->body());
            Log::error('WhatsApp error al enviar', ['to' => $numero, 'error' => $error, 'status' => $response->status()]);
            return ['ok' => false, 'mensaje' => $error];

        } catch (\Exception $e) {
            Log::error('WhatsApp excepción al enviar', ['to' => $numero, 'error' => $e->getMessage()]);
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    protected function credencialesCompletas(): bool
    {
        return !empty($this->phoneNumberId) && !empty($this->accessToken);
    }

    protected function limpiarNumero(string $numero): string
    {
        // Quita espacios, guiones, paréntesis y '+' inicial
        $digitos = preg_replace('/[^\d]/', '', $numero);

        // Normaliza a formato internacional Colombia (57). El formulario guarda
        // 10 dígitos (ej: 3001234567); Meta exige el indicativo país: 573001234567.
        if (strlen($digitos) === 10) {
            $digitos = '57' . $digitos;
        }

        return $digitos;
    }
}
