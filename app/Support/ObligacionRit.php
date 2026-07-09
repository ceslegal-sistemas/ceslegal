<?php

namespace App\Support;

/**
 * Determina si una empresa está obligada a tener Reglamento Interno de Trabajo
 * según el Art. 105 del Código Sustantivo del Trabajo:
 *   - Comercial o de servicios: más de 5 trabajadores permanentes.
 *   - Industrial: más de 10.
 *   - Agropecuaria, ganadera o forestal: más de 20.
 *
 * La categoría se infiere de la sección CIIU de la actividad económica. El mapeo
 * es un punto de ajuste jurídico (revisar con el área legal si se requiere afinar).
 */
class ObligacionRit
{
    /** Umbral de empleados a partir del cual (estrictamente mayor) hay obligación. */
    public static function umbral(?string $seccionCiiu): int
    {
        $s = strtoupper(trim((string) $seccionCiiu));

        return match (true) {
            // A: agricultura, ganadería, silvicultura y pesca.
            $s === 'A' => 20,
            // B minas · C manufactura · D energía · E agua/residuos · F construcción.
            in_array($s, ['B', 'C', 'D', 'E', 'F'], true) => 10,
            // G comercio y H–U servicios (y desconocida): categoría comercial/servicios.
            default => 5,
        };
    }

    /** Nombre legible de la categoría (para el texto explicativo). */
    public static function categoria(?string $seccionCiiu): string
    {
        return match (self::umbral($seccionCiiu)) {
            20 => 'agropecuaria, ganadera o forestal',
            10 => 'industrial',
            default => 'comercial o de servicios',
        };
    }

    /** ¿Obligada a tener RIT? null si aún no se conoce el número de empleados. */
    public static function requiere(?int $numeroEmpleados, ?string $seccionCiiu): ?bool
    {
        if ($numeroEmpleados === null) {
            return null;
        }

        return $numeroEmpleados > self::umbral($seccionCiiu);
    }

    /** Texto explicativo del resultado para mostrar en el formulario. */
    public static function explicacion(?int $numeroEmpleados, ?string $seccionCiiu): string
    {
        if ($numeroEmpleados === null) {
            return 'Indique el número de empleados para determinar si la empresa está obligada a tener RIT.';
        }

        $umbral    = self::umbral($seccionCiiu);
        $categoria = self::categoria($seccionCiiu);

        if ($numeroEmpleados > $umbral) {
            return "OBLIGADA a tener Reglamento Interno de Trabajo: es una empresa {$categoria} con más de {$umbral} empleados (Art. 105 CST).";
        }

        return "NO está obligada a tener RIT (empresa {$categoria} con {$umbral} empleados o menos). Puede regirse por el Código Sustantivo del Trabajo (CST).";
    }
}
