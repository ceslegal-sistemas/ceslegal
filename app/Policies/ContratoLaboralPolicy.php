<?php

namespace App\Policies;

use App\Models\ContratoLaboral;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContratoLaboralPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_contrato::laboral');
    }

    public function view(User $user, ContratoLaboral $contrato): bool
    {
        return $user->can('view_contrato::laboral');
    }

    public function create(User $user): bool
    {
        return $user->can('create_contrato::laboral');
    }

    public function update(User $user, ContratoLaboral $contrato): bool
    {
        return $user->can('update_contrato::laboral');
    }
}
