<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Bug real reportado por el usuario en producción (2026-09-16, tarde-noche,
 * mismo día del demo): al emitir una sanción, el confeti se cortaba a medio
 * segundo porque `redirect(static::getUrl('index'));` navegaba de inmediato
 * en la MISMA respuesta de Livewire. El parche de esa noche fue un
 * `setTimeout` antes de redirigir (commit 47bc5d4).
 *
 * Ese parche quedó OBSOLETO al mover generarYEnviarSancion()/
 * generarYEnviarConstanciaNoSancion() a GenerarYEnviarSancionJob (cola): los
 * 4 puntos que emiten una sanción ya no ejecutan la operación lenta de forma
 * síncrona, así que ya no hay nada que redirigir tras esperar - el usuario se
 * queda viendo la tabla, que muestra el estado vía el badge
 * `emision_sancion_estado` con poll(). Ver
 * docs/superpowers/specs/2026-09-18-generar-sancion-cola-asincrona-design.md.
 */
class EmitirSancionConfettiSinRedirectInmediatoTest extends TestCase
{
    public function test_solo_queda_1_redirect_inmediato_el_de_impugnacion_que_no_dispara_confeti(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ProcesoDisciplinarioResource.php'));

        // La resolución de impugnación cierra el proceso directo a 'cerrado'
        // - nunca pasa por 'sancion_emitida', no dispara confeti, nunca
        // necesitó el fix. Es el único bare-redirect que debe quedar.
        $ocurrencias = substr_count($fuente, "redirect(static::getUrl('index'));");

        $this->assertSame(1, $ocurrencias);
    }

    public function test_ya_no_queda_el_parche_de_setTimeout_porque_la_emision_es_asincrona(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ProcesoDisciplinarioResource.php'));

        // El parche de setTimeout+redirect ya no aplica: los 4 puntos que
        // emiten sanción ahora despachan GenerarYEnviarSancionJob y no
        // redirigen - no hay nada que "esperar" en la misma petición.
        $ocurrencias = substr_count($fuente, 'setTimeout(() => { window.location = ');

        $this->assertSame(0, $ocurrencias);
    }
}
