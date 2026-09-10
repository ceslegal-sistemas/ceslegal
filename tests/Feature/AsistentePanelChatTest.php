<?php

namespace Tests\Feature;

use App\Livewire\AsistentePanelChat;
use App\Models\Empresa;
use App\Models\User;
use App\Services\AsistentePanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AsistentePanelChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_enviar_agrega_el_mensaje_del_usuario_y_la_respuesta(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $this->mock(AsistentePanelService::class, function ($mock) {
            $mock->shouldReceive('responder')->once()->andReturn('Respuesta de prueba.');
        });

        Livewire::actingAs($user)->test(AsistentePanelChat::class)
            ->set('mensajeActual', '¿Cómo está mi RIT?')
            ->call('enviar')
            ->assertSet('mensajeActual', '')
            ->assertSee('¿Cómo está mi RIT?')
            ->assertSee('Respuesta de prueba.');
    }

    public function test_enviar_no_hace_nada_si_el_mensaje_esta_vacio(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $this->mock(AsistentePanelService::class, function ($mock) {
            $mock->shouldReceive('responder')->never();
        });

        Livewire::actingAs($user)->test(AsistentePanelChat::class)
            ->set('mensajeActual', '   ')
            ->call('enviar');
    }

    public function test_el_html_renderizado_tiene_un_unico_elemento_raiz(): void
    {
        // Componente Livewire real - esta sesion ya tuvo una rotura critica
        // en produccion por romper esta regla en otro componente
        // (MethodNotFoundException por un <style> antes del div raiz).
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $html = Livewire::actingAs($user)->test(AsistentePanelChat::class)->html();

        preg_match('/<\s*([a-z0-9]+)[^>]*>/i', ltrim($html), $m);
        $this->assertSame('div', strtolower($m[1] ?? ''));
    }
}
