<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Burbuja de Chatwoot (script SDK real proporcionado por el usuario)
 * embebida en el panel 'empresa', solo para role 'cliente' - identifica al
 * usuario ante Chatwoot con su user_id como custom_attribute, que es lo que
 * el workflow de n8n usa para consultar /internal/asistente-panel/contexto.
 */
class ChatwootWidgetRenderHookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.chatwoot.website_token' => 'token-de-prueba']);
        config(['services.chatwoot.base_url' => 'https://chatwoot.example.test']);
    }

    public function test_el_script_de_chatwoot_aparece_para_role_cliente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true, 'name' => 'Juan Pérez']);

        $response = $this->actingAs($user)->get('/empresa');

        $response->assertSuccessful();
        $response->assertSee('chatwootSDK', false);
        $response->assertSee('token-de-prueba', false);
        // Js::from() escapa las barras (/) por seguridad al incrustar en
        // <script> - el HTML real trae "https:\/\/...", no "https://...".
        $response->assertSee('chatwoot.example.test', false);
    }

    public function test_identifica_al_usuario_con_su_user_id_como_custom_attribute(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $response = $this->actingAs($user)->get('/empresa');

        $response->assertSee('setUser', false);
        $response->assertSee('user_id: ' . $user->id, false);
    }

    public function test_no_aparece_para_un_usuario_no_cliente(): void
    {
        $user = User::factory()->create(['role' => 'bufete', 'active' => true]);

        $response = $this->actingAs($user)->get('/empresa');

        $response->assertForbidden();
    }
}
