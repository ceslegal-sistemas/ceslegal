<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages\CreateProcesoDisciplinario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-25): el campo "Con copia a
 * (opcional)" debe validar formato de correo - si el texto no tiene un
 * "@"/formato válido, debe forzar la corrección en vez de dejarlo agregar
 * tal cual. Usa TagsInput::nestedRecursiveRules(['email']), el mecanismo
 * real de Filament v3 para validar cada elemento de un TagsInput (ver
 * Contracts\HasNestedRecursiveValidationRules).
 */
class ProcesoDisciplinarioCorreosCcValidacionTest extends TestCase
{
    use RefreshDatabase;

    private function autenticar(): User
    {
        Permission::findOrCreate('create_proceso::disciplinario', 'web');
        Permission::findOrCreate('view_any_proceso::disciplinario', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['create_proceso::disciplinario', 'view_any_proceso::disciplinario']);
        $this->actingAs($user);

        return $user;
    }

    public function test_rechaza_un_correo_sin_arroba_en_con_copia_a(): void
    {
        $this->autenticar();

        Livewire::test(CreateProcesoDisciplinario::class)
            ->set('data.correos_cc', ['no-es-un-correo'])
            ->call('create')
            ->assertHasFormErrors(['correos_cc.0' => 'email']);
    }

    public function test_no_marca_error_si_todos_los_correos_son_validos(): void
    {
        $this->autenticar();

        $errores = Livewire::test(CreateProcesoDisciplinario::class)
            ->set('data.correos_cc', ['jefe@example.com', 'rrhh@example.com'])
            ->call('create')
            ->instance()
            ->getErrorBag();

        $this->assertFalse($errores->has('correos_cc.0'));
        $this->assertFalse($errores->has('correos_cc.1'));
    }
}
