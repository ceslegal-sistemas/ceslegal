<?php

namespace Tests\Feature;

use App\Jobs\ProcesarWebhookWhatsAppJob;
use App\Models\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-10-26): Meta solo permite UNA url de
 * webhook por app - al conectar Chatwoot, su asistente de configuración
 * sobrescribió la URL del webhook de la app con la de Chatwoot, así que el
 * proyecto dejó de recibir los eventos. Solución: Meta vuelve a apuntar al
 * webhook del proyecto, y el proyecto reenvía cada evento, tal cual, a la
 * URL de Chatwoot configurada - ambos sistemas siguen recibiendo todo.
 */
class WhatsAppRelayChatwootTest extends TestCase
{
    use RefreshDatabase;

    private array $payloadEjemplo = [
        'entry' => [
            [
                'changes' => [
                    ['value' => ['messages' => []], 'field' => 'messages'],
                ],
            ],
        ],
    ];

    public function test_reenvia_el_payload_crudo_a_la_url_de_chatwoot_configurada(): void
    {
        Configuracion::updateOrCreate(
            ['clave' => 'whatsapp_chatwoot_webhook_url'],
            ['valor' => 'https://contactenos.ceslegal.co/webhooks/whatsapp/+573123012844', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]
        );

        Http::fake(['contactenos.ceslegal.co/*' => Http::response('ok', 200)]);

        (new ProcesarWebhookWhatsAppJob($this->payloadEjemplo))->handle(app(\App\Services\NotificacionService::class));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://contactenos.ceslegal.co/webhooks/whatsapp/+573123012844'
                && $request['entry'][0]['changes'][0]['field'] === 'messages';
        });
    }

    public function test_sin_url_configurada_no_intenta_reenviar(): void
    {
        Http::fake();

        (new ProcesarWebhookWhatsAppJob($this->payloadEjemplo))->handle(app(\App\Services\NotificacionService::class));

        Http::assertNothingSent();
    }

    public function test_si_chatwoot_falla_el_procesamiento_normal_sigue(): void
    {
        Configuracion::updateOrCreate(
            ['clave' => 'whatsapp_chatwoot_webhook_url'],
            ['valor' => 'https://contactenos.ceslegal.co/webhooks/whatsapp/+573123012844', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]
        );

        Http::fake(['contactenos.ceslegal.co/*' => Http::response('error', 500)]);

        // No debe lanzar excepción aunque Chatwoot responda con error.
        (new ProcesarWebhookWhatsAppJob($this->payloadEjemplo))->handle(app(\App\Services\NotificacionService::class));

        $this->assertTrue(true);
    }
}
