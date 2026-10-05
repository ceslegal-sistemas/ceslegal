<?php

namespace Tests\Feature;

use App\Livewire\RitPopupPendiente;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Popup bloqueante de RIT pendiente al hacer login (pedido explícito de
 * Andrés Sarmiento, reunión 2026-09-28, action item de Juan Pablo
 * Prendón): "no puede decir que no lo vio" - reaparece una vez por sesión
 * de login mientras el estado siga pendiente.
 */
class RitPopupPendienteTest extends TestCase
{
    use RefreshDatabase;

    private function crearClienteConRitPendiente(): array
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '123456789',
            'genero' => 'masculino', 'nombres' => 'Juan', 'apellidos' => 'Perez', 'cargo' => 'Operario', 'active' => true,
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        return [$empresa, $user];
    }

    public function test_aparece_si_hay_rit_pendiente_en_fase_publicacion(): void
    {
        [$empresa, $user] = $this->crearClienteConRitPendiente();

        Livewire::actingAs($user)->test(RitPopupPendiente::class)
            ->assertSet('mostrar', true)
            ->assertSet('fase', 'publicacion')
            ->assertSee('Todavía faltan trabajadores por confirmar');
    }

    public function test_aparece_si_hay_rit_pendiente_en_fase_socializacion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(RitPopupPendiente::class)
            ->assertSet('mostrar', true)
            ->assertSet('fase', 'socializacion')
            ->assertSee('Todavía no culminaste la socialización');
    }

    public function test_no_aparece_si_no_hay_nada_pendiente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(RitPopupPendiente::class)
            ->assertSet('mostrar', false);
    }

    public function test_no_aparece_para_bufete(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'bufete', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(RitPopupPendiente::class)
            ->assertSet('mostrar', false);
    }

    public function test_ir_a_mi_reglamento_marca_la_sesion_y_redirige(): void
    {
        [$empresa, $user] = $this->crearClienteConRitPendiente();

        Livewire::actingAs($user)->test(RitPopupPendiente::class)
            ->call('irAMiReglamento')
            ->assertRedirect();

        $this->assertTrue((bool) session("rit_popup_pendiente_visto_{$empresa->id}"));
    }

    public function test_no_vuelve_a_aparecer_en_la_misma_sesion_tras_cerrarlo(): void
    {
        [$empresa, $user] = $this->crearClienteConRitPendiente();

        Livewire::actingAs($user)->test(RitPopupPendiente::class)
            ->call('irAMiReglamento');

        // Nueva instancia del componente (simula otra página del mismo
        // panel) - la sesión persiste entre instancias dentro del mismo
        // test, igual que entre cargas de página reales de la misma sesión.
        Livewire::actingAs($user)->test(RitPopupPendiente::class)
            ->assertSet('mostrar', false);
    }
}
