<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use App\Services\NotificacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacionServiceContratoPorVencerTest extends TestCase
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

    private function crearSolicitud(Empresa $empresa, array $overrides = []): SolicitudContrato
    {
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
            'fecha_fin_contrato' => now()->addDays(30)->toDateString(),
        ], $overrides));
    }

    public function test_notifica_contrato_por_vencer_al_cliente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $cliente = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $solicitud = $this->crearSolicitud($empresa);

        app(NotificacionService::class)->notificarContratoPorVencer($solicitud);

        $registro = $cliente->notifications()->latest()->first();

        $this->assertNotNull($registro);
        $this->assertSame('Un contrato a término fijo está por vencer', $registro->data['title']);
        $this->assertStringContainsString('Juan Pérez', $registro->data['body']);
    }

    public function test_notifica_renovacion_automatica_con_prioridad_urgente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $cliente = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $solicitud = $this->crearSolicitud($empresa, ['fecha_fin_contrato' => '2027-06-30']);

        app(NotificacionService::class)->notificarRenovacionAutomatica($solicitud);

        $registro = $cliente->notifications()->latest()->first();

        $this->assertNotNull($registro);
        $this->assertSame('Un contrato se renovó automáticamente', $registro->data['title']);
        $this->assertStringContainsString('30/06/2027', $registro->data['body']);
    }

    public function test_notifica_revision_manual_requerida(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $cliente = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $solicitud = $this->crearSolicitud($empresa);

        app(NotificacionService::class)->notificarRevisionManualRenovacion($solicitud);

        $registro = $cliente->notifications()->latest()->first();

        $this->assertNotNull($registro);
        $this->assertSame('Un contrato necesita su revisión urgente', $registro->data['title']);
    }
}
