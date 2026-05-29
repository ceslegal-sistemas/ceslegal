<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CorreoEnviado;
use Illuminate\Auth\Access\HandlesAuthorization;

class CorreoEnviadoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_correo::enviado');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CorreoEnviado $correoEnviado): bool
    {
        if (! $user->can('view_correo::enviado')) {
            return false;
        }

        // Los clientes solo pueden ver sus propios correos
        if ($user->hasRole('cliente')) {
            return $correoEnviado->enviado_por === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_correo::enviado');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CorreoEnviado $correoEnviado): bool
    {
        return $user->can('update_correo::enviado');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CorreoEnviado $correoEnviado): bool
    {
        return $user->can('delete_correo::enviado');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_correo::enviado');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, CorreoEnviado $correoEnviado): bool
    {
        return $user->can('force_delete_correo::enviado');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_correo::enviado');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, CorreoEnviado $correoEnviado): bool
    {
        return $user->can('restore_correo::enviado');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_correo::enviado');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, CorreoEnviado $correoEnviado): bool
    {
        return $user->can('replicate_correo::enviado');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_correo::enviado');
    }
}
