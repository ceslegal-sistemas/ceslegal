<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * "Sí, renovar"/"No renovar" no tienen sentido sobre un contrato que ni
 * siquiera está aprobado todavía (borrador/rechazado), aunque sea a
 * término fijo y su fecha_fin_contrato caiga dentro de la ventana de 45
 * días - hallazgo real del usuario (2026-09-02).
 */
class RenovacionSoloParaContratosAprobadosTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-02'));
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearSolicitud(array $overrides = []): SolicitudContrato
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        return SolicitudContrato::create(array_merge([
            'empresa_id' => $empresa->id,
            'estado' => 'aprobado',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2026-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
            'fecha_fin_contrato' => now()->addDays(20)->toDateString(),
        ], $overrides));
    }

    public function test_no_aplica_si_esta_en_borrador(): void
    {
        $solicitud = $this->crearSolicitud(['estado' => 'borrador']);

        $this->assertFalse(SolicitudContratoResource::enVentanaDeDecisionRenovacion($solicitud));
    }

    public function test_no_aplica_si_esta_rechazado(): void
    {
        $solicitud = $this->crearSolicitud(['estado' => 'rechazado']);

        $this->assertFalse(SolicitudContratoResource::enVentanaDeDecisionRenovacion($solicitud));
    }

    public function test_si_aplica_cuando_esta_aprobado(): void
    {
        $solicitud = $this->crearSolicitud(['estado' => 'aprobado']);

        $this->assertTrue(SolicitudContratoResource::enVentanaDeDecisionRenovacion($solicitud));
    }
}
