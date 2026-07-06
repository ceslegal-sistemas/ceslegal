<?php

namespace App\Http\Controllers;

use App\Models\BufeteInvitacion;

class BufeteInvitacionController extends Controller
{
    /** Aceptar una invitación por token (enlace enviado a la empresa). */
    public function aceptar(string $token)
    {
        $invitacion = BufeteInvitacion::where('token', $token)->first();

        if (! $invitacion) {
            abort(404);
        }

        $ok = $invitacion->aceptar();

        return redirect('/admin')->with(
            $ok ? 'success' : 'error',
            $ok
                ? 'La empresa quedó vinculada al bufete correctamente.'
                : 'La invitación no es válida o ya expiró.'
        );
    }
}
