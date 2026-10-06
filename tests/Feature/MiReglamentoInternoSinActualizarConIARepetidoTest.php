<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido explícito de Andrés Sarmiento (reunión 2026-09-28): "ese actualizar
 * reglamento interno con IA sólo debería existir la primera vez... los demás
 * deberían venir de manera automática [sugerencias de Biblioteca Legal] o
 * porque usted quiere subir un reglamento interno nuevo [botón Subir RIT]" -
 * una vez que ya existe un RIT, repetir el wizard de construcción no debe
 * ser una acción disponible.
 */
class MiReglamentoInternoSinActualizarConIARepetidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_muestra_actualizar_con_ia_cuando_ya_hay_un_rit_activo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia',
            'texto_completo' => 'v1', 'estado_generacion' => 'completado',
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertDontSee('Actualizar RIT con IA')
            // Las vías válidas de actualización siguen disponibles.
            ->assertSee('Subir RIT');
    }

    public function test_el_wizard_de_primera_vez_sigue_disponible_sin_rit(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertSee('Construir Reglamento Interno con IA')
            ->assertDontSee('Actualizar RIT con IA');
    }
}
