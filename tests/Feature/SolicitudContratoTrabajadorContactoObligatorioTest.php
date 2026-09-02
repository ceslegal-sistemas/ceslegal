<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\CreateSolicitudContrato;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pedido explícito del usuario: ningún punto de "registro rápido" de un
 * trabajador debe dejar teléfono/dirección por fuera de lo obligatorio -
 * este wizard registra un trabajador nuevo inline cuando no se usa uno
 * existente (ver SolicitudContratoResource, sección "Datos Personales del
 * Trabajador").
 */
class SolicitudContratoTrabajadorContactoObligatorioTest extends TestCase
{
    use RefreshDatabase;

    protected function usuario(): User
    {
        Permission::findOrCreate('create_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['create_solicitud::contrato', 'view_any_solicitud::contrato']);
        $this->actingAs($user);
        return $user;
    }

    public function test_trabajador_nuevo_exige_telefono_y_direccion(): void
    {
        $this->usuario();

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data._usar_trabajador_existente', false)
            ->set('data.trabajador_nombres', 'Juan')
            ->set('data.trabajador_apellidos', 'Pérez')
            ->set('data.trabajador_documento_tipo', 'CC')
            ->set('data.trabajador_documento_numero', '123')
            ->set('data.trabajador_email', 'juan@test.com')
            ->call('create')
            ->assertHasFormErrors(['trabajador_telefono' => 'required', 'trabajador_direccion' => 'required']);
    }

    /**
     * Caso borde real: un trabajador existente registrado ANTES de que
     * teléfono/dirección fueran obligatorios en TrabajadorResource puede
     * tener esos campos vacíos. La sección debe volver a mostrarse para que
     * el usuario los complete ahí mismo, en vez de fallar en un campo
     * oculto que no puede ver ni corregir.
     */
    public function test_trabajador_existente_sin_telefono_muestra_la_seccion_para_completarlo(): void
    {
        $this->usuario();
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '999',
            'genero' => 'masculino',
            'nombres' => 'Ana',
            'apellidos' => 'Gómez',
            'cargo' => 'Analista',
            'email' => 'ana@test.com',
            'telefono' => null,
            'direccion' => null,
            'active' => true,
        ]);

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data._usar_trabajador_existente', true)
            ->set('data.trabajador_id', $trabajador->id)
            ->assertFormFieldIsVisible('trabajador_telefono')
            ->assertFormFieldIsVisible('trabajador_direccion');
    }

    public function test_trabajador_existente_con_datos_completos_oculta_la_seccion(): void
    {
        $this->usuario();
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '999',
            'genero' => 'masculino',
            'nombres' => 'Ana',
            'apellidos' => 'Gómez',
            'cargo' => 'Analista',
            'email' => 'ana@test.com',
            'telefono' => '3001234567',
            'direccion' => 'Calle 1 # 2-3',
            'active' => true,
        ]);

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data._usar_trabajador_existente', true)
            ->set('data.trabajador_id', $trabajador->id)
            ->assertFormFieldIsHidden('trabajador_telefono')
            ->assertFormFieldIsHidden('trabajador_direccion');
    }
}
