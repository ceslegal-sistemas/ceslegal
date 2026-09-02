<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratosVerificarVencimientosCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-02'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearSolicitud(array $overrides = []): SolicitudContrato
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        return SolicitudContrato::create(array_merge([
            'empresa_id' => $empresa->id,
            'tipo_contrato' => 'Contrato a Término Fijo',
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123456',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2026-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
        ], $overrides));
    }

    public function test_notifica_y_marca_notificado_dentro_de_la_ventana(): void
    {
        $solicitud = $this->crearSolicitud(['fecha_fin_contrato' => now()->addDays(40)->toDateString()]);

        $this->artisan('contratos:verificar-vencimientos')->assertExitCode(0);

        $solicitud->refresh();
        $this->assertNotNull($solicitud->notificado_vencimiento_en);
    }

    public function test_no_vuelve_a_notificar_si_ya_se_notifico(): void
    {
        $solicitud = $this->crearSolicitud([
            'fecha_fin_contrato' => now()->addDays(40)->toDateString(),
            'notificado_vencimiento_en' => now()->subDay(),
        ]);
        $yaNotificadoEn = $solicitud->notificado_vencimiento_en;

        $this->artisan('contratos:verificar-vencimientos');

        $solicitud->refresh();
        $this->assertTrue($solicitud->notificado_vencimiento_en->equalTo($yaNotificadoEn));
    }

    public function test_ignora_contratos_con_decision_de_no_renovar(): void
    {
        $solicitud = $this->crearSolicitud([
            'fecha_fin_contrato' => now()->addDays(20)->toDateString(),
            'decision_no_renovacion_en' => now(),
        ]);

        $this->artisan('contratos:verificar-vencimientos');

        $solicitud->refresh();
        $this->assertNull($solicitud->notificado_vencimiento_en);
        $this->assertSame(0, $solicitud->veces_prorrogado);
    }

    public function test_aplica_renovacion_automatica_al_vencer_el_plazo_legal(): void
    {
        $solicitud = $this->crearSolicitud([
            'fecha_inicio_periodo_actual' => '2026-01-01',
            'fecha_fin_contrato' => now()->addDays(30)->toDateString(),
        ]);

        $this->artisan('contratos:verificar-vencimientos');

        $solicitud->refresh();
        $this->assertSame(1, $solicitud->veces_prorrogado);
        $this->assertNotNull($solicitud->renovado_automaticamente_en);
    }

    public function test_marca_revision_manual_si_la_renovacion_excede_el_tope(): void
    {
        $solicitud = $this->crearSolicitud([
            'fecha_inicio_propuesta' => '2023-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
            'fecha_fin_contrato' => now()->addDays(30)->toDateString(),
            'veces_prorrogado' => 3,
        ]);

        $this->artisan('contratos:verificar-vencimientos');

        $solicitud->refresh();
        $this->assertTrue($solicitud->requiere_revision_manual_renovacion);
        $this->assertNull($solicitud->renovado_automaticamente_en);
    }
}
