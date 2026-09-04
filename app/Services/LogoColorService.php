<?php

namespace App\Services;

/**
 * Calcula un color de acento a partir del logo de una empresa, para usarlo
 * en el membrete de los documentos generados (franja lateral, pie de
 * página). Nunca devuelve un color de bajo contraste - si no encuentra
 * ninguno seguro, cae a un gris oscuro fijo.
 */
class LogoColorService
{
    private const SATURACION_MINIMA = 0.15;
    private const LUMINANCIA_MAXIMA = 0.85;
    private const COLOR_RESPALDO = '#3A3A3A';

    public function colorDominante(string $rutaImagen): string
    {
        $imagen = @imagecreatefrompng($rutaImagen);

        if ($imagen === false) {
            return self::COLOR_RESPALDO;
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $paso = max(1, (int) (min($ancho, $alto) / 30));

        $mejorColor = null;
        $mejorSaturacion = -1;

        for ($y = 0; $y < $alto; $y += $paso) {
            for ($x = 0; $x < $ancho; $x += $paso) {
                $rgb = imagecolorat($imagen, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $saturacion = $this->saturacion($r, $g, $b);

                if ($saturacion < self::SATURACION_MINIMA) {
                    continue;
                }

                if ($saturacion > $mejorSaturacion) {
                    $mejorSaturacion = $saturacion;
                    $mejorColor = [$r, $g, $b];
                }
            }
        }

        imagedestroy($imagen);

        if ($mejorColor === null) {
            return self::COLOR_RESPALDO;
        }

        [$r, $g, $b] = $mejorColor;

        if ($this->luminanciaRelativa($r, $g, $b) > self::LUMINANCIA_MAXIMA) {
            return self::COLOR_RESPALDO;
        }

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    private function saturacion(int $r, int $g, int $b): float
    {
        $max = max($r, $g, $b) / 255;
        $min = min($r, $g, $b) / 255;

        if ($max === $min) {
            return 0.0;
        }

        $l = ($max + $min) / 2;
        $d = $max - $min;

        return $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
    }

    private function luminanciaRelativa(int $r, int $g, int $b): float
    {
        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    }
}
