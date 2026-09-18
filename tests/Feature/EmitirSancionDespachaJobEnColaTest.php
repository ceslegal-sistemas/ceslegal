<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Los 4 puntos de ProcesoDisciplinarioResource que emiten una sanción (o la
 * constancia de no sanción) ya no llaman a DocumentGeneratorService de forma
 * síncrona - despachan GenerarYEnviarSancionJob. Test de inspección de fuente
 * (mismo patrón que EmitirSancionConfettiSinRedirectInmediatoTest): ejercer
 * las 4 Table Actions vía Livewire::test() requeriría reconstruir todo el
 * wizard de Emitir Sanción, ya cubierto en otros tests de este mismo
 * recurso - aquí solo se verifica que el código fuente real ya no invoca el
 * servicio lento directamente en ninguno de los 4 puntos.
 */
class EmitirSancionDespachaJobEnColaTest extends TestCase
{
    public function test_ningun_punto_llama_directo_al_servicio_lento(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ProcesoDisciplinarioResource.php'));

        $this->assertSame(0, substr_count($fuente, '->generarYEnviarSancion('));
        $this->assertSame(0, substr_count($fuente, '->generarYEnviarConstanciaNoSancion('));
    }

    public function test_los_4_puntos_despachan_el_job(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ProcesoDisciplinarioResource.php'));

        $this->assertSame(
            4,
            substr_count($fuente, '\App\Jobs\GenerarYEnviarSancionJob::dispatch('),
            'Se esperan los 4 puntos: constancia sin sanción, emitir sanción directo, confirmar días de suspensión y re-generar sanción.'
        );
    }
}
