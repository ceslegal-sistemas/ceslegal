<?php

namespace App\Support;

class FormateoNumerico
{
    /**
     * Deja solo dígitos y los agrupa de a 3 con punto (ej. "2200000" o
     * "2.500.000" → "2.500.000"). El cast 'decimal:2' de Eloquent siempre
     * hidrata como "2200000.00" - un separador de miles agrupa SIEMPRE de a
     * 3 dígitos hacia la derecha, así que una cola de EXACTAMENTE 2 dígitos
     * tras un solo punto solo puede ser el decimal del cast, nunca un grupo
     * de miles real (eso produciría 220.000.000, 10x el valor real).
     */
    public static function miles(?string $state): ?string
    {
        $state = (string) $state;

        $digitos = preg_match('/^(\d+)\.\d{2}$/', $state, $m)
            ? $m[1]
            : preg_replace('/\D/', '', $state);

        if ($digitos === '' || $digitos === null) {
            return null;
        }

        return number_format((int) $digitos, 0, ',', '.');
    }
}
