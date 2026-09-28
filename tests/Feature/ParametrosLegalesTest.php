<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ParametrosLegales;
use App\Models\Configuracion;
use App\Models\User;
use App\Services\TerminacionContratoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SMLMV vigente (Configuracion, clave smlmv_vigente) - dato legal nacional
 * que afecta el cálculo de indemnización de TODOS los bufetes, por eso solo
 * super_admin puede editarlo (pedido explícito del usuario).
 */
class ParametrosLegalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_super_admin_puede_acceder(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($superAdmin);
        $this->assertTrue(ParametrosLegales::canAccess());

        $bufete = User::factory()->create(['role' => 'bufete', 'active' => true]);
        $this->actingAs($bufete);
        $this->assertFalse(ParametrosLegales::canAccess());

        $cliente = User::factory()->create(['role' => 'cliente', 'active' => true]);
        $this->actingAs($cliente);
        $this->assertFalse(ParametrosLegales::canAccess());
    }

    public function test_guarda_el_smlmv_en_configuracion(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        Livewire::actingAs($superAdmin)->test(ParametrosLegales::class)
            ->fillForm(['smlmv_vigente' => 1423500])
            ->call('guardar');

        $this->assertSame(
            '1423500',
            Configuracion::where('clave', TerminacionContratoService::CLAVE_SMLMV_VIGENTE)->value('valor')
        );
    }

    public function test_precarga_el_valor_ya_guardado(): void
    {
        Configuracion::create([
            'clave' => TerminacionContratoService::CLAVE_SMLMV_VIGENTE,
            'valor' => '1300000',
            'tipo' => 'number',
            'categoria' => 'legal',
        ]);
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        Livewire::actingAs($superAdmin)->test(ParametrosLegales::class)
            ->assertFormSet(['smlmv_vigente' => 1300000]);
    }
}
