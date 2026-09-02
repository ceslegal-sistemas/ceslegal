<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use App\Services\SolicitudContratoIAService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Preaviso de no renovación: plantilla literal (texto real provisto por el
 * usuario), sin IA. Generarlo marca formalmente la decisión de no renovar,
 * para que la alerta de vencimiento deje de mostrarse (ver
 * PlazoContratoService::sinDecisionTomada()).
 */
class PreavisoNoRenovacionTest extends TestCase
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

    public function test_genera_el_preaviso_y_marca_la_decision_de_no_renovar(): void
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
            'fecha_fin_contrato' => '2026-10-02',
        ]);

        $ruta = app(SolicitudContratoIAService::class)->generarPreavisoPDF($solicitud);

        $this->assertNotNull($ruta);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($ruta));

        $solicitud->refresh();
        $this->assertNotNull($solicitud->decision_no_renovacion_en);
        $this->assertSame($ruta, $solicitud->ruta_preaviso);
    }

    public function test_renderiza_el_texto_literal_con_los_datos_reales(): void
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
            'fecha_fin_contrato' => '2026-10-02',
        ]);

        $html = view('pdfs.contratos.preaviso', [
            'municipioEmpresa' => 'Bogotá D.C.',
            'departamentoEmpresa' => 'Cundinamarca',
            'fechaCarta' => '2 de septiembre de 2026',
            'nombreTrabajador' => 'Juan Pérez',
            'tipoDocumentoLabel' => 'cédula de ciudadanía',
            'numeroDocumento' => '123456',
            'fechaContratoOriginalTexto' => '1 de enero de 2026',
            'fechaFinContratoTexto' => '2 de octubre de 2026',
            'nombreEmpresa' => 'EMPRESA DE PRUEBA S.A.S.',
            'nit' => '900123456-7',
            'representanteLegal' => 'Carlos Ruiz',
        ])->render();

        $this->assertStringContainsString('No Prórroga', $html);
        // El HTML fuente parte esta frase en 2 líneas (whitespace que el
        // navegador/PDF colapsa al renderizar) - se verifica en 2 partes.
        $this->assertStringContainsString('artículo 46 del Código Sustantivo de Trabajo modificado por el', $html);
        $this->assertStringContainsString('artículo 6 de la ley 2466 de 2025', $html);
        $this->assertStringContainsString('Treinta (30) días', $html);
        $this->assertStringContainsString('2 de octubre de 2026', $html);
        $this->assertStringContainsString('EMPRESA DE PRUEBA S.A.S.', $html);
    }
}
