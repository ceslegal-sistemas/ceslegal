<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Representación canónica de los días hábiles de una empresa.
 *
 * Un conjunto de días se modela como un arreglo de números de día ISO
 * (1 = lunes … 7 = domingo). Esto permite CUALQUIER combinación, incluido el
 * domingo y 24/7, a diferencia del antiguo esquema binario (lunes_viernes /
 * lunes_sabado). Se mantiene compatibilidad hacia atrás con esos valores legados.
 */
class DiasHabiles
{
    /** Etiquetas por día ISO (1 = lunes … 7 = domingo). */
    public const LABELS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /** Semana laboral estándar (lunes a viernes), usada como respaldo. */
    public const DEFECTO = [1, 2, 3, 4, 5];

    /** Convierte un valor legado ('lunes_viernes' | 'lunes_sabado') a conjunto ISO. */
    public static function desdeLegado(?string $valor): array
    {
        return match ($valor) {
            'lunes_sabado' => [1, 2, 3, 4, 5, 6],
            'lunes_domingo' => [1, 2, 3, 4, 5, 6, 7],
            'lunes_viernes' => [1, 2, 3, 4, 5],
            default => self::DEFECTO,
        };
    }

    /**
     * Normaliza cualquier entrada a un conjunto ISO limpio (ordenado, único, 1..7).
     * Acepta: arreglo de enteros, arreglo de etiquetas, o cadena legada.
     */
    public static function normalizar($valor): array
    {
        if (is_string($valor)) {
            return self::desdeLegado($valor);
        }

        if (! is_array($valor)) {
            return [];
        }

        $set = [];
        foreach ($valor as $v) {
            $n = is_numeric($v) ? (int) $v : self::isoDesdeNombre((string) $v);
            if ($n >= 1 && $n <= 7) {
                $set[$n] = true;
            }
        }

        $set = array_keys($set);
        sort($set);

        return $set;
    }

    /** Deriva el valor legado más cercano desde un conjunto ISO (para compatibilidad). */
    public static function aLegado(array $set): string
    {
        $set = self::normalizar($set);

        if (in_array(7, $set, true)) {
            return 'lunes_domingo';
        }
        if (in_array(6, $set, true)) {
            return 'lunes_sabado';
        }

        return 'lunes_viernes';
    }

    /** Texto legible del conjunto: "Lunes a Viernes", "Todos los días (24/7)", "Lunes, Miércoles, Domingo". */
    public static function texto(array $set): string
    {
        $set = self::normalizar($set);

        if (empty($set)) {
            return 'Sin definir';
        }
        if ($set === [1, 2, 3, 4, 5, 6, 7]) {
            return 'Todos los días (24/7)';
        }
        if ($set === [1, 2, 3, 4, 5]) {
            return 'Lunes a Viernes';
        }
        if ($set === [1, 2, 3, 4, 5, 6]) {
            return 'Lunes a Sábado';
        }

        // Rango contiguo genérico (p. ej. martes a sábado)
        if (self::esContiguo($set)) {
            return self::LABELS[$set[0]] . ' a ' . self::LABELS[end($set)];
        }

        // Lista explícita
        return implode(', ', array_map(fn ($d) => self::LABELS[$d], $set));
    }

    /**
     * Detecta el conjunto de días hábiles a partir del texto del RIT.
     * Devuelve el conjunto ISO o null si no logra determinarlo (se pedirá confirmar).
     */
    public static function detectar(?string $texto): ?array
    {
        if (! $texto) {
            return null;
        }

        $t = Str::lower(Str::ascii($texto));

        // 24/7 — todos los días
        if (preg_match('/24\s*\/?\s*7|24\s*horas|todos\s+los\s+d[ií]as|siete\s*\(?\s*7?\s*\)?\s*d[ií]as|lunes\s+a\s+domingo|domingo\s+a\s+domingo|de\s+domingo\s+a\s+domingo/u', $t)) {
            return [1, 2, 3, 4, 5, 6, 7];
        }

        // Lunes a sábado
        if (preg_match('/lunes\s+a\s+sabado|hasta\s+el\s+sabado|inclu[ií]d[oa]s?\s+(el\s+|los\s+)?sabado|seis\s*\(?\s*6\s*\)?\s*d[ií]as/u', $t)) {
            // ¿Además menciona domingo como laborable? → semana completa
            if (preg_match('/domingo[s]?\s+(y\s+festivos\s+)?(labora|se\s+labora|se\s+trabaja|h[aá]bil)/u', $t)) {
                return [1, 2, 3, 4, 5, 6, 7];
            }

            return [1, 2, 3, 4, 5, 6];
        }

        // Lunes a viernes
        if (preg_match('/lunes\s+a\s+viernes|cinco\s*\(?\s*5\s*\)?\s*d[ií]as/u', $t)) {
            return [1, 2, 3, 4, 5];
        }

        return null;
    }

    /** Opciones (ISO => etiqueta) para selectores de formulario. */
    public static function opciones(): array
    {
        return self::LABELS;
    }

    private static function esContiguo(array $set): bool
    {
        for ($i = 1; $i < count($set); $i++) {
            if ($set[$i] !== $set[$i - 1] + 1) {
                return false;
            }
        }

        return true;
    }

    private static function isoDesdeNombre(string $nombre): int
    {
        $n = Str::lower(Str::ascii(trim($nombre)));

        return match ($n) {
            'lunes' => 1,
            'martes' => 2,
            'miercoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sabado' => 6,
            'domingo' => 7,
            default => 0,
        };
    }
}
