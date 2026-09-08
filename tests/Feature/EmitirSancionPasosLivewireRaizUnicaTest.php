<?php

namespace Tests\Feature;

use App\Livewire\EmitirSancionPasos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bug real de producción (2026-09-07): el rediseño .rit-hero del modal
 * "Emitir Sanción" puso un @include('...lupe-hero-styles') (un <style>)
 * ANTES del <div> raíz de este componente Livewire real
 * (App\Livewire\EmitirSancionPasos) - Livewire exige un único elemento
 * raíz para poder identificar el componente en el cliente. El síntoma real
 * fue "MethodNotFoundException: Public method [irAPaso2] not found" al
 * hacer clic en "Continuar a Decisión". Fix: el @include va DENTRO del
 * único <div> raíz.
 */
class EmitirSancionPasosLivewireRaizUnicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_irapaso2_avanza_al_paso_2_sin_error(): void
    {
        Livewire::test(EmitirSancionPasos::class, ['procesoId' => 1])
            ->assertSet('paso', 1)
            ->call('irAPaso2')
            ->assertSet('paso', 2);
    }

    public function test_el_html_renderizado_tiene_un_unico_elemento_raiz(): void
    {
        $html = Livewire::test(EmitirSancionPasos::class, ['procesoId' => 1])->html();

        // El wire:id (o wire:snapshot, según versión de Livewire) debe estar
        // en el <div class="rit-hero...">, nunca en el <style> - si el
        // <style> quedara como hermano ANTES del root, Livewire lo tomaría
        // a él como "primer elemento" y el div real quedaría huérfano.
        $primerTagAbierto = null;
        if (preg_match('/<\s*([a-z0-9]+)[^>]*>/i', ltrim($html), $m)) {
            $primerTagAbierto = strtolower($m[1]);
        }

        $this->assertSame('div', $primerTagAbierto, 'El primer elemento renderizado debe ser el <div> raíz, no un <style> u otro hermano.');
    }
}
