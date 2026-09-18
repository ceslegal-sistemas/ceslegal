<?php

namespace App\Observers;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Garantiza que el rol de Spatie (model_has_roles) siempre coincida con la
 * columna simple `role` de users - sin importar por dónde se cree o edite
 * el usuario (registro normal, panel de administración, un comando, un
 * script de una sola vez). Hallazgo real (2026-09-18): un usuario creado a
 * mano para una demo tenía `role = 'cliente'` pero nunca se le asignó el rol
 * real de Spatie, dejándolo sin ningún permiso - invisible hasta que alguien
 * intenta usar el sistema y todo falla con "Acceso no permitido". Antes,
 * cada flujo de creación de usuario tenía que acordarse de llamar
 * `assignRole()` por su cuenta; ahora es automático a nivel de modelo.
 */
class UserObserver
{
    public function saved(User $user): void
    {
        if (!$user->role) {
            return;
        }

        if ($user->wasRecentlyCreated || $user->wasChanged('role')) {
            // firstOrCreate en vez de asumir que el rol ya existe (mismo
            // patrón que RolePermissionSeeder): un usuario puede crearse
            // antes de que el seeder haya corrido (tests, un entorno nuevo),
            // y este Observer es precisamente la garantía de que el rol de
            // Spatie exista y quede sincronizado sin importar el orden.
            $role = Role::firstOrCreate(['name' => $user->role, 'guard_name' => 'web']);
            $user->syncRoles([$role]);
        }
    }
}
