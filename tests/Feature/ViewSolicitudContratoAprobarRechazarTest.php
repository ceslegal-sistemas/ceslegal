<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\ViewSolicitudContrato;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "Aprobar"/"Rechazar" en la página "Ver" (2026-09-28, pedido explícito del
 * usuario): antes solo existían como Table Actions en el listado - al
 * entrar a "Ver" el cliente solo veía "Editar" y tenía que volver al
 * listado para decidir sobre un borrador. Mismo cuerpo que las Table
 * Actions de SolicitudContratoResource::table() (ver SolicitarCambioModalTest
 * para el patrón de permisos ya establecido).
 */
class ViewSolicitudContratoAprobarRechazarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);

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
        ], $overrides));
    }

    public function test_aprobar_y_rechazar_visibles_solo_en_borrador(): void
    {
        $this->actingAsAutorizado();

        $borrador = $this->crearSolicitud(['estado' => 'borrador']);
        $aprobado = $this->crearSolicitud(['estado' => 'aprobado']);

        Livewire::test(ViewSolicitudContrato::class, ['record' => $borrador->getRouteKey()])
            ->assertActionVisible('aprobar')
            ->assertActionVisible('rechazar');

        Livewire::test(ViewSolicitudContrato::class, ['record' => $aprobado->getRouteKey()])
            ->assertActionHidden('aprobar')
            ->assertActionHidden('rechazar');
    }

    public function test_aprobar_genera_el_contrato_final_y_actualiza_el_estado(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        Livewire::test(ViewSolicitudContrato::class, ['record' => $solicitud->getRouteKey()])
            ->callAction('aprobar')
            ->assertHasNoActionErrors();

        $this->assertSame('aprobado', $solicitud->fresh()->estado);
    }

    public function test_rechazar_exige_motivo_y_actualiza_el_estado(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        Livewire::test(ViewSolicitudContrato::class, ['record' => $solicitud->getRouteKey()])
            ->callAction('rechazar', data: ['motivo' => 'El cargo propuesto no está aprobado en presupuesto.'])
            ->assertHasNoActionErrors();

        $solicitud->refresh();
        $this->assertSame('rechazado', $solicitud->estado);
        $this->assertSame('El cargo propuesto no está aprobado en presupuesto.', $solicitud->motivo_rechazo);
    }
}
