<?php

namespace App\Http\Controllers;

use App\Services\ImpugnacionPortalService;

class ImpugnacionPublicaController extends Controller
{
    /**
     * Ruta pública (sin auth): la autorización real es poseer el token y, para
     * presentar algo, verificar el código OTP (ver ImpugnacionPortalService).
     * Sin caché: la página lleva el token CSRF y el snapshot de Livewire de CADA
     * visita (mismo motivo documentado en SocializacionRitPublicoController).
     */
    public function mostrar(string $token, ImpugnacionPortalService $servicio)
    {
        $proceso = $servicio->resolverPorToken($token);

        return response()
            ->view('impugnacion.acceso', [
                'token' => $token,
                'empresaNombre' => $proceso?->empresa?->razon_social,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }
}
