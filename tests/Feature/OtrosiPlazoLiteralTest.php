<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ModificacionContractual;
use App\Models\SolicitudContrato;
use App\Models\User;
use App\Services\SolicitudContratoIAService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Decisión confirmada explícitamente con el usuario: el Otrosí de Plazo se
 * genera con una plantilla LITERAL (texto exacto del Word real), sin IA de
 * por medio - a diferencia de los otros 4 tipos de ModificacionContractual
 * (salario/cargo/jornada/tipo_contrato), que sí redactan en texto libre.
 */
class OtrosiPlazoLiteralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
        Carbon::setTestNow(Carbon::parse('2026-09-02'));
        // Si el flujo de plazo llamara a Gemini por error, el test debe
        // fallar en vez de intentar una llamada de red real.
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response(['error' => 'no debería llamarse'], 500),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearModificacionPlazo(): ModificacionContractual
    {
        $empresa = Empresa::factory()->create([
            'active' => true,
            'razon_social' => 'EMPRESA DE PRUEBA S.A.S.',
            'nit' => '900123456-7',
            'representante_legal' => 'Carlos Ruiz',
            'ciudad' => 'Bogotá D.C.',
            'departamento' => 'Cundinamarca',
        ]);

        $solicitud = SolicitudContrato::create([
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
            'fecha_fin_contrato' => '2026-06-30',
            'veces_prorrogado' => 0,
        ]);

        return ModificacionContractual::create([
            'solicitud_contrato_id' => $solicitud->id,
            'empresa_id' => $empresa->id,
            'tipo_modificacion' => 'plazo',
            'valor_anterior' => '2026-06-30',
            'valor_nuevo' => '2026-12-28',
            'fecha_efectiva' => '2026-07-01',
        ]);
    }

    public function test_redacta_el_otrosi_de_plazo_de_forma_literal_sin_llamar_ia(): void
    {
        $modificacion = $this->crearModificacionPlazo();

        $html = app(SolicitudContratoIAService::class)->redactarOtrosi($modificacion);

        Http::assertNothingSent();
        $this->assertStringContainsString('OTROSÍ DE PLAZO', mb_strtoupper($html));
        $this->assertStringContainsString('EMPRESA DE PRUEBA S.A.S.', $html);
        $this->assertStringContainsString('Juan Pérez', $html);
        $this->assertStringContainsString('900123456-7', $html);
        // Período que se vence: 2026-01-01 a 2026-06-30 = 6 meses exactos.
        $this->assertStringContainsString('6 meses', $html);
    }

    public function test_generar_pdf_aplica_la_prorroga_al_contrato(): void
    {
        $modificacion = $this->crearModificacionPlazo();
        $modificacion->update([
            'texto_otrosi_redactado' => app(SolicitudContratoIAService::class)->redactarOtrosi($modificacion),
        ]);

        app(SolicitudContratoIAService::class)->generarOtrosiPDF($modificacion);

        $solicitud = $modificacion->solicitudContrato->fresh();

        $this->assertSame(1, $solicitud->veces_prorrogado);
        $this->assertSame('2026-07-01', $solicitud->fecha_inicio_periodo_actual->toDateString());
        $this->assertSame('2026-12-28', $solicitud->fecha_fin_contrato->toDateString());
        $this->assertNull($solicitud->decision_no_renovacion_en);
        $this->assertNull($solicitud->notificado_vencimiento_en);
        $this->assertFalse($solicitud->requiere_revision_manual_renovacion);
    }
}
