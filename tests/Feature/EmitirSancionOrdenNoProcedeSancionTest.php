<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Bug real reportado con captura de pantalla (2026-09-16): cuando la
 * recomendación de la IA es "No Aplicar Sanción" ($sancionesValidas vacío),
 * la tarjeta verde "La IA no recomienda sanción" (la recomendación REAL)
 * aparecía DESPUÉS de "Otras sanciones - no recomendadas por la IA", dando
 * la impresión de que la recomendación real quedaba al final. Se movió antes,
 * igual que la Tarjeta 2 "La IA recomienda" ya aparecía primero cuando sí
 * hay una sanción punitiva recomendada.
 */
class EmitirSancionOrdenNoProcedeSancionTest extends TestCase
{
    public function test_la_ia_no_recomienda_sancion_aparece_antes_que_otras_sanciones(): void
    {
        $html = view('filament.components.emitir-sancion-analisis', [
            'analisis' => ['gravedad' => 'grave', 'justificacion' => 'x'],
            'recomendacion' => ['sanciones_sugeridas' => []],
            'opcionesSancion' => [
                'llamado_atencion' => 'Llamado de Atención',
                'suspension' => 'Suspensión Laboral',
                'terminacion' => 'Terminación de Contrato',
                'no_sancion' => 'No Aplicar Sanción',
            ],
            'iaSancionesRecomendadas' => [],
            'modoDecision' => true,
        ])->render();

        $posicionNoRecomienda = strpos($html, 'La IA no recomienda sanción');
        $posicionOtrasSanciones = strpos($html, 'Otras sanciones - no recomendadas por la IA');

        $this->assertNotFalse($posicionNoRecomienda, 'No se encontró la tarjeta "La IA no recomienda sanción".');
        $this->assertNotFalse($posicionOtrasSanciones, 'No se encontró la sección "Otras sanciones".');
        $this->assertLessThan(
            $posicionOtrasSanciones,
            $posicionNoRecomienda,
            '"La IA no recomienda sanción" debe aparecer ANTES que "Otras sanciones".'
        );
    }
}
