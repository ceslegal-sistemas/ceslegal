<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-07, con captura real): una vez los
 * 5 pasos de "Tu proceso" están en 'done', el timeline grande se queda fijo
 * en verde para siempre (el paso "Emitir sanción" nunca vuelve a 'pending'
 * aunque se acumulen sanciones nuevas por emitir) - se reemplaza por una
 * línea compacta, y la lista "Listos para sancionar" (que en la captura
 * tenía 16 filas estirando toda la página) ahora tiene scroll interno.
 */
class ProcesoGuiaCompactoTest extends TestCase
{
    use RefreshDatabase;

    private function guiaCon(array $pasos, array $listos = []): array
    {
        return [
            'estado' => 'ok',
            'empresa' => (object) ['razon_social' => 'RENBEL'],
            'pasos' => $pasos,
            'accion' => null,
            'listos' => $listos,
            'sancion_url' => '#',
        ];
    }

    public function test_muestra_el_timeline_grande_si_falta_algun_paso(): void
    {
        $guia = $this->guiaCon([
            ['clave' => 'cuenta', 'label' => 'Cuenta creada', 'estado' => 'done'],
            ['clave' => 'rit', 'label' => 'Reglamento Interno', 'estado' => 'current'],
        ]);

        $html = view('filament.widgets.proceso-guia', ['guia' => $guia])->render();

        $this->assertStringContainsString('class="pg-steps"', $html);
        $this->assertStringNotContainsString('Proceso configurado', $html);
    }

    public function test_muestra_la_linea_compacta_si_los_5_pasos_estan_done(): void
    {
        $guia = $this->guiaCon([
            ['clave' => 'cuenta', 'label' => 'Cuenta creada', 'estado' => 'done'],
            ['clave' => 'rit', 'label' => 'Reglamento Interno', 'estado' => 'done'],
            ['clave' => 'descargo', 'label' => 'Crear descargo', 'estado' => 'done'],
            ['clave' => 'diligencia', 'label' => 'Descargos del trabajador', 'estado' => 'done'],
            ['clave' => 'sancion', 'label' => 'Emitir sanción', 'estado' => 'done'],
        ]);

        $html = view('filament.widgets.proceso-guia', ['guia' => $guia])->render();

        $this->assertStringContainsString('Proceso configurado', $html);
        $this->assertStringNotContainsString('class="pg-steps"', $html);
    }

    public function test_la_lista_de_listos_para_sancionar_tiene_scroll_interno_y_contador(): void
    {
        $listos = array_map(fn($i) => ['trabajador' => "Trabajador {$i}", 'codigo' => "PD-2026-{$i}"], range(1, 16));
        $guia = $this->guiaCon([
            ['clave' => 'cuenta', 'label' => 'Cuenta creada', 'estado' => 'done'],
            ['clave' => 'rit', 'label' => 'Reglamento Interno', 'estado' => 'done'],
            ['clave' => 'descargo', 'label' => 'Crear descargo', 'estado' => 'done'],
            ['clave' => 'diligencia', 'label' => 'Descargos del trabajador', 'estado' => 'done'],
            ['clave' => 'sancion', 'label' => 'Emitir sanción', 'estado' => 'done'],
        ], $listos);

        $html = view('filament.widgets.proceso-guia', ['guia' => $guia])->render();

        $this->assertStringContainsString('pg-listos-scroll', $html);
        $this->assertStringContainsString('Listos para sancionar (16)', $html);
    }
}
