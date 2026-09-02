<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use App\Services\PlazoContratoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo "renovar/no-renovar contrato a término fijo con alerta de 30
 * días" (Art. 46 CST, modificado por el Art. 6 de la Ley 2466 de 2025).
 * Diseño y reglas de negocio confirmadas explícitamente con el usuario:
 * alerta al cliente 45 días antes, plazo legal de 30 días en días
 * CALENDARIO (no hábiles), renovación automática con las 3 reglas
 * completas del Art. 46 (mismo período, mínimo 1 año tras la 4a
 * prórroga, tope de 4 años).
 */
class PlazoContratoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
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
            'fecha_fin_contrato' => '2026-12-31',
        ], $overrides));
    }

    public function test_dias_hasta_vencimiento_cuenta_dias_calendario(): void
    {
        $solicitud = $this->crearSolicitud(['fecha_fin_contrato' => '2026-09-12']);
        $service = new PlazoContratoService();

        $this->assertSame(10, $service->diasHastaVencimiento($solicitud));
    }

    public function test_esta_en_ventana_de_alerta_a_45_dias_pero_no_a_46(): void
    {
        $service = new PlazoContratoService();

        $dentro = $this->crearSolicitud(['fecha_fin_contrato' => now()->addDays(45)->toDateString()]);
        $fuera  = $this->crearSolicitud(['fecha_fin_contrato' => now()->addDays(46)->toDateString()]);

        $this->assertTrue($service->estaEnVentanaDeAlerta($dentro));
        $this->assertFalse($service->estaEnVentanaDeAlerta($fuera));
    }

    public function test_no_alerta_si_ya_se_decidio_no_renovar(): void
    {
        $solicitud = $this->crearSolicitud([
            'fecha_fin_contrato' => now()->addDays(10)->toDateString(),
            'decision_no_renovacion_en' => now(),
        ]);

        $this->assertFalse((new PlazoContratoService())->estaEnVentanaDeAlerta($solicitud));
    }

    public function test_ya_vencio_plazo_legal_a_los_30_dias_exactos_pero_no_a_31(): void
    {
        $service = new PlazoContratoService();

        $limite = $this->crearSolicitud(['fecha_fin_contrato' => now()->addDays(30)->toDateString()]);
        $antes  = $this->crearSolicitud(['fecha_fin_contrato' => now()->addDays(31)->toDateString()]);

        $this->assertTrue($service->yaVencioPlazoLegalSinDecision($limite));
        $this->assertFalse($service->yaVencioPlazoLegalSinDecision($antes));
    }

    public function test_calcula_la_misma_duracion_del_periodo_que_se_vence(): void
    {
        // Período de 6 meses (2026-01-01 a 2026-06-30).
        $solicitud = $this->crearSolicitud([
            'fecha_inicio_periodo_actual' => '2026-01-01',
            'fecha_fin_contrato' => '2026-06-30',
            'veces_prorrogado' => 0,
        ]);

        $calculo = (new PlazoContratoService())->calcularProximaRenovacion($solicitud);

        $this->assertTrue($calculo['puede_renovar']);
        $this->assertSame('2026-07-01', $calculo['nueva_fecha_inicio']->toDateString());
        // Mismo número de días que el período que se vence (Jan1-Jun30 = 181
        // días inclusive) -> Jul1-Dec28 (181 días inclusive).
        $this->assertSame('2026-12-28', $calculo['nueva_fecha_fin']->toDateString());
    }

    public function test_tras_la_cuarta_prorroga_fuerza_minimo_un_anio(): void
    {
        // Períodos de 6 meses, esta sería la 4a prórroga (veces_prorrogado=3 -> numeroProrroga=4).
        $solicitud = $this->crearSolicitud([
            'fecha_inicio_periodo_actual' => '2026-01-01',
            'fecha_fin_contrato' => '2026-06-30',
            'veces_prorrogado' => 3,
        ]);

        $calculo = (new PlazoContratoService())->calcularProximaRenovacion($solicitud);

        $this->assertSame('2026-07-01', $calculo['nueva_fecha_inicio']->toDateString());
        // 1 año exacto desde el 2026-07-01 (ambos incluidos) -> 2027-06-30.
        $this->assertSame('2027-06-30', $calculo['nueva_fecha_fin']->toDateString());
    }

    public function test_no_renueva_automaticamente_si_supera_el_tope_de_4_anios(): void
    {
        // Inicio original hace casi 4 años; el siguiente período de 1 año lo superaría.
        $solicitud = $this->crearSolicitud([
            'fecha_inicio_propuesta' => '2023-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
            'fecha_fin_contrato' => '2026-06-30',
            'veces_prorrogado' => 3,
        ]);

        $service = new PlazoContratoService();
        $calculo = $service->calcularProximaRenovacion($solicitud);

        $this->assertFalse($calculo['puede_renovar']);
        $this->assertTrue($calculo['excede_tope_4_anios']);
    }

    public function test_aplicar_renovacion_automatica_actualiza_el_contrato(): void
    {
        $solicitud = $this->crearSolicitud([
            'fecha_inicio_periodo_actual' => '2026-01-01',
            'fecha_fin_contrato' => '2026-06-30',
            'veces_prorrogado' => 0,
        ]);

        (new PlazoContratoService())->aplicarRenovacionAutomatica($solicitud);
        $solicitud->refresh();

        $this->assertSame(1, $solicitud->veces_prorrogado);
        $this->assertSame('2026-07-01', $solicitud->fecha_inicio_periodo_actual->toDateString());
        $this->assertSame('2026-12-28', $solicitud->fecha_fin_contrato->toDateString());
        $this->assertNotNull($solicitud->renovado_automaticamente_en);
        $this->assertFalse($solicitud->requiere_revision_manual_renovacion);
    }

    public function test_aplicar_renovacion_automatica_marca_revision_manual_si_excede_el_tope(): void
    {
        $solicitud = $this->crearSolicitud([
            'fecha_inicio_propuesta' => '2023-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
            'fecha_fin_contrato' => '2026-06-30',
            'veces_prorrogado' => 3,
        ]);

        (new PlazoContratoService())->aplicarRenovacionAutomatica($solicitud);
        $solicitud->refresh();

        $this->assertTrue($solicitud->requiere_revision_manual_renovacion);
        $this->assertNull($solicitud->renovado_automaticamente_en);
        // No debe haber tocado la fecha fin real si no se pudo renovar.
        $this->assertSame('2026-06-30', $solicitud->fecha_fin_contrato->toDateString());
    }
}
