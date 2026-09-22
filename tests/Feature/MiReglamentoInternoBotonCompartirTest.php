<?php

namespace Tests\Feature;

use Tests\TestCase;

class MiReglamentoInternoBotonCompartirTest extends TestCase
{
    public function test_incluye_el_banner_de_compartir_con_los_3_canales(): void
    {
        $fuente = file_get_contents(resource_path('views/filament/pages/mi-reglamento-interno.blade.php'));

        $this->assertStringContainsString('urlSocializacionRit', $fuente);
        $this->assertStringContainsString('navigator.clipboard.writeText', $fuente);
        $this->assertStringContainsString('wa.me', $fuente);
        $this->assertStringContainsString('mailto:', $fuente);
    }

    /**
     * Pedido explícito del usuario (2026-09-22): el banner debe verse ANTES
     * del texto del reglamento, para quedar visible sin scroll al entrar a
     * la página. "Texto del reglamento vigente" aparece una sola vez en el
     * archivo, justo en el header del visor.
     */
    public function test_el_banner_esta_antes_del_texto_del_reglamento_vigente(): void
    {
        $fuente = file_get_contents(resource_path('views/filament/pages/mi-reglamento-interno.blade.php'));

        $posicionBanner = strpos($fuente, 'urlSocializacionRit');
        $posicionAncla = strpos($fuente, 'Texto del reglamento vigente');

        $this->assertNotFalse($posicionBanner, 'No se encontró el banner nuevo.');
        $this->assertNotFalse($posicionAncla, 'No se encontró el ancla esperada en el archivo.');
        $this->assertLessThan($posicionAncla, $posicionBanner, 'El banner de compartir debe estar ANTES del texto del reglamento vigente.');
    }

    public function test_el_input_del_link_tiene_estilos_de_contraste_para_ambos_modos(): void
    {
        $fuente = file_get_contents(resource_path('views/filament/pages/mi-reglamento-interno.blade.php'));

        $this->assertStringContainsString('rit-link-input', $fuente);
        $this->assertMatchesRegularExpression('/\.rit-link-input\{[^}]*background:rgba\(255,255,255,\.06\)/', $fuente);
        $this->assertMatchesRegularExpression('/html:not\(\.dark\) \.rit-link-input\{[^}]*background:#fff/', $fuente);
    }
}
