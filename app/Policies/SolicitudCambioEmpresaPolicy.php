<?php

namespace App\Policies;

use App\Models\SolicitudCambioEmpresa;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SolicitudCambioEmpresaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_solicitud::cambio::empresa');
    }

    public function view(User $user, SolicitudCambioEmpresa $solicitud): bool
    {
        return $user->can('view_solicitud::cambio::empresa');
    }

    public function update(User $user, SolicitudCambioEmpresa $solicitud): bool
    {
        return $user->can('update_solicitud::cambio::empresa');
    }
}
