<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TemaNormativo;
use Illuminate\Auth\Access\HandlesAuthorization;

class TemaNormativoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_tema::normativo');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TemaNormativo $temaNormativo): bool
    {
        return $user->can('view_tema::normativo');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_tema::normativo');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TemaNormativo $temaNormativo): bool
    {
        return $user->can('update_tema::normativo');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TemaNormativo $temaNormativo): bool
    {
        return $user->can('delete_tema::normativo');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_tema::normativo');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, TemaNormativo $temaNormativo): bool
    {
        return $user->can('force_delete_tema::normativo');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_tema::normativo');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, TemaNormativo $temaNormativo): bool
    {
        return $user->can('restore_tema::normativo');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_tema::normativo');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, TemaNormativo $temaNormativo): bool
    {
        return $user->can('replicate_tema::normativo');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_tema::normativo');
    }
}
