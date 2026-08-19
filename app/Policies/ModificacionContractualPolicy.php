<?php

namespace App\Policies;

use App\Models\ModificacionContractual;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModificacionContractualPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_modificacion::contractual');
    }

    public function view(User $user, ModificacionContractual $modificacionContractual): bool
    {
        return $user->can('view_modificacion::contractual');
    }

    public function create(User $user): bool
    {
        return $user->can('create_modificacion::contractual');
    }

    public function update(User $user, ModificacionContractual $modificacionContractual): bool
    {
        return $user->can('update_modificacion::contractual');
    }

    public function delete(User $user, ModificacionContractual $modificacionContractual): bool
    {
        return $user->can('delete_modificacion::contractual');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_modificacion::contractual');
    }

    public function restore(User $user, ModificacionContractual $modificacionContractual): bool
    {
        return $user->can('restore_modificacion::contractual');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_modificacion::contractual');
    }

    public function forceDelete(User $user, ModificacionContractual $modificacionContractual): bool
    {
        return $user->can('force_delete_modificacion::contractual');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_modificacion::contractual');
    }
}
