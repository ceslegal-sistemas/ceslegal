<?php

namespace App\Support;

/**
 * Convierte un monto en pesos colombianos a su representación en letras
 * para cláusulas de remuneración de contratos (ej. "la suma de
 * ______________ PESOS ($ _________ COP)"). Usa NumberFormatter de la
 * extensión intl de PHP (ya disponible en este entorno, sin librerías
 * nuevas) en vez de escribir un conversor número-a-letras desde cero.
 *
 * Simplificación aceptada: no implementa la regla gramatical de "de" antes
 * de "pesos" cuando el número es un múltiplo exacto de millón (ej. "cinco
 * millones DE pesos" vs "un millón trescientos mil pesos" sin "de") - el
 * texto es igual de válido legalmente sin esa partícula.
 */
class MontoEnLetras
{
    public static function pesos(float $valor): string
    {
        $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
        $letras = $formatter->format((int) $valor);

        // ICU intercala un guion suave (U+00AD) al partir palabras largas
        // (ej. "ocho­cientos") para ayudar a la hifenación visual - no se ve
        // al leer pero queda embebido en el texto si no se limpia, y termina
        // metido dentro del PDF final.
        $letras = str_replace("\u{00AD}", '', $letras);

        return sprintf(
            '%s PESOS ($%s COP)',
            mb_strtoupper($letras),
            number_format($valor, 0, ',', '.')
        );
    }
}
