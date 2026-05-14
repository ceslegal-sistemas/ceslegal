<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $phoneNumberId;
    protected string $accessToken;
    protected string $apiVersion;
    protected bool $habilitado;

    public function __construct()
    {
        // Preferencia: BD (configurable por UI) > .env
        $this->habilitado     = (bool) Configuracion::obtener('whatsapp_habilitado', false);
        $this->phoneNumberId  = Configuracion::obtener('whatsapp_phone_number_id')
            ?: config('services.whatsapp.phone_number_id', '');
        $this->accessToken    = Configuracion::obtener('whatsapp_access_token')
            ?: config('services.whatsapp.access_token', '');
        $this->apiVersion     = config('services.whatsapp.api_version', 'v20.0');
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
        return preg_replace('/[^\d]/', '', $numero);
    }
}
