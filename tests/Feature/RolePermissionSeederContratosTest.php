<?php

namespace Tests\Feature;

use Database\Seeders\BufeteRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hallazgo real (2026-09-02): 'cliente' no tenía ningún permiso
 * modificacion::contractual (los botones "Sí, renovar"/"No renovar"/
 * "Solicitar un Cambio" le darían 403), y 'bufete' no tenía ningún permiso
 * solicitud::contrato (no podía abrir "Historial de Contratos" pese a
 * recibir la notificación de vencimiento). Este test fija el permiso
 * mínimo esperado para que esos flujos funcionen de verdad.
 */
class RolePermissionSeederContratosTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_puede_solicitar_y_ver_otrosies(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $cliente = Role::findByName('cliente', 'web');

        // view_any es obligatorio aunque el cliente no vea el listado plano
        // como ítem de menú (Filament exige canViewAny() para acceder a
        // CUALQUIER página del resource, no solo al listado) - la
        // ocultación real del menú vive en
        // ModificacionContractualResource::shouldRegisterNavigation().
        $this->assertTrue($cliente->hasPermissionTo('view_any_modificacion::contractual'));
        $this->assertTrue($cliente->hasPermissionTo('create_modificacion::contractual'));
        $this->assertTrue($cliente->hasPermissionTo('view_modificacion::contractual'));
        $this->assertFalse($cliente->hasPermissionTo('update_modificacion::contractual'));
        $this->assertFalse($cliente->hasPermissionTo('delete_modificacion::contractual'));
    }

    public function test_bufete_puede_gestionar_solicitudes_de_contrato(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(BufeteRoleSeeder::class);

        $bufete = Role::findByName('bufete', 'web');

        $this->assertTrue($bufete->hasPermissionTo('view_any_solicitud::contrato'));
        $this->assertTrue($bufete->hasPermissionTo('view_solicitud::contrato'));
        $this->assertTrue($bufete->hasPermissionTo('create_solicitud::contrato'));
        $this->assertTrue($bufete->hasPermissionTo('update_solicitud::contrato'));
    }
}
