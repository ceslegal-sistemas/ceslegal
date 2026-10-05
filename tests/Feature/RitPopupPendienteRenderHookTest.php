<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El popup de RIT pendiente (App\Livewire\RitPopupPendiente) está montado
 * vía renderHook(BODY_END) en todo el panel 'empresa' - mismo mecanismo que
 * el widget de Chatwoot (ver ChatwootWidgetRenderHookTest).
 */
class RitPopupPendienteRenderHookTest extends TestCase
{
    use RefreshDatabase;

    public function test_aparece_en_el_panel_empresa_si_hay_rit_pendiente(): void
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

        $response = $this->actingAs($user)->get('/empresa');

        $response->assertSuccessful();
        $response->assertSee('Todavía faltan trabajadores por confirmar');
    }

    public function test_no_aparece_si_no_hay_nada_pendiente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $response = $this->actingAs($user)->get('/empresa');

        $response->assertSuccessful();
        $response->assertDontSee('Todavía faltan trabajadores por confirmar');
        $response->assertDontSee('Todavía no culminaste la socialización');
    }

    public function test_no_aparece_para_un_usuario_no_cliente(): void
    {
        $user = User::factory()->create(['role' => 'bufete', 'active' => true]);

        $response = $this->actingAs($user)->get('/empresa');

        $response->assertForbidden();
    }
}
