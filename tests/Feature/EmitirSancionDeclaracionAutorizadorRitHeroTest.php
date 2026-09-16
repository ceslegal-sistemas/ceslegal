<?php

namespace Tests\Feature;

use Tests\TestCase;

class EmitirSancionDeclaracionAutorizadorRitHeroTest extends TestCase
{
    public function test_el_placeholder_declaracion_texto_usa_rit_hero(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ProcesoDisciplinarioResource.php'));

        $this->assertStringContainsString("Placeholder::make('declaracion_texto')", $fuente);
        $this->assertStringContainsString('rit-hero', $fuente);
        $this->assertStringContainsString('Confirme su autorización', $fuente);
        $this->assertStringNotContainsString('--decl-bg', $fuente);
    }
}
