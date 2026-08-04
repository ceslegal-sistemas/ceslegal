<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un cliente autenticado que entra a /admin (marcador viejo, enlace guardado,
 * costumbre) ya no tiene permiso ahí (ver User::canAccessPanel()) - sin este
 * middleware, Filament\Http\Middleware\Authenticate lo detendría con un 403
 * seco. Se redirige de una a su panel real (/empresa) antes de que eso pase.
 *
 * Va en el middleware BASE del panel (Panel::middleware(), no
 * ->authMiddleware()) para correr ANTES del Authenticate de Filament: las
 * rutas de la app registran primero el grupo de Panel::getMiddleware() y
 * dentro de ese, como grupo anidado, el de getAuthMiddleware() (ver
 * vendor/filament/filament/routes/web.php).
 */
class RedirigirClienteAlPanelEmpresa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user?->role === 'cliente') {
            return redirect(Filament::getPanel('empresa')->getUrl());
        }

        return $next($request);
    }
}
