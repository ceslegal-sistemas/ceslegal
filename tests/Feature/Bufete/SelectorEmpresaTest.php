<?php

namespace Tests\Feature\Bufete;

use App\Livewire\SelectorEmpresa;
use App\Models\Bufete;
use App\Models\Empresa;
use App\Models\User;
use App\Support\EmpresaActiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SelectorEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_selector_solo_acepta_empresas_del_bufete(): void
    {
        $bufete = Bufete::factory()->create();
        $e = Empresa::factory()->create(['bufete_id' => $bufete->id]);
        $ajena = Empresa::factory()->create();
        $abogado = User::factory()->create(['role' => 'bufete', 'bufete_id' => $bufete->id]);
        $this->actingAs($abogado);

        Livewire::test(SelectorEmpresa::class)->call('seleccionar', $e->id);
        $this->assertSame($e->id, EmpresaActiva::id());

        // una empresa ajena al bufete no cambia la selección
        Livewire::test(SelectorEmpresa::class)->call('seleccionar', $ajena->id);
        $this->assertSame($e->id, EmpresaActiva::id());

        Livewire::test(SelectorEmpresa::class)->call('todas');
        $this->assertNull(EmpresaActiva::id());
    }

    /**
     * Bug real reportado por el usuario (2026-09-29): super_admin no tenia
     * ninguna forma de elegir empresa en "Mi Reglamento Interno" - el
     * selector del topbar solo se mostraba para bufete, y super_admin
     * siempre caia a Empresa::first() sin importar cual empresa necesitara
     * ver de verdad.
     */
    public function test_super_admin_ve_y_puede_elegir_entre_todas_las_empresas(): void
    {
        $empresaA = Empresa::factory()->create(['razon_social' => 'ALFA', 'active' => true]);
        $empresaB = Empresa::factory()->create(['razon_social' => 'BETA', 'active' => true]);
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($superAdmin);

        Livewire::test(SelectorEmpresa::class)
            ->assertSee('ALFA')
            ->assertSee('BETA')
            ->call('seleccionar', $empresaB->id);

        $this->assertSame($empresaB->id, EmpresaActiva::id());
    }

    public function test_super_admin_puede_filtrar_empresas_por_busqueda(): void
    {
        Empresa::factory()->create(['razon_social' => 'ALFA', 'active' => true]);
        Empresa::factory()->create(['razon_social' => 'BETA', 'active' => true]);
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($superAdmin);

        Livewire::test(SelectorEmpresa::class)
            ->set('busqueda', 'ALFA')
            ->assertSee('ALFA')
            ->assertDontSee('BETA');
    }

    public function test_un_cliente_no_ve_ninguna_empresa_en_el_selector(): void
    {
        $empresa = Empresa::factory()->create(['razon_social' => 'ALFA', 'active' => true]);
        $cliente = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($cliente);

        Livewire::test(SelectorEmpresa::class)
            ->assertDontSee('ALFA');
    }
}
