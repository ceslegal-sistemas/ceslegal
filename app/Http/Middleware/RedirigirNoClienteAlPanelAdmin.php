<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espejo de RedirigirClienteAlPanelEmpresa: un bufete/admin/abogado
 * autenticado que entra a /empresa (enlace equivocado, marcador, prueba) ya
 * no tiene permiso ahí (ver User::canAccessPanel()) - sin este middleware,
 * Filament\Http\Middleware\Authenticate lo detendría con un 403 seco. Se
 * redirige de una a su panel real (/admin) antes de que eso pase.
 *
 * Va en el middleware BASE del panel 'empresa' (Panel::middleware(), no
 * ->authMiddleware()), mismo motivo que el original: correr ANTES del grupo
 * de authMiddleware (donde vive el Authenticate de Filament que haría el
 * abort_if(...,403) - ver vendor/filament/filament/routes/web.php).
 */
class RedirigirNoClienteAlPanelAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->role !== 'cliente') {
            return redirect(Filament::getPanel('admin')->getUrl());
        }

        return $next($request);
    }
}
