<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ReglamentoInterno;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReglamentoInternoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_reglamento::interno');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReglamentoInterno $reglamentoInterno): bool
    {
        if (! $user->can('view_reglamento::interno')) {
            return false;
        }

        if ($user->hasRole('cliente')) {
            return $reglamentoInterno->empresa_id === $user->empresa_id;
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_reglamento::interno');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReglamentoInterno $reglamentoInterno): bool
    {
        return $user->can('update_reglamento::interno');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReglamentoInterno $reglamentoInterno): bool
    {
        return $user->can('delete_reglamento::interno');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_reglamento::interno');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ReglamentoInterno $reglamentoInterno): bool
    {
        return $user->can('force_delete_reglamento::interno');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_reglamento::interno');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ReglamentoInterno $reglamentoInterno): bool
    {
        return $user->can('restore_reglamento::interno');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_reglamento::interno');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ReglamentoInterno $reglamentoInterno): bool
    {
        return $user->can('replicate_reglamento::interno');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_reglamento::interno');
    }
}
