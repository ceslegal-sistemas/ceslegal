<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-08): las tarjetas de análisis del
 * modal "Emitir Sanción" tenían 3 avisos con iconos SVG planos de Heroicons
 * (análisis de pruebas, opciones sujetas a verificación, no recomienda
 * sanción) - se reemplazan por lord-icon, reutilizando los mismos que ya
 * usaba este archivo para no arriesgar un ID inválido.
 */
class EmitirSancionAnalisisSinSvgPlanoTest extends TestCase
{
    use RefreshDatabase;

    private function render(array $overrides = []): string
    {
        $data = array_merge([
            'analisis' => ['gravedad' => 'grave', 'justificacion' => 'Justificación de prueba.'],
            'recomendacion' => [],
            'opcionesSancion' => ['suspension' => 'Suspensión Laboral', 'no_sancion' => 'No Aplicar Sanción'],
            'iaSancionesRecomendadas' => ['suspension'],
            'modoDecision' => true,
        ], $overrides);

        return view('filament.components.emitir-sancion-analisis', $data)->render();
    }

    public function test_el_aviso_de_analisis_de_pruebas_usa_lord_icon_no_svg_plano(): void
    {
        $html = $this->render([
            'analisis' => ['gravedad' => 'grave', 'justificacion' => 'x', 'analisis_pruebas' => 'La incapacidad médica parece válida.'],
        ]);

        $this->assertStringContainsString('wpsdctqb.json', $html);
        // El path SVG viejo específico de este aviso ya no debe existir.
        $this->assertStringNotContainsString('M19.5 14.25v-2.625a3.375', $html);
    }

    public function test_el_aviso_condicional_usa_lord_icon_no_svg_plano(): void
    {
        $html = $this->render([
            'analisis' => ['gravedad' => 'grave', 'justificacion' => 'x', 'sancion_recomendada' => 'suspension'],
            'recomendacion' => ['estado_recomendacion' => 'condicionada', 'sancion_principal' => 'suspension'],
        ]);

        $this->assertStringContainsString('lltgvngb.json', $html);
        $this->assertStringNotContainsString('M12 9v3.75m9-.75a9', $html);
    }

    public function test_el_aviso_no_recomienda_sancion_usa_lord_icon_no_svg_plano(): void
    {
        $html = $this->render([
            'analisis' => ['gravedad' => 'leve', 'justificacion' => 'x'],
            'recomendacion' => ['mensaje_para_decision' => 'No hay elementos para sancionar.'],
            'iaSancionesRecomendadas' => [],
        ]);

        $this->assertStringContainsString('lvrxlmju.json', $html);
        // El botón "Aplicar: No Aplicar Sanción" SÍ conserva un heroicon
        // pequeño a propósito (icono de 15px dentro de un botón compacto,
        // no un icono de contenido) - lo que se verifica aquí es que el
        // icono GRANDE de 26px del aviso (el que se veía en la captura del
        // usuario) ya no existe, sin toparse con el del botón.
        $this->assertStringNotContainsString('width:26px;height:26px;color:#16a34a', $html);
    }
}
