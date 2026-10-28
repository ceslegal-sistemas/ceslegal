<?php

namespace Tests\Feature;

use App\Jobs\ProcesarWebhookWhatsAppJob;
use App\Models\EmailTracking;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido del usuario (2026-10-27): "necesito que haya un acuse de que el
 * trabajador ya leyó el mensaje enviado por whatsapp" - el wamid devuelto
 * al enviar (whatsapp_message_id) se correlaciona con el webhook de estado
 * de Meta (ProcesarWebhookWhatsAppJob::procesarStatus()) para marcar
 * entregado/leído, igual que el pixel de apertura ya existente para correo.
 */
class WhatsappAcuseLecturaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ProcesoDisciplinarioObserver registra en el timeline con
        // Auth::id() ?? 1 - sin sesión activa necesita que exista un
        // usuario con id=1 para la FK (mismo gotcha de siempre en este
        // recurso).
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function trackingWhatsapp(array $overrides = []): EmailTracking
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '1007568740',
            'genero' => 'masculino',
            'nombres' => 'Luis',
            'apellidos' => 'Gomez',
            'cargo' => 'Operario',
            'email' => 'luis@test.com',
            'telefono' => '3009998877',
            'active' => true,
        ]);

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-WA-' . uniqid(),
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        return EmailTracking::create(array_merge([
            'token' => EmailTracking::generarToken(),
            'tipo_documento' => 'citacion',
            'proceso_id' => $proceso->id,
            'trabajador_id' => $trabajador->id,
            'email_destinatario' => $trabajador->email,
            'canales' => ['whatsapp'],
            'whatsapp_message_id' => 'wamid.TEST123',
            'enviado_en' => now(),
        ], $overrides));
    }

    public function test_webhook_delivered_marca_entregado(): void
    {
        $tracking = $this->trackingWhatsapp();

        ProcesarWebhookWhatsAppJob::dispatchSync([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.TEST123',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                            'recipient_id' => '573009998877',
                        ]],
                    ],
                ]],
            ]],
        ]);

        $tracking->refresh();
        $this->assertSame('entregado', $tracking->whatsapp_estado);
        $this->assertNotNull($tracking->whatsapp_entregado_en);
        $this->assertNull($tracking->whatsapp_leido_en);
    }

    public function test_webhook_read_marca_leido_y_entregado(): void
    {
        $tracking = $this->trackingWhatsapp();

        ProcesarWebhookWhatsAppJob::dispatchSync([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.TEST123',
                            'status' => 'read',
                            'timestamp' => (string) now()->timestamp,
                            'recipient_id' => '573009998877',
                        ]],
                    ],
                ]],
            ]],
        ]);

        $tracking->refresh();
        $this->assertSame('leido', $tracking->whatsapp_estado);
        $this->assertNotNull($tracking->whatsapp_leido_en);
        $this->assertNotNull($tracking->whatsapp_entregado_en);
        $this->assertSame('Leído por WhatsApp', $tracking->getEstadoLectura());
        $this->assertSame('success', $tracking->getColorEstado());
    }

    public function test_leido_no_se_pisa_por_un_delivered_tardio_reentregado(): void
    {
        $tracking = $this->trackingWhatsapp(['whatsapp_estado' => 'leido', 'whatsapp_leido_en' => now()->subMinute()]);

        ProcesarWebhookWhatsAppJob::dispatchSync([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.TEST123',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ]);

        $tracking->refresh();
        $this->assertSame('leido', $tracking->whatsapp_estado);
    }

    public function test_webhook_failed_marca_fallido(): void
    {
        $tracking = $this->trackingWhatsapp();

        ProcesarWebhookWhatsAppJob::dispatchSync([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.TEST123',
                            'status' => 'failed',
                            'errors' => [['title' => 'Template rejected']],
                        ]],
                    ],
                ]],
            ]],
        ]);

        $tracking->refresh();
        $this->assertSame('fallido', $tracking->whatsapp_estado);
        $this->assertSame('Falló el envío por WhatsApp', $tracking->getEstadoLectura());
        $this->assertSame('danger', $tracking->getColorEstado());
    }

    public function test_webhook_sin_tracking_correspondiente_no_truena(): void
    {
        ProcesarWebhookWhatsAppJob::dispatchSync([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.NO_EXISTE',
                            'status' => 'read',
                        ]],
                    ],
                ]],
            ]],
        ]);

        $this->assertTrue(true);
    }

    public function test_estado_enviado_muestra_badge_neutral(): void
    {
        $tracking = $this->trackingWhatsapp(['whatsapp_estado' => 'enviado']);

        $this->assertSame('Enviado por WhatsApp', $tracking->getEstadoLectura());
        $this->assertSame('info', $tracking->getColorEstado());
        $this->assertSame('WhatsApp: Enviado, esperando confirmación de entrega', $tracking->whatsapp_acuse_linea);
    }

    public function test_tracking_solo_correo_no_tiene_linea_de_acuse_whatsapp(): void
    {
        $tracking = $this->trackingWhatsapp(['canales' => ['email'], 'whatsapp_message_id' => null]);

        $this->assertNull($tracking->whatsapp_acuse_linea);
    }
}
