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

    /**
     * Bug real reportado por el usuario (2026-09-25): "superadmin@ceslegal.co,
     * jprendon@gmail.com" quedaba como UN solo tag en vez de dos - splitKeys
     * por defecto de Filament TagsInput es [], así que la coma no separaba
     * nada (el JS de tags-input.js solo confirma un tag con la tecla Enter o
     * con las teclas de splitKeys, ver vendor/filament/forms/resources/js/
     * components/tags-input.js). El texto de ayuda ya decía "presione Enter
     * o coma" pero el código nunca lo hacía. No se puede probar el
     * comportamiento real de Alpine.js con PHPUnit, así que se verifica que
     * el campo esté configurado correctamente en el código fuente.
     */
    public function test_el_campo_separa_tags_con_coma(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ProcesoDisciplinarioResource/Pages/CreateProcesoDisciplinario.php'));

        $posicionCampo = strpos($fuente, "TagsInput::make('correos_cc')");
        $posicionSplit = strpos($fuente, "->splitKeys([','])", $posicionCampo ?: 0);

        $this->assertNotFalse($posicionCampo, 'No se encontró el campo correos_cc.');
        $this->assertNotFalse($posicionSplit, 'El campo correos_cc debe tener ->splitKeys([\',\']) para que la coma separe tags.');
        $this->assertLessThan($posicionSplit - $posicionCampo, 500, '->splitKeys no está cerca de la definición de correos_cc.');
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
