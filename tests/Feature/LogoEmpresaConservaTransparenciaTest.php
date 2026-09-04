<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Bug real reportado por el usuario: el logo de la empresa salía con fondo
 * opaco (sólido) en el membrete de los PDF, aunque el PNG original subido sí
 * tenía transparencia real. Causa raíz: tanto Register.php
 * (crearCuentaEmpresa()) como EditEmpresa.php (afterSave()) recodifican el
 * PNG/JPG subido con GD (imagecreatefrompng + imagepng) para garantizar que
 * LogoColorService reciba siempre un PNG real - pero sin
 * imagealphablending(false) + imagesavealpha(true) antes de imagepng(), GD
 * aplana el canal alfa a un fondo sólido al reescribir el archivo.
 *
 * Esta prueba no monta el flujo completo de Livewire (requeriría simular el
 * FileUpload temporal de Filament); en su lugar verifica directamente, a
 * nivel de GD, que el mismo patrón exacto usado en ambos archivos preserva
 * la transparencia - si alguno de los dos volviera a perder las dos líneas
 * de fix, este test no cubre esa regresión puntual, pero sí prueba que el
 * patrón correcto realmente funciona con el motor de imágenes de este
 * proyecto (GD, no Imagick).
 */
class LogoEmpresaConservaTransparenciaTest extends TestCase
{
    public function test_recodificar_un_png_transparente_con_el_patron_del_fix_conserva_el_canal_alfa(): void
    {
        $origen = tempnam(sys_get_temp_dir(), 'logo_origen_') . '.png';
        $destino = tempnam(sys_get_temp_dir(), 'logo_destino_') . '.png';

        // PNG de prueba con un cuadrado opaco sobre fondo 100% transparente.
        $lienzo = imagecreatetruecolor(20, 20);
        imagesavealpha($lienzo, true);
        $transparente = imagecolorallocatealpha($lienzo, 0, 0, 0, 127);
        imagefill($lienzo, 0, 0, $transparente);
        $opaco = imagecolorallocatealpha($lienzo, 200, 30, 30, 0);
        imagefilledrectangle($lienzo, 5, 5, 14, 14, $opaco);
        imagepng($lienzo, $origen);
        imagedestroy($lienzo);

        // Mismo patrón exacto que EditEmpresa.php::afterSave() y
        // Register.php::crearCuentaEmpresa() tras el fix.
        $imagenOrigen = imagecreatefrompng($origen);
        imagealphablending($imagenOrigen, false);
        imagesavealpha($imagenOrigen, true);
        imagepng($imagenOrigen, $destino);
        imagedestroy($imagenOrigen);

        $resultado = imagecreatefrompng($destino);
        $colorFondo = imagecolorat($resultado, 0, 0);
        $alfaFondo = ($colorFondo >> 24) & 0x7F;
        $colorCuadro = imagecolorat($resultado, 10, 10);
        $alfaCuadro = ($colorCuadro >> 24) & 0x7F;
        imagedestroy($resultado);

        unlink($origen);
        unlink($destino);

        // En PHP/GD, alfa 127 = totalmente transparente, 0 = totalmente opaco.
        $this->assertSame(127, $alfaFondo, 'El fondo debe seguir siendo transparente tras recodificar.');
        $this->assertSame(0, $alfaCuadro, 'El cuadro opaco debe seguir siendo opaco tras recodificar.');
    }

    public function test_sin_el_fix_gd_aplana_la_transparencia_a_un_fondo_solido(): void
    {
        $origen = tempnam(sys_get_temp_dir(), 'logo_origen_') . '.png';
        $destino = tempnam(sys_get_temp_dir(), 'logo_destino_') . '.png';

        $lienzo = imagecreatetruecolor(20, 20);
        imagesavealpha($lienzo, true);
        $transparente = imagecolorallocatealpha($lienzo, 0, 0, 0, 127);
        imagefill($lienzo, 0, 0, $transparente);
        imagepng($lienzo, $origen);
        imagedestroy($lienzo);

        // Patrón ANTERIOR al fix: sin imagealphablending()/imagesavealpha().
        $imagenOrigen = imagecreatefrompng($origen);
        imagepng($imagenOrigen, $destino);
        imagedestroy($imagenOrigen);

        $resultado = imagecreatefrompng($destino);
        $colorFondo = imagecolorat($resultado, 0, 0);
        $alfaFondo = ($colorFondo >> 24) & 0x7F;
        imagedestroy($resultado);

        unlink($origen);
        unlink($destino);

        // Documenta el bug real: sin el fix, el fondo deja de ser transparente.
        $this->assertNotSame(127, $alfaFondo, 'Sin el fix, GD aplana la transparencia (confirma el bug real).');
    }
}
