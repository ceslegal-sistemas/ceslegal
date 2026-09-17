<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Bug real reportado por el usuario en producción (2026-09-16, tarde-noche,
 * mismo día del demo): al emitir una sanción, el confeti (LogroDescargosService
 * ::celebrar(), disparado por ProcesoDisciplinarioObserver al guardar el
 * estado 'sancion_emitida') se cortaba a medio segundo porque
 * `redirect(static::getUrl('index'));` navegaba de inmediato en la MISMA
 * respuesta de Livewire - confirmado técnicamente: Livewire reemplaza el
 * binding global 'redirect' por uno propio que agrega el destino como
 * "efecto" de la respuesta sin necesitar `return` (ver
 * SupportRedirects::dehydrate() en el vendor de Livewire).
 *
 * Fix: reemplazar el redirect inmediato por un `setTimeout` vía
 * `$action->getLivewire()->js(...)`, dando tiempo a la animación.
 */
class EmitirSancionConfettiSinRedirectInmediatoTest extends TestCase
{
    public function test_solo_queda_1_redirect_inmediato_el_de_impugnacion_que_no_dispara_confeti(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ProcesoDisciplinarioResource.php'));

        // Antes había 5 ocurrencias de redirect(static::getUrl('index')) sin
        // return - Livewire lo aplica igual como "efecto" de la respuesta (ver
        // SupportRedirects::dehydrate() en el vendor), llegando junto con el
        // confeti y cortándolo a medio segundo. Los 4 puntos que SÍ emiten una
        // sanción (y disparan el confeti vía el observer al guardar
        // 'sancion_emitida') ahora retrasan la navegación. Solo queda 1 sin
        // tocar: la resolución de impugnación, que cierra el proceso
        // directamente a 'cerrado' - nunca pasa por 'sancion_emitida', así que
        // no dispara confeti y no necesitaba el fix.
        $ocurrencias = substr_count($fuente, "redirect(static::getUrl('index'));");

        $this->assertSame(1, $ocurrencias);
    }

    public function test_el_redirect_retrasado_via_js_esta_presente_4_veces(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ProcesoDisciplinarioResource.php'));

        // Cuenta específicamente el patrón setTimeout(...window.location...) que
        // reemplaza al redirect inmediato - no el conteo genérico de
        // getLivewire()->js(), que también incluye el listener 'modal-closed'
        // ya existente desde antes (sin relación con este bug).
        $ocurrencias = substr_count($fuente, "setTimeout(() => { window.location = ");

        $this->assertSame(4, $ocurrencias, 'Se esperaban los 4 puntos: constancia sin sanción, emitir sanción directo, confirmar días de suspensión y re-generar sanción.');
    }
}
