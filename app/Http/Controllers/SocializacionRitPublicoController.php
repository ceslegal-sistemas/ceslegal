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

        // Esta pagina lleva un token CSRF y un snapshot de Livewire propios de
        // CADA visita/sesion - si un proxy/CDN delante del hosting (Cloudflare,
        // ya documentado cacheando assets estaticos en este mismo dominio) la
        // cachea como una pagina normal, TODOS los visitantes reciben el MISMO
        // token CSRF congelado del momento en que se cacheo, que nunca coincide
        // con su propia sesion -> el submit del formulario falla siempre con
        // 419, y Livewire no muestra ningun aviso visible ante ese error.
        return response()
            ->view('rit.socializacion', ['empresa' => $empresa])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }
}
