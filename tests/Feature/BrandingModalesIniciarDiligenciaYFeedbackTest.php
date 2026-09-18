<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Branding LUPE Legal para los 2 modales de bajo riesgo identificados en el
 * rehearsal de QA del 2026-09-16 (ver backlog-branding-3-pantallas-2026-09-16):
 * la confirmación "¿Iniciar diligencia?" (SweetAlert2, ícono "?" gris por
 * defecto) y el modal de feedback post-diligencia (heading/icono genéricos
 * de Filament). El badge "Recomendado" (3er punto de ese backlog) queda
 * fuera de alcance - necesita inspección de CSS aparte.
 */
class BrandingModalesIniciarDiligenciaYFeedbackTest extends TestCase
{
    public function test_iniciar_diligencia_usa_funcion_nombrada_no_swal_inline_en_el_atributo(): void
    {
        $fuente = file_get_contents(resource_path('views/livewire/formulario-descargos.blade.php'));

        // El Swal.fire(...) inline dentro de @click="..." se movió a una
        // función nombrada - un SVG con comillas dobles dentro de un
        // atributo HTML también de comillas dobles rompería el parseo
        // (mismo tipo de bug real ya ocurrido esta sesión en otro archivo).
        $this->assertStringContainsString('confirmarIniciarDiligencia($wire)', $fuente);
        $this->assertStringContainsString('window.confirmarIniciarDiligencia = function', $fuente);
        $this->assertSame(1, substr_count($fuente, 'Swal.fire('), 'Swal.fire debe llamarse una sola vez, desde la función nombrada, no inline en el botón.');
    }

    public function test_iniciar_diligencia_usa_lord_icon_no_svg_plano_ni_icono_generico(): void
    {
        $fuente = file_get_contents(resource_path('views/livewire/formulario-descargos.blade.php'));

        // Aislar el bloque de la función confirmarIniciarDiligencia: el
        // resto del archivo sí tiene SVGs planos preexistentes (avatar del
        // trabajador, triángulo de advertencia, ícono del botón) fuera de
        // alcance de este fix - solo el ícono del modal debe usar lord-icon.
        preg_match('/window\.confirmarIniciarDiligencia = function.*?\n        \};/s', $fuente, $matches);
        $bloque = $matches[0] ?? '';

        $this->assertNotEmpty($bloque, 'No se encontró la función confirmarIniciarDiligencia.');
        $this->assertStringNotContainsString("icon: 'question'", $bloque);
        $this->assertStringContainsString('iconHtml:', $bloque);
        $this->assertStringContainsString('<lord-icon', $bloque);
        $this->assertStringNotContainsString('<svg', $bloque, 'No debe quedar ningún SVG plano inventado para este ícono.');
    }

    public function test_la_pagina_host_carga_el_script_de_lordicon(): void
    {
        $fuente = file_get_contents(resource_path('views/descargos/formulario.blade.php'));

        $this->assertStringContainsString('cdn.lordicon.com/lordicon.js', $fuente);
    }

    public function test_feedback_ya_no_usa_heading_icono_generico_de_filament(): void
    {
        $fuente = file_get_contents(app_path(
            'Filament/Admin/Resources/ProcesoDisciplinarioResource/Pages/ListProcesoDisciplinarios.php'
        ));

        $this->assertStringNotContainsString("modalHeading('Diligencia completada", $fuente);
        $this->assertStringNotContainsString("modalIcon('heroicon-o-document-check')", $fuente);
        $this->assertStringContainsString('feedback-diligencia-hero', $fuente);
    }

    public function test_el_partial_de_branding_del_feedback_usa_rit_hero(): void
    {
        $fuente = file_get_contents(resource_path('views/filament/components/feedback-diligencia-hero.blade.php'));

        $this->assertStringContainsString('lupe-hero-styles', $fuente);
        $this->assertStringContainsString('rit-hero', $fuente);
        $this->assertStringContainsString('¿Cómo te fue?', $fuente);
    }

    public function test_el_partial_de_feedback_renderiza_sin_error(): void
    {
        $html = view('filament.components.feedback-diligencia-hero')->render();

        $this->assertStringContainsString('rit-hero', $html);
        $this->assertStringContainsString('Diligencia Completada', $html);
    }
}
