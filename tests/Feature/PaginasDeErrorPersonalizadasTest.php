<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Páginas de error propias (403/404/405/419/500), todas compartiendo
 * errors/_shell.blade.php - antes solo existía 403, y cualquier otro error
 * (incluido un MethodNotAllowedHttpException real reportado por el usuario
 * en GET /empresa/logout) caía en la página de debug cruda de Laravel.
 */
class PaginasDeErrorPersonalizadasTest extends TestCase
{
    public function test_las_5_paginas_de_error_renderizan_sin_excepcion(): void
    {
        foreach (['403', '404', '405', '419', '500'] as $codigo) {
            $html = view("errors.{$codigo}")->render();

            $this->assertStringContainsString("Error {$codigo}", $html);
            $this->assertStringContainsString('LUPE Legal', $html);
            $this->assertStringContainsString('Volver al inicio', $html);
        }
    }

    public function test_solo_la_pagina_500_muestra_el_contacto_real(): void
    {
        $html500 = view('errors.500')->render();
        $this->assertStringContainsString('3196777103', $html500);
        $this->assertStringContainsString('admin@ceslegal.co', $html500);

        foreach (['403', '404', '405', '419'] as $codigo) {
            $html = view("errors.{$codigo}")->render();
            $this->assertStringNotContainsString('3196777103', $html);
            $this->assertStringNotContainsString('admin@ceslegal.co', $html);
        }
    }

    public function test_404_y_405_muestran_el_mismo_mensaje_al_cliente(): void
    {
        // El cliente no necesita distinguir "no existe" de "método no
        // permitido" - bug real reportado: GET /empresa/logout (405) desde
        // Safari en iPhone mostraba la página de debug cruda. Solo el
        // código de error visible (404 vs 405) y el <title> difieren.
        $mensaje = 'Esta página no está disponible';

        $this->assertStringContainsString($mensaje, view('errors.404')->render());
        $this->assertStringContainsString($mensaje, view('errors.405')->render());
    }
}
