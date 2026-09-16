<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-10): la tarjeta de garantías/debido
 * proceso usa el mismo lenguaje visual .rit-hero que "Logros de
 * cumplimiento" del Dashboard - pero envolviendo SOLO esta tarjeta, no todo
 * el modal.
 */
class EmitirSancionGarantiasRitHeroTest extends TestCase
{
    use RefreshDatabase;

    private function render(array $garantias): string
    {
        return view('filament.components.emitir-sancion-analisis', [
            'analisis' => ['gravedad' => 'leve', 'justificacion' => 'x', 'verificacion_garantias' => $garantias],
            'recomendacion' => [],
            'opcionesSancion' => ['no_sancion' => 'No Aplicar Sanción'],
            'iaSancionesRecomendadas' => [],
            'modoDecision' => true,
        ])->render();
    }

    public function test_usa_rit_hero_con_badge_de_peligro_cuando_hay_riesgo(): void
    {
        $html = $this->render([
            'proporcionalidad' => ['estado' => 'riesgo', 'nota' => 'la sancion parece excesiva'],
        ]);

        $this->assertStringContainsString('rit-hero', $html);
        $this->assertStringContainsString('rit-badge-danger', $html);
        $this->assertStringContainsString('lltgvngb.json', $html);
    }

    public function test_usa_badge_verde_cuando_todas_las_garantias_cumplen(): void
    {
        $html = $this->render([
            'proporcionalidad' => ['estado' => 'cumple'],
        ]);

        $this->assertStringContainsString('rit-hero', $html);
        $this->assertStringContainsString('rit-badge-sub', $html);
        $this->assertStringContainsString('Todas las garantías se cumplen', $html);
    }

    public function test_incluye_los_estilos_compartidos_del_hero(): void
    {
        $html = $this->render(['proporcionalidad' => ['estado' => 'cumple']]);

        $this->assertStringContainsString('.rit-orb-b', $html);
        $this->assertStringContainsString('.rit-overlay', $html);
    }
}
