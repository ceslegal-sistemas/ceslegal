<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Models\Empresa;
use App\Models\User;
use App\Services\LogroDescargosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tarjeta de progreso de logros en el Dashboard - solo visible para
 * 'cliente' (no 'bufete'), pedido explícito del usuario.
 */
class DashboardLogroDescargosNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LogrosSeeder::class);
    }

    public function test_muestra_la_tarjeta_de_progreso_para_cliente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        app(LogroDescargosService::class)->registrarPlazoCumplido($empresa);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Gestor puntual — 1 de 5');
    }

    public function test_no_muestra_la_tarjeta_para_bufete(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'bufete', 'empresa_id' => $empresa->id, 'active' => true]);

        app(LogroDescargosService::class)->registrarPlazoCumplido($empresa);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('Logros de cumplimiento');
    }

    public function test_muestra_estado_completo_cuando_los_3_logros_estan_desbloqueados(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $service = app(LogroDescargosService::class);

        for ($i = 0; $i < 10; $i++) {
            $service->registrarPlazoCumplido($empresa);
        }

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('¡Todos los logros de cumplimiento desbloqueados!');
    }
}
