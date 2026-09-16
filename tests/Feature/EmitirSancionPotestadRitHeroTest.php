<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-16): la tarjeta "Quién puede
 * autorizar según el RIT" usa el mismo lenguaje visual .rit-hero que el
 * resto del modal Emitir Sanción - los puntos de color por tipo de sanción
 * (dot) se conservan, son funcionales, no decorativos.
 */
class EmitirSancionPotestadRitHeroTest extends TestCase
{
    private function render(array $autoridadRit, array $opcionesSancion): string
    {
        return view('filament.components.emitir-sancion-potestad', [
            'autoridadRit' => $autoridadRit,
            'opcionesSancion' => $opcionesSancion,
        ])->render();
    }

    public function test_usa_rit_hero_y_muestra_las_filas_por_tipo_de_sancion(): void
    {
        $html = $this->render(
            ['suspension' => 'Gerente de RRHH'],
            ['llamado_atencion' => 'x', 'suspension' => 'x', 'terminacion' => 'x'],
        );

        $this->assertStringContainsString('rit-hero', $html);
        $this->assertStringContainsString('Quién puede autorizar según el RIT', $html);
        $this->assertStringContainsString('Gerente de RRHH', $html);
        $this->assertStringContainsString('No especificado en el RIT', $html);
    }

    public function test_muestra_aviso_cuando_el_rit_no_detalla_ninguna_potestad(): void
    {
        $html = $this->render([], ['suspension' => 'x']);

        $this->assertStringContainsString('El RIT no detalla potestades disciplinarias', $html);
    }
}
