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
     * Ancla en un string UNICO y ya verificado en el archivo real (la
     * cadena "@if($tiene)" aparece 2 veces en un lugar equivocado - dentro
     * de la fila de botones del header - por eso NO se usa como ancla aquí).
     * "Construir Reglamento Interno con IA" aparece una sola vez, cerca de
     * donde termina el bloque real del visor - el banner nuevo debe quedar
     * despues de esa linea.
     */
    public function test_el_banner_esta_despues_del_visor_del_rit_no_dentro_de_rit_actions(): void
    {
        $fuente = file_get_contents(resource_path('views/filament/pages/mi-reglamento-interno.blade.php'));

        $posicionAncla = strpos($fuente, 'Construir Reglamento Interno con IA');
        $posicionBanner = strpos($fuente, 'urlSocializacionRit');

        $this->assertNotFalse($posicionAncla, 'No se encontró el ancla esperada en el archivo.');
        $this->assertNotFalse($posicionBanner, 'No se encontró el banner nuevo.');
        $this->assertGreaterThan($posicionAncla, $posicionBanner, 'El banner de compartir debe estar DESPUÉS del bloque del visor del RIT, no dentro de la fila de botones .rit-actions.');
    }
}
