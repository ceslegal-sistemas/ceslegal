<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoint interno que n8n consulta (tool HTTP del AI Agent) para obtener
 * el contexto de la empresa del usuario que está chateando vía la burbuja
 * de Chatwoot embebida en el panel - protegido por secreto compartido, no
 * por sesión (es una llamada servidor-a-servidor, no del navegador).
 */
class AsistentePanelContextoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asistente_panel.secret' => 'secreto-de-prueba']);
    }

    public function test_devuelve_el_contexto_del_usuario_con_el_secreto_correcto(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => 10]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $response = $this->withHeaders(['X-Internal-Secret' => 'secreto-de-prueba'])
            ->getJson('/internal/asistente-panel/contexto?user_id=' . $user->id);

        $response->assertSuccessful();
        $response->assertJsonPath('numero_empleados', 10);
        $response->assertJsonStructure(['empresa_nombre', 'rit', 'procesos_disciplinarios_abiertos', 'numero_empleados', 'contratos_por_vencer']);
    }

    public function test_rechaza_sin_el_secreto_correcto(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $response = $this->withHeaders(['X-Internal-Secret' => 'secreto-incorrecto'])
            ->getJson('/internal/asistente-panel/contexto?user_id=' . $user->id);

        $response->assertForbidden();
    }

    public function test_rechaza_sin_ningun_secreto(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $response = $this->getJson('/internal/asistente-panel/contexto?user_id=' . $user->id);

        $response->assertForbidden();
    }

    public function test_devuelve_404_si_el_usuario_no_existe(): void
    {
        $response = $this->withHeaders(['X-Internal-Secret' => 'secreto-de-prueba'])
            ->getJson('/internal/asistente-panel/contexto?user_id=999999');

        $response->assertNotFound();
    }

    public function test_rechaza_un_usuario_que_no_es_cliente(): void
    {
        $user = User::factory()->create(['role' => 'bufete', 'active' => true]);

        $response = $this->withHeaders(['X-Internal-Secret' => 'secreto-de-prueba'])
            ->getJson('/internal/asistente-panel/contexto?user_id=' . $user->id);

        $response->assertForbidden();
    }
}
