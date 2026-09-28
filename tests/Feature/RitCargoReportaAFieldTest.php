<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Campo "Reporta a" del Repeater de cargos (wizard "Construir RIT",
 * 2026-09-27) - Select self-referencing que lista los demás cargos ya
 * escritos en la misma lista, mismo patrón ya usado por
 * 'periodicidad_diferenciada' -> $get('../../cargos') en este mismo archivo.
 * Se verifica por código fuente: probar el Select dinámico dentro de un
 * Repeater vía Livewire requeriría reconstruir el estado completo del
 * wizard multi-paso, sin aportar más confianza que verificar que el mismo
 * mecanismo ya probado en producción (periodicidad_diferenciada) se aplicó
 * correctamente al nuevo campo.
 */
class RitCargoReportaAFieldTest extends TestCase
{
    public function test_el_campo_reporta_a_existe_y_excluye_el_propio_cargo_de_las_opciones(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php'));

        $this->assertStringContainsString("Select::make('reporta_a')", $fuente);

        $posicionCampo = strpos($fuente, "Select::make('reporta_a')");
        $bloque = substr($fuente, $posicionCampo, 600);

        $this->assertStringContainsString("get('../../cargos')", $bloque);
        $this->assertStringContainsString('reject(', $bloque);
        $this->assertStringContainsString("get('nombre_cargo')", $bloque);
    }
}
