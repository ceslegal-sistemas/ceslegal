<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ConfiguracionWhatsapp;
use App\Jobs\ProcesarWebhookWhatsAppJob;
use App\Mail\OtpDescargos;
use App\Models\Configuracion;
use App\Models\DiligenciaDescargo;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Cubre la Fase 1 de WhatsApp (docs/superpowers/specs/2026-10-19-whatsapp-fase1-otp-descargos-design.md):
 * envío del OTP de Descargos con respaldo a correo, webhook (verificación,
 * firma, opt-out, calidad) y restricción de acceso a super_admin.
 */
class WhatsAppFase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ProcesoDisciplinarioObserver registra en el timeline con
        // Auth::id() ?? 1 - necesita un usuario id=1 para la FK aunque el
        // test no use actingAs (mismo gotcha de siempre en este recurso).
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);

        Configuracion::updateOrCreate(['clave' => 'whatsapp_habilitado'], ['valor' => '1', 'tipo' => 'boolean', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_phone_number_id'], ['valor' => '123456', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_access_token'], ['valor' => 'token-de-prueba', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_webhook_verify_token'], ['valor' => 'verify-123', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]);
    }

    private function crearDiligencia(array $trabajadorAtributos = []): DiligenciaDescargo
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create(array_merge([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '111222333',
            'genero' => 'masculino', 'nombres' => 'Trabajador', 'apellidos' => 'De Prueba', 'cargo' => 'Operario',
            'email' => 'trabajador.prueba@test.com', 'telefono' => '3001234567', 'active' => true,
        ], $trabajadorAtributos));

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-WA', 'empresa_id' => $empresa->id, 'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.', 'estado' => 'apertura',
        ]);

        return DiligenciaDescargo::create([
            'proceso_id' => $proceso->id,
            'fecha_diligencia' => now()->addDays(3),
            'lugar_diligencia' => 'virtual',
            'modalidad_descargos' => 'virtual',
        ]);
    }

    // ─── WhatsAppService::enviarPlantillaOtp() ────────────────────────────

    public function test_enviar_plantilla_otp_arma_el_payload_correcto(): void
    {
        Configuracion::updateOrCreate(['clave' => 'whatsapp_plantilla_otp_nombre'], ['valor' => 'otp_verificacion', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $resultado = app(WhatsAppService::class)->enviarPlantillaOtp('3001234567', '482913');

        $this->assertTrue($resultado['ok']);
        Http::assertSent(function ($request) {
            $data = $request->data();
            return $data['to'] === '573001234567'
                && $data['type'] === 'template'
                && $data['template']['name'] === 'otp_verificacion'
                && $data['template']['components'][0]['parameters'][0]['text'] === '482913';
        });
    }

    // ─── DiligenciaDescargo::enviarOtp() - las 4 ramas ────────────────────

    public function test_sin_opt_in_envia_solo_por_correo_comportamiento_actual(): void
    {
        Mail::fake();
        $diligencia = $this->crearDiligencia(); // notificar_por_whatsapp default false

        $diligencia->enviarOtp();

        Mail::assertSent(OtpDescargos::class);
        $this->assertSame('email', $diligencia->fresh()->otp_canal);
    }

    public function test_opt_in_whatsapp_exitoso_y_notificar_por_email_envia_ambos(): void
    {
        Mail::fake();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $diligencia = $this->crearDiligencia(['notificar_por_whatsapp' => true]);

        $diligencia->enviarOtp();

        Mail::assertSent(OtpDescargos::class);
        $this->assertSame('whatsapp,email', $diligencia->fresh()->otp_canal);
    }

    public function test_opt_in_whatsapp_exitoso_sin_correo_envia_solo_whatsapp(): void
    {
        Mail::fake();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $diligencia = $this->crearDiligencia(['notificar_por_whatsapp' => true, 'notificar_por_email' => false]);

        $diligencia->enviarOtp();

        Mail::assertNotSent(OtpDescargos::class);
        $this->assertSame('whatsapp', $diligencia->fresh()->otp_canal);
    }

    public function test_opt_in_whatsapp_que_falla_cae_a_correo_de_respaldo(): void
    {
        Mail::fake();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'plantilla rechazada']], 400)]);

        $diligencia = $this->crearDiligencia(['notificar_por_whatsapp' => true, 'notificar_por_email' => false]);

        $diligencia->enviarOtp();

        Mail::assertSent(OtpDescargos::class);
        $this->assertSame('email', $diligencia->fresh()->otp_canal);
    }

    // ─── Webhook: verificación GET ─────────────────────────────────────────

    public function test_webhook_verificacion_token_correcto_responde_el_challenge(): void
    {
        $response = $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-123&hub.challenge=abc123');

        $response->assertStatus(200);
        $response->assertSee('abc123', false);
    }

    public function test_webhook_verificacion_token_incorrecto_responde_403(): void
    {
        $response = $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=incorrecto&hub.challenge=abc123');

        $response->assertStatus(403);
    }

    // ─── Webhook: firma ─────────────────────────────────────────────────────

    public function test_webhook_sin_firma_responde_403(): void
    {
        $response = $this->postJson('/whatsapp/webhook', ['entry' => []]);

        $response->assertStatus(403);
    }

    public function test_webhook_con_firma_invalida_responde_403(): void
    {
        config(['services.whatsapp.app_secret' => 'secreto-de-prueba']);

        $response = $this->postJson('/whatsapp/webhook', ['entry' => []], [
            'X-Hub-Signature-256' => 'sha256=firmainvalida',
        ]);

        $response->assertStatus(403);
    }

    public function test_webhook_con_firma_valida_responde_200(): void
    {
        config(['services.whatsapp.app_secret' => 'secreto-de-prueba']);

        $body = json_encode(['entry' => []]);
        $firma = 'sha256=' . hash_hmac('sha256', $body, 'secreto-de-prueba');

        $response = $this->call('POST', '/whatsapp/webhook', [], [], [], [
            'HTTP_X-Hub-Signature-256' => $firma,
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(200);
    }

    // ─── Job: opt-out ────────────────────────────────────────────────────

    public function test_job_opt_out_apaga_notificar_por_whatsapp_del_trabajador_correcto(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '999888777',
            'genero' => 'femenino', 'nombres' => 'Opt', 'apellidos' => 'Out', 'cargo' => 'Op',
            'telefono' => '3009998888', 'notificar_por_whatsapp' => true, 'active' => true,
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messages' => [
                            ['from' => '573009998888', 'text' => ['body' => 'STOP']],
                        ],
                    ],
                ]],
            ]],
        ];

        (new ProcesarWebhookWhatsAppJob($payload))->handle(app(\App\Services\NotificacionService::class));

        $this->assertFalse($trabajador->fresh()->notificar_por_whatsapp);
    }

    public function test_job_opt_out_con_cero_coincidencias_no_revienta(): void
    {
        $payload = [
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => ['messages' => [['from' => '573000000000', 'text' => ['body' => 'baja']]]],
                ]],
            ]],
        ];

        (new ProcesarWebhookWhatsAppJob($payload))->handle(app(\App\Services\NotificacionService::class));

        $this->assertTrue(true); // No lanzó excepción.
    }

    // ─── Job: calidad ────────────────────────────────────────────────────

    public function test_job_calidad_red_apaga_whatsapp_habilitado_y_notifica_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'field' => 'account_update',
                    'value' => ['quality_rating' => 'RED'],
                ]],
            ]],
        ];

        (new ProcesarWebhookWhatsAppJob($payload))->handle(app(\App\Services\NotificacionService::class));

        $this->assertSame('0', Configuracion::where('clave', 'whatsapp_habilitado')->value('valor'));
        $this->assertSame('RED', Configuracion::obtener('whatsapp_calidad_actual'));
        $this->assertGreaterThan(0, $admin->notifications()->count());
    }

    public function test_job_calidad_sin_cambio_no_duplica_la_notificacion(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_calidad_actual'], ['valor' => 'GREEN', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => false]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'field' => 'account_update',
                    'value' => ['quality_rating' => 'GREEN'],
                ]],
            ]],
        ];

        (new ProcesarWebhookWhatsAppJob($payload))->handle(app(\App\Services\NotificacionService::class));

        $this->assertSame(0, $admin->notifications()->count());
    }

    // ─── Opt-in en FormularioDescargos ─────────────────────────────────────

    public function test_checkbox_de_opt_in_no_aparece_sin_telefono(): void
    {
        $diligencia = $this->crearDiligencia(['telefono' => null]);
        $diligencia->update(['otp_verificado_en' => now(), 'disclaimer_aceptado_en' => null]);

        Livewire::test(\App\Livewire\FormularioDescargos::class, ['diligencia' => $diligencia])
            ->set('etapa', 'disclaimer')
            ->assertDontSee('También quiero recibir mi código');
    }

    public function test_marcar_opt_in_persiste_consentimiento_con_ip_y_fecha(): void
    {
        $diligencia = $this->crearDiligencia();
        $diligencia->update(['otp_verificado_en' => now()]);

        Livewire::test(\App\Livewire\FormularioDescargos::class, ['diligencia' => $diligencia])
            ->set('etapa', 'disclaimer')
            ->set('whatsappOptIn', true)
            ->call('aceptarDisclaimer');

        $trabajador = $diligencia->proceso->trabajador->fresh();
        $this->assertTrue($trabajador->notificar_por_whatsapp);
        $this->assertNotNull($trabajador->whatsapp_consentimiento_en);
        $this->assertNotNull($trabajador->whatsapp_consentimiento_ip);
    }

    // ─── ConfiguracionWhatsapp: acceso restringido a super_admin ───────────

    public function test_bufete_con_el_permiso_de_shield_no_puede_acceder_aunque_lo_tenga(): void
    {
        Permission::findOrCreate('page_ConfiguracionWhatsapp', 'web');
        $bufete = User::factory()->create(['role' => 'bufete', 'active' => true]);
        $bufete->givePermissionTo('page_ConfiguracionWhatsapp');
        $this->actingAs($bufete);

        Livewire::test(ConfiguracionWhatsapp::class)->assertForbidden();
    }

    public function test_super_admin_si_puede_acceder(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($admin);

        Livewire::test(ConfiguracionWhatsapp::class)->assertSuccessful();
    }
}
