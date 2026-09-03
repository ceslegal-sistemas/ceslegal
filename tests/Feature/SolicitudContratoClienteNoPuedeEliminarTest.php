<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hallazgo real (2026-09-02): un contrato aprobado (o ya no vigente) se
 * cierra con una Terminación de Contrato formal (justa causa/sin justa
 * causa, indemnización si aplica - pendiente de construir aparte), no
 * borrándolo del historial. 'cliente' nunca debe poder eliminar un
 * contrato, sin importar el permiso que tenga asignado.
 */
class SolicitudContratoClienteNoPuedeEliminarTest extends TestCase
{
    use RefreshDatabase;

    private function crearSolicitud(): SolicitudContrato
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        return SolicitudContrato::create([
            'empresa_id' => $empresa->id,
            'estado' => 'aprobado',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2026-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
        ]);
    }

    public function test_cliente_no_puede_eliminar_ni_con_el_permiso_asignado(): void
    {
        Permission::findOrCreate('delete_solicitud::contrato', 'web');
        Role::findOrCreate('cliente', 'web');
        $user = User::factory()->create(['role' => 'cliente', 'active' => true]);
        $user->assignRole('cliente');
        $user->givePermissionTo('delete_solicitud::contrato');

        $solicitud = $this->crearSolicitud();

        $this->assertFalse($user->can('delete', $solicitud));
    }

    public function test_bufete_si_puede_eliminar_con_el_permiso_asignado(): void
    {
        Permission::findOrCreate('delete_solicitud::contrato', 'web');
        Role::findOrCreate('bufete', 'web');
        $user = User::factory()->create(['role' => 'bufete', 'active' => true]);
        $user->assignRole('bufete');
        $user->givePermissionTo('delete_solicitud::contrato');

        $solicitud = $this->crearSolicitud();

        $this->assertTrue($user->can('delete', $solicitud));
    }
}
