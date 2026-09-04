<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * generarDocumentoSancion() arma el HTML con una IA que a veces devuelve su
 * propio <!DOCTYPE>/<html> completo (con su propio </body>, no controlado
 * por código) y a veces no (en cuyo caso envolverEnHTMLCompleto() sí lo
 * controla, ver limpiarContenidoHTML()). Por eso la inyección del membrete
 * se probó aislada en su propio método (inyectarMembreteSancion), mismo
 * patrón ya usado en este archivo para inyectarTablaSanciones() - probar el
 * flujo completo requeriría mockear la llamada real a la IA en otra capa.
 */
class MembreteEnSancionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function invocar(string $html, Empresa $empresa): string
    {
        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'inyectarMembreteSancion');
        $metodo->setAccessible(true);

        return $metodo->invoke($service, $html, $empresa);
    }

    private function crearEmpresaConLogo(): Empresa
    {
        Storage::disk('local')->put('logos/1/logo.png', 'contenido-de-prueba-png');

        return Empresa::factory()->create(['logo_path' => 'logos/1/logo.png', 'logo_color_acento' => '#E46350']);
    }

    public function test_inyecta_el_membrete_cuando_el_html_tiene_body_cerrado(): void
    {
        $empresa = $this->crearEmpresaConLogo();
        $html = '<html><body><p>contenido</p></body></html>';

        $resultado = $this->invocar($html, $empresa);

        $this->assertStringContainsString('membrete-pie', $resultado);
        $this->assertStringContainsString('<p>contenido</p>', $resultado);
    }

    public function test_anexa_el_membrete_al_final_cuando_no_hay_body_cerrado(): void
    {
        // Simula el caso real que el plan identificó como riesgo: la IA
        // devuelve HTML sin un </body> controlado por código.
        $empresa = $this->crearEmpresaConLogo();
        $html = '<div><p>contenido sin estructura html completa</p></div>';

        $resultado = $this->invocar($html, $empresa);

        $this->assertStringContainsString('<p>contenido sin estructura html completa</p>', $resultado);
        $this->assertStringContainsString('membrete-pie', $resultado);
    }

    public function test_no_agrega_nada_si_la_empresa_no_tiene_logo(): void
    {
        $empresa = Empresa::factory()->create(['logo_path' => null]);
        $html = '<html><body><p>contenido</p></body></html>';

        $resultado = $this->invocar($html, $empresa);

        $this->assertSame($html, $resultado);
    }
}
