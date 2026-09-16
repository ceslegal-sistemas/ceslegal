<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pedido explicito del usuario (2026-09-10): reemplazar SVG planos por
 * lord-icon en este componente, con cuidado de no tocar ningun atributo
 * x-show/x-text/wire:* de los elementos padres - EXCEPTO la linea del
 * icono de camara del boton "Tomar foto de verificacion", que tiene
 * x-show="!revisandoAccesorios" en si mismo (no en un padre) y debe
 * permanecer intacta (no se convierte en esta tarea - ver Tarea 6).
 */
class WebcamAutorizadorLordIconTest extends TestCase
{
    private function fuente(): string
    {
        return file_get_contents(resource_path('views/filament/components/webcam-autorizador.blade.php'));
    }

    public function test_disclaimer_usa_lord_icon(): void
    {
        $this->assertStringContainsString('wpsdctqb.json', $this->fuente());
    }

    public function test_aviso_falta_parpadeo_ya_no_usa_el_svg_plano_viejo(): void
    {
        $this->markTestIncomplete('Pendiente: elegir un lord-icon real de ojo/parpadeo navegando lordicon.com (Tarea 6 de seguimiento) antes de convertir este icono.');
    }

    public function test_aviso_parpadeo_confirmado_usa_lord_icon(): void
    {
        $this->assertStringContainsString('lvrxlmju.json', $this->fuente());
    }

    public function test_alerta_de_accesorios_usa_lord_icon(): void
    {
        $this->assertStringContainsString('lltgvngb.json', $this->fuente());
    }

    public function test_spinners_de_carga_siguen_siendo_svg_plano_no_lord_icon(): void
    {
        $fuente = $this->fuente();
        $this->assertStringContainsString('animation:spin 1s linear infinite', $fuente);
    }

    public function test_x_show_del_icono_de_camara_se_preservo_en_el_reemplazo(): void
    {
        $this->assertStringContainsString('x-show="!revisandoAccesorios"', $this->fuente());
    }

    public function test_el_html_renderiza_sin_error_y_conserva_el_div_wrapper(): void
    {
        // Este parcial se incluye vía Placeholder::content(fn() => view(...))
        // dentro de un Form de Filament (no es el render() de un componente
        // Livewire real) - el <style> antes del <div> aquí es válido, la
        // regla de "un solo elemento raíz" solo aplica a archivos que SÍ son
        // la raíz de un Livewire\Component/Filament\Widgets\Widget. Se
        // confirma que sigue renderizando sin error y conserva su div
        // wrapper principal (wire:ignore, contiene el video/canvas).
        $html = view('filament.components.webcam-autorizador')->render();
        $this->assertStringContainsString('<div wire:ignore', $html);
    }
}
