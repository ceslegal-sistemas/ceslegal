<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Circuit Breaker para la API de Gemini.
 *
 * Evita llamadas continuas cuando Gemini está caído (503/429),
 * dando tiempo a la API para recuperarse antes de reintentar.
 *
 * Estados:
 *  - CERRADO  → llamadas pasan normal (estado inicial)
 *  - ABIERTO  → bloquea llamadas por OPEN_TTL segundos
 *  - SEMI-ABIERTO → permite una sola llamada de prueba (cuando expira el TTL)
 */
class GeminiCircuitBreaker
{
    const FAILURES_KEY = 'gemini_cb_failures';
    const OPEN_KEY     = 'gemini_cb_open';

    // Número de fallos antes de abrir el circuito
    const THRESHOLD = 8;

    // Segundos que el circuito permanece abierto antes de probar de nuevo
    const OPEN_TTL = 90;

    // Ventana de tiempo (segundos) en que se cuentan los fallos
    const WINDOW = 120;

    /**
     * Retorna true si el circuito está abierto (no llamar la API).
     */
    public static function isOpen(): bool
    {
        return (bool) Cache::get(self::OPEN_KEY, false);
    }

    /**
     * Registrar un fallo. Si supera el umbral, abre el circuito.
     */
    public static function recordFailure(string $modelo = ''): void
    {
        $failures = (int) Cache::get(self::FAILURES_KEY, 0) + 1;
        Cache::put(self::FAILURES_KEY, $failures, self::WINDOW);

        if ($failures >= self::THRESHOLD) {
            Cache::put(self::OPEN_KEY, true, self::OPEN_TTL);
            Cache::forget(self::FAILURES_KEY);

            Log::warning('GeminiCircuitBreaker: circuito ABIERTO — se bloquearán llamadas por ' . self::OPEN_TTL . 's', [
                'fallos_acumulados' => $failures,
                'ultimo_modelo'     => $modelo,
            ]);
        }
    }

    /**
     * Registrar éxito. Cierra el circuito si estaba abierto.
     */
    public static function recordSuccess(): void
    {
        Cache::forget(self::FAILURES_KEY);

        if (Cache::has(self::OPEN_KEY)) {
            Cache::forget(self::OPEN_KEY);
            Log::info('GeminiCircuitBreaker: circuito CERRADO — API recuperada');
        }
    }

    /**
     * Retorna cuántos segundos faltan para que el circuito se cierre.
     * 0 si ya está cerrado.
     */
    public static function segundosHastaCierre(): int
    {
        return (int) Cache::getTimeToLive(self::OPEN_KEY) ?? 0;
    }
}
