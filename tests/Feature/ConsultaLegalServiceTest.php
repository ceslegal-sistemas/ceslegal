<?php

namespace Tests\Feature;

use App\Models\ArticuloLegal;
use App\Models\Empresa;
use App\Services\ConsultaLegalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Extraído de EvaluacionHechosService::buscarNormasRelevantes() para
 * reutilizarse también desde el Asistente Panel (ver
 * AsistentePanelConsultaLegalControllerTest) - estos tests cubren la lógica
 * de búsqueda en sí, independiente de quién la llame.
 */
class ConsultaLegalServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakearEmbeddingDeConsulta(array $valores): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => $valores],
            ], 200),
        ]);
    }

    public function test_devuelve_el_articulo_cuya_similitud_supera_el_umbral(): void
    {
        $this->fakearEmbeddingDeConsulta([1.0, 0.0, 0.0]);

        ArticuloLegal::create([
            'codigo' => 'CST-60-2',
            'titulo' => 'Prohibición: presentarse en estado de embriaguez',
            'descripcion' => 'x',
            'texto_completo' => 'Está prohibido al trabajador presentarse al trabajo en estado de embriaguez.',
            'fuente' => 'Código Sustantivo del Trabajo',
            'activo' => true,
            'embedding' => [1.0, 0.0, 0.0], // idéntico al embedding de la consulta -> score 1.0
        ]);

        $resultado = app(ConsultaLegalService::class)->buscarContenidoRelevante('¿me pueden despedir por llegar borracho?');

        $this->assertStringContainsString('CST-60-2', $resultado);
        $this->assertStringContainsString('estado de embriaguez', $resultado);
    }

    public function test_devuelve_vacio_si_ningun_articulo_supera_el_umbral(): void
    {
        $this->fakearEmbeddingDeConsulta([1.0, 0.0, 0.0]);

        ArticuloLegal::create([
            'codigo' => 'CST-1',
            'titulo' => 'Artículo no relacionado',
            'descripcion' => 'x',
            'texto_completo' => 'x',
            'activo' => true,
            'embedding' => [0.0, 1.0, 0.0], // ortogonal a la consulta -> score 0.0
        ]);

        $resultado = app(ConsultaLegalService::class)->buscarContenidoRelevante('pregunta sin relación');

        $this->assertSame('', $resultado);
    }

    public function test_solo_incluye_articulos_universales_y_los_propios_de_la_empresa(): void
    {
        $this->fakearEmbeddingDeConsulta([1.0, 0.0, 0.0]);

        $empresaConsultante = Empresa::factory()->create();
        $otraEmpresa = Empresa::factory()->create();

        ArticuloLegal::create([
            'codigo' => 'CST-58',
            'titulo' => 'Universal (CST)',
            'descripcion' => 'x',
            'texto_completo' => 'Texto universal del CST.',
            'activo' => true,
            'empresa_id' => null,
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        ArticuloLegal::create([
            'codigo' => 'RIT-ART-9',
            'titulo' => 'Artículo del RIT de otra empresa',
            'descripcion' => 'x',
            'texto_completo' => 'No debería aparecer para la empresa consultante.',
            'activo' => true,
            'empresa_id' => $otraEmpresa->id,
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $resultado = app(ConsultaLegalService::class)->buscarContenidoRelevante('pregunta', $empresaConsultante->id);

        $this->assertStringContainsString('CST-58', $resultado);
        $this->assertStringNotContainsString('RIT-ART-9', $resultado);
    }

    public function test_no_lanza_excepcion_si_gemini_falla(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Error', 500),
        ]);

        ArticuloLegal::create([
            'codigo' => 'CST-1',
            'titulo' => 'x',
            'descripcion' => 'x',
            'activo' => true,
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $resultado = app(ConsultaLegalService::class)->buscarContenidoRelevante('pregunta cualquiera');

        $this->assertSame('', $resultado);
    }
}
