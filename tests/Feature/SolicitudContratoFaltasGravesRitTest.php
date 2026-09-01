<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\SolicitudContrato;
use App\Models\User;
use App\Services\ReglamentoInternoService;
use App\Services\SolicitudContratoIAService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudContratoFaltasGravesRitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Timeline registra con Auth::id() ?? 1 ("usuario del sistema") -
        // estos tests no hacen actingAs.
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function crearSolicitud(int $empresaId): SolicitudContrato
    {
        return SolicitudContrato::create([
            'empresa_id' => $empresaId,
            'estado' => 'borrador',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123',
            'trabajador_email' => 'juan@test.com',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
        ]);
    }

    public function test_sin_rit_el_origen_es_sin_rit(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $solicitud = $this->crearSolicitud($empresa->id);

        $resultado = app(SolicitudContratoIAService::class)->obtenerFaltasGravesRit($solicitud);

        $this->assertSame('sin_rit', $resultado['origen']);
        $this->assertSame([], $resultado['grave']);
        $this->assertSame([], $resultado['gravisima']);
    }

    public function test_rit_con_conductas_ya_calculadas_las_usa_tal_cual_sin_llamar_ia(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'RIT de prueba',
            'activo' => true,
            'conductas_sancionables' => [
                'leve' => [],
                'grave' => [['conducta' => 'Llegar tarde de forma reiterada', 'medida' => 'x', 'tipo' => 'suspension', 'dias_suspension' => 3, 'base_legal' => 'Art. 60 CST']],
                'gravisima' => [['conducta' => 'Agredir físicamente a un compañero', 'medida' => 'x', 'tipo' => 'terminacion', 'dias_suspension' => null, 'base_legal' => 'Art. 62 CST']],
            ],
        ]);
        $solicitud = $this->crearSolicitud($empresa->id);

        // No se mockea ReglamentoInternoService - si el código intentara
        // llamar a generarConductasSancionables() (que golpea Gemini vía
        // Http real), el test fallaría por un error de red/IA en vez de
        // pasar; que pase confirma que NO se llamó.
        $resultado = app(SolicitudContratoIAService::class)->obtenerFaltasGravesRit($solicitud);

        $this->assertSame('rit', $resultado['origen']);
        $this->assertSame(['Llegar tarde de forma reiterada'], $resultado['grave']);
        $this->assertSame(['Agredir físicamente a un compañero'], $resultado['gravisima']);
    }

    public function test_rit_sin_conductas_calculadas_dispara_la_extraccion_y_la_persiste(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'RIT de prueba',
            'activo' => true,
            'conductas_sancionables' => null,
        ]);
        $solicitud = $this->crearSolicitud($empresa->id);

        $this->mock(ReglamentoInternoService::class, function ($mock) {
            $mock->shouldReceive('generarConductasSancionables')
                ->once()
                ->andReturn([
                    'leve' => [],
                    'grave' => [['conducta' => 'Conducta extraída del RIT', 'medida' => 'x', 'tipo' => 'suspension', 'dias_suspension' => 5, 'base_legal' => 'Art. 60 CST']],
                    'gravisima' => [],
                ]);
        });

        $resultado = app(SolicitudContratoIAService::class)->obtenerFaltasGravesRit($solicitud);

        $this->assertSame('rit', $resultado['origen']);
        $this->assertSame(['Conducta extraída del RIT'], $resultado['grave']);
        $this->assertDatabaseHas('reglamentos_internos', ['id' => $rit->id]);
        $this->assertNotEmpty($rit->fresh()->conductas_sancionables['grave']);
    }

    public function test_si_la_extraccion_falla_cae_al_listado_general(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'RIT de prueba',
            'activo' => true,
            'conductas_sancionables' => null,
        ]);
        $solicitud = $this->crearSolicitud($empresa->id);

        $this->mock(ReglamentoInternoService::class, function ($mock) {
            $mock->shouldReceive('generarConductasSancionables')
                ->once()
                ->andThrow(new \RuntimeException('Gemini caído'));
        });

        $resultado = app(SolicitudContratoIAService::class)->obtenerFaltasGravesRit($solicitud);

        $this->assertSame('sin_conductas', $resultado['origen']);
    }

    public function test_rit_con_conductas_calculadas_pero_solo_leves_no_reintenta_extraccion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'RIT de prueba',
            'activo' => true,
            'conductas_sancionables' => [
                'leve' => [['conducta' => 'Llegar tarde una vez', 'medida' => 'x', 'tipo' => 'llamado_atencion', 'dias_suspension' => null, 'base_legal' => 'Art. 58 CST']],
                'grave' => [],
                'gravisima' => [],
            ],
        ]);
        $solicitud = $this->crearSolicitud($empresa->id);

        // Si el código reintentara la extracción por no tener grave/gravísima,
        // este mock no configurado haría fallar el test.
        $this->mock(ReglamentoInternoService::class, function ($mock) {
            $mock->shouldNotReceive('generarConductasSancionables');
        });

        $resultado = app(SolicitudContratoIAService::class)->obtenerFaltasGravesRit($solicitud);

        $this->assertSame('sin_conductas', $resultado['origen']);
    }

    public function test_generar_contrato_pdf_devuelve_el_origen_y_lo_registra_en_el_timeline(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $solicitud = $this->crearSolicitud($empresa->id);

        $resultado = app(SolicitudContratoIAService::class)->generarContratoPDF($solicitud, borrador: true);

        $this->assertSame('sin_rit', $resultado['faltas_graves_origen']);
        $this->assertArrayHasKey('ruta', $resultado);

        $this->assertDatabaseHas('timeline', [
            'proceso_tipo' => 'contrato',
            'proceso_id' => $solicitud->id,
            'accion' => 'Documento generado',
        ]);
    }
}
