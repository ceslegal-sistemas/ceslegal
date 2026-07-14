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

    /**
     * Recuadro con estilo (HTML) para mostrar el resultado en el formulario:
     * pendiente (neutro), obligada (ámbar) o no obligada (verde).
     */
    public static function avisoHtml(?int $numeroEmpleados, ?string $seccionCiiu): string
    {
        $requiere = self::requiere($numeroEmpleados, $seccionCiiu);

        [$a, $bg, $b, $titulo, $texto, $lord] = match ($requiere) {
            true => [
                '#d97706', 'rgba(217,119,6,.08)', 'rgba(217,119,6,.25)',
                'Obligada a tener Reglamento Interno',
                'Es una empresa ' . self::categoria($seccionCiiu) . ' con más de ' . self::umbral($seccionCiiu) . ' empleados (Art. 105 CST). Debe cargar o construir su RIT.',
                'hmpomorl', // alerta
            ],
            false => [
                '#16a34a', 'rgba(22,163,74,.08)', 'rgba(22,163,74,.25)',
                'No está obligada a tener RIT',
                'Empresa ' . self::categoria($seccionCiiu) . ' con ' . self::umbral($seccionCiiu) . ' empleados o menos. Puede regirse por el Código Sustantivo del Trabajo (CST). Si desea, igual puede cargar su RIT.',
                'lvrxlmju', // check
            ],
            default => [
                '#78716c', 'rgba(120,113,108,.08)', 'rgba(120,113,108,.2)',
                'Pendiente de determinar',
                'Indique el número de empleados para saber si la empresa está obligada a tener Reglamento Interno de Trabajo.',
                'fikcyfpp', // documento
            ],
        };

        $css = '<style>'
            . '.obl-rit{display:flex;gap:10px;align-items:center;padding:12px 16px;border-radius:12px;border:1px solid var(--b);border-left:4px solid var(--a);background:var(--bg)}'
            . '.obl-rit h4{margin:0 0 2px;font-weight:700;font-size:13px;line-height:1.2;color:var(--a)}'
            . '.obl-rit p{margin:0;font-size:12.5px;line-height:1.5;color:#57534e}'
            . 'html.dark .obl-rit p{color:#d6d3d1}'
            . '.obl-rit lord-icon{width:38px;height:38px;flex-shrink:0}'
            . '</style>';

        return '<script src="https://cdn.lordicon.com/lordicon.js"></script>' . $css
            . '<div class="obl-rit" style="--a:' . $a . ';--bg:' . $bg . ';--b:' . $b . '">'
            . '<lord-icon src="https://cdn.lordicon.com/' . $lord . '.json" trigger="loop" delay="700" colors="primary:' . $a . ',secondary:' . $a . '"></lord-icon>'
            . '<div><h4>' . $titulo . '</h4><p>' . $texto . '</p></div>'
            . '</div>';
    }
}
