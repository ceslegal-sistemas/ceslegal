<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudCambioEmpresaResource\Pages\ListSolicitudesCambioEmpresa;
use App\Models\Empresa;
use App\Models\SolicitudCambioEmpresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-25): las empresas registradas antes
 * de que existiera el campo de cédula del representante legal no tienen
 * forma de agregarla (bloqueado en "Mi Empresa") - se corrige por el mismo
 * mecanismo ya existente de "Solicitar cambio" + aprobación del admin.
 */
class SolicitudCambioEmpresaCedulaTest extends TestCase
{
    use RefreshDatabase;

    public function test_al_aprobar_se_puede_agregar_la_cedula_del_representante_legal(): void
    {
        $empresa = Empresa::factory()->create(['representante_legal_cedula' => null]);
        $cliente = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        Permission::findOrCreate('view_any_solicitud::cambio::empresa', 'web');
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $admin->givePermissionTo('view_any_solicitud::cambio::empresa');

        $solicitud = SolicitudCambioEmpresa::create([
            'empresa_id' => $empresa->id,
            'user_id' => $cliente->id,
            'mensaje' => 'Falta mi cédula de representante legal: 1234567890',
            'estado' => 'pendiente',
        ]);

        Livewire::actingAs($admin)
            ->test(ListSolicitudesCambioEmpresa::class)
            ->callTableAction('aprobar', $solicitud, data: [
                'razon_social' => $empresa->razon_social,
                'tipo_societario' => $empresa->tipo_societario,
                'nit' => $empresa->nit,
                'representante_legal' => $empresa->representante_legal,
                'representante_legal_cedula' => '1234567890',
            ]);

        $this->assertSame('1234567890', $empresa->fresh()->representante_legal_cedula);
        $this->assertSame('aprobada', $solicitud->fresh()->estado);
    }
}
