<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-16, puntos 3 y 4 de
 * backlog-emitir-sancion-feedback-detallado-2026-09-16): los banners "Falta
 * elegir la sanción" y "Advertencia Legal - Decisión contraria..." quedaron
 * fuera del barrido de conversión a .rit-hero de una sesión anterior.
 */
class EmitirSancionFaltaElegirYAvisoRitHeroTest extends TestCase
{
    public function test_falta_elegir_la_sancion_usa_rit_hero(): void
    {
        $fuente = file_get_contents(resource_path('views/livewire/emitir-sancion-pasos.blade.php'));

        $this->assertStringContainsString('Falta elegir la sanción', $fuente);
        $this->assertStringContainsString('rit-badge-warning', $fuente);
        $this->assertStringNotContainsString('bg-amber-50 dark:bg-amber-900/20', $fuente);
    }

    public function test_advertencia_legal_usa_rit_hero(): void
    {
        $html = view('filament.components.emitir-sancion-exoneracion-aviso', [
            'tipoSeleccionado' => 'terminacion',
            'iaRazonesNoRecomendadas' => [],
        ])->render();

        $this->assertStringContainsString('rit-hero', $html);
        $this->assertStringContainsString('rit-badge-danger', $html);
        $this->assertStringContainsString('Decisión contraria a la recomendación jurídica', $html);
        $this->assertStringNotContainsString('border-left:3px solid #f87171', $html);
    }

    public function test_advertencia_legal_sigue_mostrando_la_razon_especifica(): void
    {
        $html = view('filament.components.emitir-sancion-exoneracion-aviso', [
            'tipoSeleccionado' => 'terminacion',
            'iaRazonesNoRecomendadas' => ['terminacion' => 'La falta no es lo suficientemente grave.'],
        ])->render();

        $this->assertStringContainsString('Por qué la IA no recomienda «Terminación de Contrato»', $html);
        $this->assertStringContainsString('La falta no es lo suficientemente grave.', $html);
    }
}
