<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InformeJuridico;
use Illuminate\Auth\Access\HandlesAuthorization;

class InformeJuridicoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_informe::juridico');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InformeJuridico $informeJuridico): bool
    {
        return $user->can('view_informe::juridico');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_informe::juridico');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InformeJuridico $informeJuridico): bool
    {
        return $user->can('update_informe::juridico');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InformeJuridico $informeJuridico): bool
    {
        return $user->can('delete_informe::juridico');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_informe::juridico');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, InformeJuridico $informeJuridico): bool
    {
        return $user->can('force_delete_informe::juridico');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_informe::juridico');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, InformeJuridico $informeJuridico): bool
    {
        return $user->can('restore_informe::juridico');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_informe::juridico');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, InformeJuridico $informeJuridico): bool
    {
        return $user->can('replicate_informe::juridico');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_informe::juridico');
    }
}
