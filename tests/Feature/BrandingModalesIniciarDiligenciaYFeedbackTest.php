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

    public function test_iniciar_diligencia_ya_no_usa_el_icono_generico_de_sweetalert2(): void
    {
        $fuente = file_get_contents(resource_path('views/livewire/formulario-descargos.blade.php'));

        $this->assertStringNotContainsString("icon: 'question'", $fuente);
        $this->assertStringContainsString('iconHtml:', $fuente);
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
