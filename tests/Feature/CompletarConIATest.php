<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\CreateSolicitudContrato;
use App\Models\Empresa;
use App\Models\User;
use App\Services\SolicitudContratoIAService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CompletarConIATest extends TestCase
{
    use RefreshDatabase;

    protected function usuario(): User
    {
        Permission::findOrCreate('create_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['create_solicitud::contrato', 'view_any_solicitud::contrato']);
        $this->actingAs($user);
        return $user;
    }

    /**
     * Bug real reportado en producción: cuando el servicio de IA falla (en
     * este caso, RESOURCE_EXHAUSTED de Gemini por créditos agotados), la
     * excepción no estaba atrapada y reventaba con la pantalla de error
     * cruda de Laravel en vez de un aviso legible. Se verifica que ahora la
     * página sigue respondiendo con éxito (sin 500) tras el fallo.
     */
    public function test_completar_con_ia_no_revienta_si_el_servicio_de_ia_falla(): void
    {
        $this->usuario();
        $empresa = Empresa::factory()->create(['active' => true]);

        $this->mock(SolicitudContratoIAService::class, function ($mock) {
            $mock->shouldReceive('completarDetallesCargo')
                ->once()
                ->andThrow(new \RuntimeException('RESOURCE_EXHAUSTED'));
        });

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.empresa_id', $empresa->id)
            ->set('data.cargo_contrato', 'Analista')
            ->call('completarDetallesConIA')
            ->assertSuccessful()
            ->assertSet('data.responsabilidades', null);
    }
}
