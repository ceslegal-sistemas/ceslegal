<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsistentePanelRenderHookTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_widget_aparece_para_role_cliente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $response = $this->actingAs($user)->get('/empresa');

        $response->assertSuccessful();
        $response->assertSeeLivewire('asistente-panel-chat');
    }

    public function test_un_usuario_no_cliente_ni_siquiera_llega_a_ver_empresa(): void
    {
        // No se puede probar "el widget no aparece para bufete" visitando
        // /empresa con assertSuccessful(): Filament\Http\Middleware\Authenticate
        // ya corta con 403 antes de llegar al render hook (canAccessPanel()
        // devuelve false para role !== 'cliente' en este panel).
        //
        // BUG PREEXISTENTE encontrado al escribir este test (2026-09-10, no
        // corregido aquí - fuera de alcance de esta tarea): el comentario de
        // RedirigirNoClienteAlPanelAdmin.php dice que corre "ANTES del grupo
        // de authMiddleware", pero `php artisan route:list -v` muestra que en
        // realidad corre AL FINAL de la cadena, después de
        // Filament\Http\Middleware\Authenticate - por eso el redirect nunca
        // se ejecuta (Filament ya respondió 403 antes). El guard
        // `role === 'cliente'` del render hook es una segunda capa que nunca
        // se ejercita por HTTP real para un no-cliente de todas formas.
        $user = User::factory()->create(['role' => 'bufete', 'active' => true]);

        $response = $this->actingAs($user)->get('/empresa');

        $response->assertForbidden();
    }
}
