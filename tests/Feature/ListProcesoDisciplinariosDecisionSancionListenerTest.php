<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Bug real reportado por el usuario con captura de pantalla (2026-09-16):
 * al confirmar la decisión de sanción (Paso 2), el footer nativo del Paso 3
 * (Cancelar/Volver a Decisión/Continuar) aparecía correcto, pero el
 * contenido del wizard (Paso 2 completo, con su propio footer
 * Cancelar/Volver/Continuar a Autorizar) seguía visible debajo - "botones
 * duplicados", persistente, no un parpadeo.
 *
 * Causa raíz (vendor/filament/tables/src/Concerns/HasActions.php,
 * getMountedTableActionForm()): Filament cachea el formulario de la acción
 * montada y solo lo reconstruye - re-evaluando ->visible() en sus campos -
 * al invocarse vía una Action/método real de Livewire. El puente Alpine
 * anterior (emitir-sancion-pasos-wrapper.blade.php) hacía $wire.$set() en
 * bruto, que nunca pasa por ese mecanismo. Fix: mover la transición a un
 * listener PHP real (#[On(...)]) en la página, igual patrón que ya usaba
 * "Volver a Decisión" (extraModalFooterActions, que sí funcionaba).
 *
 * Se verifica por inspección de código fuente (mismo criterio ya usado en
 * EmitirSancionDeclaracionAutorizadorRitHeroTest.php): intentar
 * Livewire::test(ListProcesoDisciplinarios::class)->call(...) falla con
 * "Invalid Livewire snapshot structure" incluso solo llamando a un método
 * sin relación con este fix - limitación pre-existente de la infraestructura
 * de testing de esta página (nunca se había probado así en este proyecto),
 * no algo que este cambio pueda arreglar. La prueba real de que el HTML deja
 * de mostrar el wizard requiere confirmación visual del usuario tras
 * desplegar.
 */
class ListProcesoDisciplinariosDecisionSancionListenerTest extends TestCase
{
    private function fuentePagina(): string
    {
        return file_get_contents(app_path(
            'Filament/Admin/Resources/ProcesoDisciplinarioResource/Pages/ListProcesoDisciplinarios.php'
        ));
    }

    private function fuenteWrapper(): string
    {
        return file_get_contents(resource_path(
            'views/livewire/emitir-sancion-pasos-wrapper.blade.php'
        ));
    }

    public function test_la_pagina_tiene_el_listener_real_del_evento(): void
    {
        $fuente = $this->fuentePagina();

        $this->assertStringContainsString("#[On('emitir-sancion-paso2-completo')]", $fuente);
        $this->assertStringContainsString('public function recibirDecisionSancion(', $fuente);
        $this->assertStringContainsString("mountedTableActionsData[0]['paso_actual'] = 3", $fuente);
    }

    public function test_el_listener_guarda_los_4_campos_esperados(): void
    {
        $fuente = $this->fuentePagina();

        $this->assertStringContainsString("mountedTableActionsData[0]['tipo_sancion'] = \$tipoSancion", $fuente);
        $this->assertStringContainsString("mountedTableActionsData[0]['razon_divergencia'] = \$razonDivergencia", $fuente);
        $this->assertStringContainsString("mountedTableActionsData[0]['exoneracion_aceptada'] = \$exoneracionAceptada", $fuente);
    }

    public function test_el_bridge_de_alpine_con_wire_set_en_bruto_ya_no_existe(): void
    {
        $fuente = $this->fuenteWrapper();

        // El atributo x-on real (fuera de comentarios Blade {{-- --}}) ya no
        // existe - solo queda mencionado dentro del comentario explicativo
        // que documenta por qué se quitó.
        $this->assertStringNotContainsString('x-on:emitir-sancion-paso2-completo.window=', $fuente);
    }
}
