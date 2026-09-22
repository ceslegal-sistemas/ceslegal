<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-22): la interfaz pública de
 * socialización del RIT debe sentirse igual que el formulario de
 * descargos (mismo componente/patrón visual ya probado con usuarios
 * reales) - no una versión más pobre. Verifica los elementos concretos
 * que faltaban: barra de progreso, feedback de carga (wire:loading) en
 * cada botón de acción, y el overlay global "Procesando...".
 */
class SocializacionRitParidadVisualDescargosTest extends TestCase
{
    public function test_incluye_header_sticky_y_barra_de_progreso(): void
    {
        $fuente = file_get_contents(resource_path('views/livewire/socializacion-rit.blade.php'));

        $this->assertStringContainsString('sticky top-0', $fuente);
        $this->assertStringContainsString('$pasoActual', $fuente);
    }

    public function test_cada_boton_de_accion_tiene_feedback_de_carga(): void
    {
        $fuente = file_get_contents(resource_path('views/livewire/socializacion-rit.blade.php'));

        foreach (['buscarTrabajador', 'guardarDatos', 'aceptarReglamento'] as $metodo) {
            $this->assertStringContainsString('wire:target="' . $metodo . '"', $fuente);
        }
        $this->assertStringContainsString('animate-spin', $fuente);
    }

    public function test_incluye_el_overlay_global_de_procesando(): void
    {
        $fuente = file_get_contents(resource_path('views/livewire/socializacion-rit.blade.php'));

        $this->assertStringContainsString('Procesando...', $fuente);
        $this->assertStringContainsString('wire:loading.delay', $fuente);
    }

    /**
     * Bug real reportado por el usuario (2026-09-22): los campos se veían
     * totalmente planos, sin borde ni relleno visible. Causa raíz: Tailwind
     * pone `border-width:0` en TODO por defecto (preflight) - una clase
     * `border-gray-300` (solo color) sin la utilidad `border` (ancho) no
     * dibuja ningún borde. Lo mismo pasa con `focus:ring-primary-500` (color)
     * sin `focus:ring-2` (ancho). Verifica que cada input/select tenga
     * explícitamente ambas utilidades de ancho, no solo las de color.
     */
    public function test_los_campos_tienen_ancho_de_borde_y_de_anillo_explicito(): void
    {
        $fuente = file_get_contents(resource_path('views/livewire/socializacion-rit.blade.php'));

        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bborder\b[^"]*border-gray-300[^"]*"/',
            $fuente,
            'Los campos deben tener la utilidad "border" (ancho), no solo "border-gray-300" (color).'
        );
        $this->assertMatchesRegularExpression(
            '/class="[^"]*focus:ring-2[^"]*focus:ring-primary-500[^"]*"/',
            $fuente,
            'Los campos deben tener "focus:ring-2" (ancho), no solo "focus:ring-primary-500" (color).'
        );
    }
}
