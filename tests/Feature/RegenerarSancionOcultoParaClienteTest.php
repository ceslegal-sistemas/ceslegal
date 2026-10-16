<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages\ListProcesoDisciplinarios;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pedido del usuario (2026-10-16): "Re-generar Sanción" reemplaza el
 * documento de sanción ya notificado al trabajador - solo el
 * bufete/abogado/super_admin debe poder hacerlo, no el cliente.
 */
class RegenerarSancionOcultoParaClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ProcesoDisciplinarioObserver registra en el timeline con
        // Auth::id() ?? 1 - el proceso se crea antes de autenticar al
        // usuario de prueba, así que necesita un usuario id=1 para la FK
        // (mismo patrón ya usado en otros tests de este recurso).
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function crearProcesoConSancionEmitida(Empresa $empresa): ProcesoDisciplinario
    {
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '111222333',
            'genero' => 'masculino', 'nombres' => 'Trabajador', 'apellidos' => 'De Prueba', 'cargo' => 'Operario',
            'email' => 'trabajador.prueba@test.com', 'active' => true,
        ]);

        return ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-REGEN',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
            'estado' => 'sancion_emitida',
        ]);
    }

    public function test_oculto_para_cliente(): void
    {
        Permission::findOrCreate('view_any_proceso::disciplinario', 'web');
        $empresa = Empresa::factory()->create(['active' => true]);
        $proceso = $this->crearProcesoConSancionEmitida($empresa);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $user->givePermissionTo('view_any_proceso::disciplinario');
        $this->actingAs($user);

        Livewire::test(ListProcesoDisciplinarios::class)
            ->assertTableActionHidden('regenerar_sancion', $proceso);
    }

    public function test_visible_para_super_admin(): void
    {
        Permission::findOrCreate('view_any_proceso::disciplinario', 'web');
        $empresa = Empresa::factory()->create(['active' => true]);
        $proceso = $this->crearProcesoConSancionEmitida($empresa);
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo('view_any_proceso::disciplinario');
        $this->actingAs($user);

        Livewire::test(ListProcesoDisciplinarios::class)
            ->assertTableActionVisible('regenerar_sancion', $proceso);
    }
}
