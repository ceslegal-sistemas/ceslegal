<?php

namespace Tests\Feature;

use App\Models\ArticuloLegal;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Endpoint interno que n8n consulta (Tool del AI Agent) SOLO cuando el
 * cliente pregunta por un artículo/norma concreta - separado de
 * AsistentePanelContextoControllerTest porque este dispara una búsqueda
 * semántica real (Gemini fakeado aquí).
 */
class AsistentePanelConsultaLegalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asistente_panel.secret' => 'secreto-de-prueba']);
    }

    private function fakearEmbeddingDeConsulta(array $valores): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => $valores],
            ], 200),
        ]);
    }

    public function test_devuelve_el_articulo_encontrado_con_el_secreto_correcto(): void
    {
        $this->fakearEmbeddingDeConsulta([1.0, 0.0, 0.0]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        ArticuloLegal::create([
            'codigo' => 'CST-60-2',
            'titulo' => 'Prohibición: presentarse en estado de embriaguez',
            'descripcion' => 'x',
            'texto_completo' => 'Está prohibido presentarse al trabajo en estado de embriaguez.',
            'activo' => true,
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $response = $this->withHeaders(['X-Internal-Secret' => 'secreto-de-prueba'])
            ->postJson('/internal/asistente-panel/consultar-legal', [
                'user_id' => $user->id,
                'pregunta' => '¿me pueden despedir por llegar borracho?',
            ]);

        $response->assertSuccessful();
        $response->assertJson(['encontrado' => true]);
        $response->assertJsonFragment(['encontrado' => true]);
        $this->assertStringContainsString('CST-60-2', $response->json('contenido'));
    }

    public function test_devuelve_no_encontrado_si_no_hay_articulos_relevantes(): void
    {
        $this->fakearEmbeddingDeConsulta([1.0, 0.0, 0.0]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $response = $this->withHeaders(['X-Internal-Secret' => 'secreto-de-prueba'])
            ->postJson('/internal/asistente-panel/consultar-legal', [
                'user_id' => $user->id,
                'pregunta' => 'pregunta sin ningún artículo indexado',
            ]);

        $response->assertSuccessful();
        $response->assertExactJson(['encontrado' => false, 'contenido' => '']);
    }

    public function test_rechaza_sin_el_secreto_correcto(): void
    {
        $user = User::factory()->create(['role' => 'cliente', 'active' => true]);

        $response = $this->withHeaders(['X-Internal-Secret' => 'secreto-incorrecto'])
            ->postJson('/internal/asistente-panel/consultar-legal', ['user_id' => $user->id, 'pregunta' => 'x']);

        $response->assertForbidden();
    }

    public function test_rechaza_sin_ningun_secreto(): void
    {
        $user = User::factory()->create(['role' => 'cliente', 'active' => true]);

        $response = $this->postJson('/internal/asistente-panel/consultar-legal', ['user_id' => $user->id, 'pregunta' => 'x']);

        $response->assertForbidden();
    }

    public function test_devuelve_404_si_el_usuario_no_existe(): void
    {
        $response = $this->withHeaders(['X-Internal-Secret' => 'secreto-de-prueba'])
            ->postJson('/internal/asistente-panel/consultar-legal', ['user_id' => 999999, 'pregunta' => 'x']);

        $response->assertNotFound();
    }

    public function test_rechaza_un_usuario_que_no_es_cliente(): void
    {
        $user = User::factory()->create(['role' => 'bufete', 'active' => true]);

        $response = $this->withHeaders(['X-Internal-Secret' => 'secreto-de-prueba'])
            ->postJson('/internal/asistente-panel/consultar-legal', ['user_id' => $user->id, 'pregunta' => 'x']);

        $response->assertForbidden();
    }

    public function test_rechaza_pregunta_vacia(): void
    {
        $user = User::factory()->create(['role' => 'cliente', 'active' => true]);

        $response = $this->withHeaders(['X-Internal-Secret' => 'secreto-de-prueba'])
            ->postJson('/internal/asistente-panel/consultar-legal', ['user_id' => $user->id, 'pregunta' => '   ']);

        $response->assertStatus(422);
    }

    public function test_no_devuelve_articulos_de_otra_empresa(): void
    {
        $this->fakearEmbeddingDeConsulta([1.0, 0.0, 0.0]);

        $empresaConsultante = Empresa::factory()->create(['active' => true]);
        $otraEmpresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresaConsultante->id, 'active' => true]);

        ArticuloLegal::create([
            'codigo' => 'RIT-ART-9',
            'titulo' => 'Artículo del RIT de otra empresa',
            'descripcion' => 'x',
            'texto_completo' => 'No debería verse desde otra empresa.',
            'activo' => true,
            'empresa_id' => $otraEmpresa->id,
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $response = $this->withHeaders(['X-Internal-Secret' => 'secreto-de-prueba'])
            ->postJson('/internal/asistente-panel/consultar-legal', [
                'user_id' => $user->id,
                'pregunta' => 'pregunta cualquiera',
            ]);

        $response->assertSuccessful();
        $response->assertExactJson(['encontrado' => false, 'contenido' => '']);
    }
}
