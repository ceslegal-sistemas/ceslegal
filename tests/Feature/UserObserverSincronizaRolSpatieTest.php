<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hallazgo real en producción (2026-09-18): un usuario creado a mano para una
 * empresa de demo tenía `role = 'cliente'` en la columna simple de `users`,
 * pero nunca se le asignó el rol real de Spatie (model_has_roles) - quedó
 * sin ningún permiso, invisible hasta que intentó usar el sistema (no podía
 * construir su RIT, ver "Historial de Descargos", y "Auditoría de RIT" le
 * aparecía visible por error, ya que esa página se oculta comprobando
 * hasRole('cliente') vía Spatie, no la columna). UserObserver mantiene
 * ambos siempre sincronizados, sin importar por dónde se cree/edite el
 * usuario.
 */
class UserObserverSincronizaRolSpatieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'abogado', 'guard_name' => 'web']);
    }

    public function test_crear_un_usuario_le_asigna_el_rol_de_spatie_automaticamente(): void
    {
        $user = User::factory()->create(['role' => 'cliente']);

        $this->assertTrue($user->fresh()->hasRole('cliente'));
    }

    public function test_cambiar_la_columna_role_resincroniza_el_rol_de_spatie(): void
    {
        $user = User::factory()->create(['role' => 'cliente']);

        $user->update(['role' => 'abogado']);

        $user->refresh();
        $this->assertTrue($user->hasRole('abogado'));
        $this->assertFalse($user->hasRole('cliente'));
    }

    public function test_guardar_sin_cambiar_el_rol_no_duplica_la_asignacion(): void
    {
        $user = User::factory()->create(['role' => 'cliente']);

        $user->update(['name' => 'Nombre actualizado']);

        $user->refresh();
        $this->assertCount(1, $user->roles);
        $this->assertTrue($user->hasRole('cliente'));
    }
}
