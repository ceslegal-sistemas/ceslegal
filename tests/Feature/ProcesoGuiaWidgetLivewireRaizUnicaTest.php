<?php

namespace Tests\Feature;

use App\Filament\Admin\Widgets\ProcesoGuiaWidget;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mismo bug real que EmitirSancionPasosLivewireRaizUnicaTest, encontrado en
 * el mismo rediseño .rit-hero (2026-09-07): los Widgets de Filament SON
 * componentes Livewire reales - un @include('...lupe-hero-styles') (un
 * <style>) puesto ANTES del <div class="pg-wrap"> raíz rompe la detección
 * del componente. No causó un error visible aquí (este widget no tiene
 * wire:click propios), pero es el mismo defecto estructural - se prueba
 * igual para no depender de tener un botón que falle para notarlo.
 */
class ProcesoGuiaWidgetLivewireRaizUnicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_html_renderizado_tiene_un_unico_elemento_raiz(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($user);

        $html = Livewire::test(ProcesoGuiaWidget::class)->html();

        $primerTagAbierto = null;
        if (preg_match('/<\s*([a-z0-9]+)[^>]*>/i', ltrim($html), $m)) {
            $primerTagAbierto = strtolower($m[1]);
        }

        $this->assertSame('div', $primerTagAbierto, 'El primer elemento renderizado debe ser el <div class="pg-wrap"> raíz, no un <style> u otro hermano.');
    }
}
