<?php

namespace App\Support;

/**
 * Convierte un monto en pesos colombianos a su representación en letras
 * para cláusulas de remuneración de contratos (ej. "la suma de
 * ______________ PESOS ($ _________ COP)"). Usa NumberFormatter de la
 * extensión intl de PHP (ya disponible en este entorno, sin librerías
 * nuevas) en vez de escribir un conversor número-a-letras desde cero.
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

        // Regla gramatical del español (bug real reportado por el usuario,
        // 2026-09-28: "CUATRO MILLONES DE PESOS", no "CUATRO MILLONES
        // PESOS"): "de" va después de millón/millones/billón/billones
        // cuando esa palabra queda inmediatamente antes del sustantivo
        // contado (múltiplo exacto, sin miles/unidades de por medio) - ej.
        // "cuatro millones DE pesos", pero "un millón trescientos mil
        // pesos" (sin "de", porque "millón" no es la última palabra). NO
        // aplica a "mil" ("mil pesos", nunca "mil de pesos").
        $palabras = explode(' ', trim($letras));
        $ultimaPalabra = mb_strtolower(end($palabras));
        $necesitaDe = in_array($ultimaPalabra, ['millón', 'millones', 'billón', 'billones'], true);

        return sprintf(
            '%s%s PESOS ($%s COP)',
            mb_strtoupper($letras),
            $necesitaDe ? ' DE' : '',
            number_format($valor, 0, ',', '.')
        );
    }
}
