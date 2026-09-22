<?php

namespace App\Http\Controllers;

class SocializacionRitPublicoController extends Controller
{
    /**
     * Ruta publica (sin middleware auth) - la autorizacion real es poseer el
     * token, no la sesion. Empresa/Trabajador tienen el scope global
     * ScopedToBufeteOrEmpresa, que filtra por la empresa/bufete del usuario
     * AUTENTICADO en la sesion actual - si alguien abre este link con una
     * sesion de otro rol/empresa activa en el mismo navegador, el scope
     * resolveria la empresa equivocada en silencio sin este
     * withoutGlobalScope (mismo patron ya usado en DescargoPublicoController).
     */
    public function mostrar(string $token)
    {
        $empresa = \App\Models\Empresa::withoutGlobalScope('bufeteOrEmpresa')
            ->where('token_socializacion_rit', $token)
            ->first();

        if (!$empresa) {
            abort(404);
        }

        return view('rit.socializacion', ['empresa' => $empresa]);
    }
}
