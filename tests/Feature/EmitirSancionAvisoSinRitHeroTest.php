<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-16): unificar el aviso "Empresa sin
 * Reglamento Interno de Trabajo" (dentro del modal Emitir Sanción) al
 * lenguaje visual .rit-hero, igual que el resto del modal.
 */
class EmitirSancionAvisoSinRitHeroTest extends TestCase
{
    public function test_el_aviso_sin_rit_usa_rit_hero(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ProcesoDisciplinarioResource.php'));

        $this->assertStringContainsString("Placeholder::make('aviso_sin_rit')", $fuente);
        $this->assertStringContainsString('Esta empresa no tiene RIT', $fuente);
        $this->assertStringContainsString('rit-hero', $fuente);
        $this->assertStringNotContainsString('bg-amber-50 dark:bg-amber-900/20', $fuente);
    }
}
