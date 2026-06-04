<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Verifica que el dominio de un email pueda recibir correos, es decir, que tenga
 * registros DNS MX (o, en su defecto, un registro A/AAAA — algunos dominios reciben
 * sin MX explícito).
 *
 * Atrapa errores típicos de digitación ("gmial.com", "hotmial.com", dominios
 * inexistentes) ANTES de intentar el envío.
 *
 * Nota: NO detecta si el buzón concreto existe (eso solo se sabe por el rebote
 * diferido que el servidor de destino envía minutos después). Solo valida el dominio.
 */
class EmailDominioEntregable implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || $value === '') {
            return; // la validación de "required"/"email" se encarga del resto
        }

        $arroba = strrpos($value, '@');
        if ($arroba === false) {
            return; // formato inválido → lo reporta la regla email()
        }

        $dominio = substr($value, $arroba + 1);
        if ($dominio === '') {
            return;
        }

        // Sin acceso a DNS (entornos sin red) no bloqueamos para no dar falsos negativos.
        if (!function_exists('checkdnsrr')) {
            return;
        }

        $tieneMx = @checkdnsrr($dominio, 'MX');
        $tieneA  = @checkdnsrr($dominio, 'A') || @checkdnsrr($dominio, 'AAAA');

        if (!$tieneMx && !$tieneA) {
            $fail("El dominio \"{$dominio}\" no existe o no puede recibir correos. Revisa que el email esté bien escrito.");
        }
    }
}
