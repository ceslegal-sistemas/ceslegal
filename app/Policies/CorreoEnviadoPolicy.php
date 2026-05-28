<?php

namespace App\Policies;

use App\Models\CorreoEnviado;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CorreoEnviadoPolicy
{
    use HandlesAuthorization;

    private function esAutorizado(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->hasRole('abogado');
    }

    public function viewAny(User $user): bool
    {
        return $this->esAutorizado($user);
    }

    public function view(User $user, CorreoEnviado $correo): bool
    {
        return $this->esAutorizado($user);
    }

    public function create(User $user): bool
    {
        return $this->esAutorizado($user);
    }

    public function update(User $user, CorreoEnviado $correo): bool
    {
        return false; // Los correos enviados no se editan
    }

    public function delete(User $user, CorreoEnviado $correo): bool
    {
        return $user->hasRole('super_admin');
    }

    public function restore(User $user, CorreoEnviado $correo): bool
    {
        return false;
    }

    public function forceDelete(User $user, CorreoEnviado $correo): bool
    {
        return false;
    }
}
