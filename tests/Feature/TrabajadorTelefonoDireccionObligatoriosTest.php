<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\TrabajadorResource\Pages\CreateTrabajador;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pedido explícito del usuario: teléfono y dirección del trabajador deben
 * ser obligatorios al crearlo en la plataforma (antes eran opcionales).
 */
class TrabajadorTelefonoDireccionObligatoriosTest extends TestCase
{
    use RefreshDatabase;

    protected function usuario(): User
    {
        Permission::findOrCreate('create_trabajador', 'web');
        Permission::findOrCreate('view_any_trabajador', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['create_trabajador', 'view_any_trabajador']);
        $this->actingAs($user);
        return $user;
    }

    public function test_no_se_puede_crear_trabajador_sin_telefono_ni_direccion(): void
    {
        $this->usuario();
        $empresa = Empresa::factory()->create(['active' => true]);

        Livewire::test(CreateTrabajador::class)
            ->set('data.empresa_id', $empresa->id)
            ->set('data.tipo_documento', 'CC')
            ->set('data.numero_documento', '1234567890')
            ->set('data.genero', 'masculino')
            ->set('data.nombres', 'Juan')
            ->set('data.apellidos', 'Pérez')
            ->set('data.email', 'juan@empresa.com')
            ->set('data.cargo_select', 'Analista')
            ->call('create')
            ->assertHasFormErrors(['telefono' => 'required', 'direccion' => 'required']);
    }

    public function test_se_puede_crear_trabajador_con_telefono_y_direccion(): void
    {
        $this->usuario();
        $empresa = Empresa::factory()->create(['active' => true]);

        Livewire::test(CreateTrabajador::class)
            ->set('data.empresa_id', $empresa->id)
            ->set('data.tipo_documento', 'CC')
            ->set('data.numero_documento', '1234567890')
            ->set('data.genero', 'masculino')
            ->set('data.nombres', 'Juan')
            ->set('data.apellidos', 'Pérez')
            ->set('data.email', 'juan@empresa.com')
            ->set('data.cargo_select', 'Analista')
            ->set('data.telefono', '3001234567')
            ->set('data.direccion', 'Calle 123 # 45-67')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('trabajadores', [
            'numero_documento' => '1234567890',
            'telefono' => '3001234567',
            'direccion' => 'Calle 123 # 45-67',
        ]);
    }
}
