<?php

namespace Tests\Feature;

use App\Models\Bufete;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El selector de empresa del topbar (PanelBrandingServiceProvider) solo se
 * renderizaba para bufete - bug real reportado por el usuario (2026-09-29):
 * super_admin no tenia ninguna forma de elegir empresa en el panel.
 */
class SelectorEmpresaTopbarHookTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_selector_aparece_en_el_topbar_para_super_admin(): void
    {
        Empresa::factory()->create(['active' => true]);
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($superAdmin);

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('wire:model.live.debounce.300ms="busqueda"', false);
    }

    public function test_el_selector_no_aparece_para_un_cliente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $cliente = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($cliente);

        $response = $this->get('/empresa');

        $response->assertDontSee('wire:model.live.debounce.300ms="busqueda"', false);
    }

    public function test_el_selector_aparece_para_abogado_de_bufete(): void
    {
        $bufete = Bufete::factory()->create();
        Empresa::factory()->create(['bufete_id' => $bufete->id, 'active' => true]);
        $abogado = User::factory()->create(['role' => 'bufete', 'bufete_id' => $bufete->id, 'active' => true]);
        $this->actingAs($abogado);

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('wire:model.live.debounce.300ms="busqueda"', false);
    }
}
