<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EditarSolicitudContratoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug real reportado por el usuario con captura: "Editar" mostraba el
     * stepper nativo de Filament ADEMÁS del step-header de marca (Crear ya
     * no lo tenía desde antes), y el botón "Guardar cambios" aparecía
     * duplicado (el del wizard + el nativo de Filament por fuera).
     */
    public function test_editar_oculta_el_stepper_nativo_y_no_duplica_el_boton_guardar(): void
    {
        Permission::findOrCreate('update_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['update_solicitud::contrato', 'view_any_solicitud::contrato']);
        $this->actingAs($user);

        $empresa = Empresa::factory()->create(['active' => true]);
        $solicitud = SolicitudContrato::create([
            'empresa_id' => $empresa->id,
            'estado' => 'borrador',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123',
            'trabajador_email' => 'juan@test.com',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
        ]);

        $response = $this->get(SolicitudContratoResource::getUrl('edit', ['record' => $solicitud]))
            ->assertSuccessful()
            ->assertSee('ces-hide-wizard-steps', false);

        // El botón nativo de Filament ("Guardar cambios" generado por
        // getFormActions()) tiene el atributo wire:click="save" - si
        // getFormActions() sigue vacío, ese atributo no debe aparecer en el
        // HTML (el único "save" restante es el wire:submit="save" del
        // <form>, un atributo distinto).
        $response->assertDontSee('wire:click="save"', false);
    }
}
