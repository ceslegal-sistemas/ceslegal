<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-10): los botones "Aplicar esta
 * sancion" de $sancionMeta (compartidos entre "La IA recomienda" y "Otras
 * sanciones") pasan de heroicon a lord-icon, por consistencia entre ambas
 * secciones - esto reemplaza deliberadamente la decision documentada en
 * EmitirSancionAnalisisSinSvgPlanoTest.php sobre mantener un heroicon
 * pequeno en estos botones. El boton "No Aplicar Sancion" (linea ~635) NO
 * usa $sancionMeta - es un cambio de codigo aparte, verificado en el spec.
 */
class EmitirSancionBotonesSancionLordIconTest extends TestCase
{
    use RefreshDatabase;

    private function render(array $overrides = []): string
    {
        $data = array_merge([
            'analisis' => ['gravedad' => 'grave', 'justificacion' => 'x'],
            'recomendacion' => ['sancion_principal' => 'suspension'],
            'opcionesSancion' => [
                'llamado_atencion' => 'Llamado de Atención',
                'suspension' => 'Suspensión Laboral',
                'multa' => 'Multa',
                'terminacion' => 'Terminación de Contrato',
                'no_sancion' => 'No Aplicar Sanción',
            ],
            'iaSancionesRecomendadas' => ['suspension'],
            'modoDecision' => true,
        ], $overrides);

        return view('filament.components.emitir-sancion-analisis', $data)->render();
    }

    public function test_boton_de_la_ia_recomienda_usa_lord_icon(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('uphbloed.json', $html);
    }

    public function test_botones_de_otras_sanciones_usan_lord_icon(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('jdgfsfzr.json', $html);
        $this->assertStringContainsString('hmpomorl.json', $html);
    }

    public function test_boton_no_aplicar_sancion_en_otras_sanciones_usa_lord_icon(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('lvrxlmju.json', $html);
    }

    public function test_boton_no_aplicar_sancion_en_bloque_no_procede_usa_lord_icon(): void
    {
        $html = $this->render(['iaSancionesRecomendadas' => []]);

        $this->assertStringContainsString('lvrxlmju.json', $html);
    }

    public function test_ya_no_hay_svg_plano_de_heroicon_en_los_botones_de_sancion(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('M12 6v6h4.5m4.5 0a9 9', $html);
    }
}
