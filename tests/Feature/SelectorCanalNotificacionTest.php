<?php

namespace Tests\Feature;

use App\Livewire\FormularioDescargos;
use App\Models\DiligenciaDescargo;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Configuracion;
use App\Models\EmailTracking;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-10-26): selector real de canal de
 * notificación (correo / WhatsApp / ambos), en vez de que el correo fuera
 * "garantizado" en silencio sin que el trabajador o la empresa lo
 * eligieran. Ambos pueden estar activos a la vez sin problema.
 */
class SelectorCanalNotificacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ProcesoDisciplinarioObserver registra en el timeline con
        // Auth::id() ?? 1 - sin sesión activa necesita que exista un
        // usuario con id=1 para la FK (mismo patrón ya usado en
        // ProcesoDisciplinarioDisclaimerAutorizadorFechaTest).
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function trabajador(Empresa $empresa, array $overrides = []): Trabajador
    {
        return Trabajador::create(array_merge([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '1007568729',
            'genero' => 'masculino',
            'nombres' => 'Juan Pablo',
            'apellidos' => 'Rendon',
            'cargo' => 'Desarrollador',
            'email' => 'juan@test.com',
            'telefono' => '3001234567',
            'active' => true,
        ], $overrides));
    }

    public function test_el_trabajador_puede_elegir_correo_y_whatsapp_a_la_vez(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->trabajador($empresa);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0001',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);
        $diligencia = DiligenciaDescargo::create([
            'proceso_id' => $proceso->id,
            'fecha_diligencia' => now()->addDay(),
            'lugar_diligencia' => 'virtual',
        ]);

        Livewire::test(FormularioDescargos::class, ['diligencia' => $diligencia])
            ->set('emailOptIn', true)
            ->set('whatsappOptIn', true)
            ->call('aceptarDisclaimer');

        $trabajador->refresh();
        $this->assertTrue((bool) $trabajador->notificar_por_email);
        $this->assertTrue((bool) $trabajador->notificar_por_whatsapp);
        $this->assertNotNull($trabajador->whatsapp_consentimiento_en);
    }

    public function test_el_trabajador_puede_desactivar_el_correo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->trabajador($empresa);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0002',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);
        $diligencia = DiligenciaDescargo::create([
            'proceso_id' => $proceso->id,
            'fecha_diligencia' => now()->addDay(),
            'lugar_diligencia' => 'virtual',
        ]);

        Livewire::test(FormularioDescargos::class, ['diligencia' => $diligencia])
            ->set('emailOptIn', false)
            ->call('aceptarDisclaimer');

        $this->assertFalse((bool) $trabajador->refresh()->notificar_por_email);
    }

    public function test_empresa_que_desactiva_correo_sin_whatsapp_configurado_igual_recibe_el_correo(): void
    {
        // Sin WhatsApp disponible como alternativa (notificar_por_whatsapp=false),
        // el correo debe seguir siendo el respaldo obligatorio aunque la
        // empresa haya desactivado el toggle de correo.
        $empresa = Empresa::factory()->create(['active' => true, 'notificar_por_email' => false, 'notificar_por_whatsapp' => false]);
        $trabajador = $this->trabajador($empresa);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0003',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);
        User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true, 'email' => 'cliente@test.com']);

        app(DocumentGeneratorService::class)->enviarNotificacionDescargosAlCliente($proceso, 'descargos_realizados');

        $this->assertSame(1, EmailTracking::where('proceso_id', $proceso->id)->count());
    }

    public function test_empresa_que_desactiva_correo_con_whatsapp_configurado_omite_el_correo(): void
    {
        Configuracion::updateOrCreate(
            ['clave' => 'whatsapp_plantilla_aviso_empresa_nombre'],
            ['valor' => 'aviso_empresa_proceso', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => false]
        );

        $empresa = Empresa::factory()->create([
            'active' => true,
            'notificar_por_email' => false,
            'notificar_por_whatsapp' => true,
            'telefono' => '3001234567',
        ]);
        $trabajador = $this->trabajador($empresa);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0004',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);
        User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true, 'email' => 'cliente@test.com']);

        app(DocumentGeneratorService::class)->enviarNotificacionDescargosAlCliente($proceso, 'descargos_realizados');

        $this->assertSame(0, EmailTracking::where('proceso_id', $proceso->id)->count());
    }
}
