<?php

namespace Tests\Feature;

use App\Services\LogoColorService;
use Tests\TestCase;

class LogoColorServiceTest extends TestCase
{
    private function crearImagenSolida(int $r, int $g, int $b): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'logo_test_') . '.png';
        $img = imagecreatetruecolor(40, 40);
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        imagepng($img, $ruta);
        imagedestroy($img);

        return $ruta;
    }

    public function test_detecta_el_color_solido_saturado_de_una_imagen(): void
    {
        // Coral #E46350 = rgb(228, 99, 80)
        $ruta = $this->crearImagenSolida(228, 99, 80);

        $color = app(LogoColorService::class)->colorDominante($ruta);

        $this->assertSame('#E46350', $color);

        unlink($ruta);
    }

    public function test_cae_al_gris_de_respaldo_con_una_imagen_en_blanco_y_negro(): void
    {
        $ruta = $this->crearImagenSolida(20, 20, 20);

        $color = app(LogoColorService::class)->colorDominante($ruta);

        $this->assertSame('#3A3A3A', $color);

        unlink($ruta);
    }

    public function test_cae_al_gris_de_respaldo_con_una_imagen_muy_clara(): void
    {
        // Rosa muy claro: su saturación en realidad SÍ pasa el umbral (~0.33,
        // > 0.15) - lo que lo descarta es la luminancia (~0.97, > 0.85).
        $ruta = $this->crearImagenSolida(250, 245, 248);

        $color = app(LogoColorService::class)->colorDominante($ruta);

        $this->assertSame('#3A3A3A', $color);

        unlink($ruta);
    }
}
