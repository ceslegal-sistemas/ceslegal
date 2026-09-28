<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MisLogros;
use App\Models\Empresa;
use App\Models\User;
use App\Services\LogroDescargosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Página "Mis Logros" (2026-09-25): vitrina permanente de TODOS los logros
 * (obtenidos y en progreso), a diferencia de la tarjeta del Dashboard que
 * solo muestra el siguiente pendiente.
 */
class MisLogrosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\LogrosSeeder::class);
    }

    public function test_solo_se_registra_en_el_menu_para_cliente(): void
    {
        $cliente = User::factory()->create(['role' => 'cliente', 'active' => true]);
        $this->actingAs($cliente);
        $this->assertTrue(MisLogros::shouldRegisterNavigation());

        $bufete = User::factory()->create(['role' => 'bufete', 'active' => true]);
        $this->actingAs($bufete);
        $this->assertFalse(MisLogros::shouldRegisterNavigation());
    }

    public function test_muestra_los_logros_obtenidos_y_en_progreso_del_cliente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        app(LogroDescargosService::class)->registrarPlazoCumplido($empresa);

        Livewire::actingAs($user)->test(MisLogros::class)
            ->assertSee('Primer plazo cumplido')
            ->assertSee('Obtenido el')
            ->assertSee('Gestor puntual')
            ->assertSee('1 de 5 procesos cerrados a tiempo');
    }

    public function test_el_badge_de_navegacion_cuenta_solo_los_logros_completados(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($user);

        $this->assertNull(MisLogros::getNavigationBadge());

        app(LogroDescargosService::class)->registrarPlazoCumplido($empresa);

        $this->assertSame('1', MisLogros::getNavigationBadge());
    }
}
