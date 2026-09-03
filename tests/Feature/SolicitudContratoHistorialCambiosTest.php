<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Models\Empresa;
use App\Models\ModificacionContractual;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SolicitudContratoHistorialCambiosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('view_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        Permission::findOrCreate('update_solicitud::contrato', 'web');
    }

    private function actingAsAutorizado(): User
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_solicitud::contrato', 'view_any_solicitud::contrato', 'update_solicitud::contrato']);
        $this->actingAs($user);

        return $user;
    }

    private function crearSolicitud(array $overrides = []): SolicitudContrato
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        return SolicitudContrato::create(array_merge([
            'empresa_id' => $empresa->id,
            'estado' => 'aprobado',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2026-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
        ], $overrides));
    }

    public function test_muestra_mensaje_vacio_sin_cambios_previos(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        $this->get(SolicitudContratoResource::getUrl('view', ['record' => $solicitud]))
            ->assertSuccessful()
            ->assertSee('Este contrato no ha tenido cambios formales.');
    }

    public function test_lista_los_cambios_ya_aplicados(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        ModificacionContractual::create([
            'solicitud_contrato_id' => $solicitud->id,
            'tipo_modificacion' => 'salario',
            'valor_anterior' => '2000000',
            'valor_nuevo' => '2500000',
            'fecha_efectiva' => '2026-03-15',
            'estado' => 'otrosi_generado',
        ]);

        $this->get(SolicitudContratoResource::getUrl('view', ['record' => $solicitud]))
            ->assertSuccessful()
            ->assertSee('Historial de Cambios')
            ->assertSee('Salario')
            ->assertSee('2000000')
            ->assertSee('2500000')
            ->assertDontSee('Este contrato no ha tenido cambios formales.');
    }

    public function test_boton_solicitar_cambio_visible_solo_si_aprobado(): void
    {
        $this->actingAsAutorizado();

        $aprobado = $this->crearSolicitud(['estado' => 'aprobado']);
        $this->get(SolicitudContratoResource::getUrl('view', ['record' => $aprobado]))
            ->assertSee('Solicitar un Cambio');

        $borrador = $this->crearSolicitud(['estado' => 'borrador']);
        $this->get(SolicitudContratoResource::getUrl('view', ['record' => $borrador]))
            ->assertDontSee('Solicitar un Cambio');
    }
}
