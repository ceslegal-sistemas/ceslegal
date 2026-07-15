<?php

namespace App\Support;

/**
 * Gating del menú por Reglamento Interno.
 *
 * Regla de negocio: para el rol CLIENTE (empresa B2B), el RIT es OBLIGATORIO. Mientras no
 * tenga un RIT vigente (subido o construido/mejorado por IA), en el menú solo debe aparecer
 * "Mi Reglamento Interno"; el resto (Crear Citación de Descargos, Historial de Descargos,
 * Trabajadores, Sanciones Emitidas) permanece oculto hasta que cargue o construya su RIT.
 *
 * No aplica a super_admin, abogado ni bufete.
 */
class MenuRit
{
    /** ¿Debe ocultarse el resto del menú por falta de RIT vigente del cliente? */
    public static function clienteSinRit(): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->hasRole('cliente')) {
            return false;
        }

        return ! ($user->empresa?->tieneRitVigente() ?? false);
    }
}
