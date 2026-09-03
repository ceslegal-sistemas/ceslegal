<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\ListSolicitudContratos;
use App\Models\Empresa;
use App\Models\ModificacionContractual;
use App\Models\SolicitudContrato;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Mismo calculador años/meses/días de "Crear Solicitud de Contrato"
 * (Fieldset "Duración del Contrato") aplicado a "Sí, renovar" - pedido
 * explícito del usuario: "hagamos lo mismo como en la creación... y las
 * validaciones aplican para que no seleccionen fechas anteriores"
 * (2026-09-02). La duración se cuenta desde el vencimiento ACTUAL del
 * contrato, no desde una fecha de inicio nueva.
 */
class SolicitarCambioRenovacionDuracionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-02'));

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);

        Permission::findOrCreate('view_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        Permission::findOrCreate('update_solicitud::contrato', 'web');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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
            'fecha_fin_contrato' => '2026-09-22',
        ], $overrides));
    }

    public function test_duracion_en_meses_calcula_la_nueva_fecha(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        Livewire::test(ListSolicitudContratos::class)
            ->mountTableAction('solicitarCambio', $solicitud)
            ->set('mountedTableActionsData.0.renovacion_duracion_unidad', 'mes')
            ->set('mountedTableActionsData.0.renovacion_duracion_cantidad', 6)
            ->assertSet('mountedTableActionsData.0.valor_nuevo', '2027-03-22');
    }

    public function test_duracion_en_anios_mas_meses_calcula_la_nueva_fecha(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        Livewire::test(ListSolicitudContratos::class)
            ->mountTableAction('solicitarCambio', $solicitud)
            ->set('mountedTableActionsData.0.renovacion_duracion_unidad', 'anio')
            ->set('mountedTableActionsData.0.renovacion_duracion_cantidad', 1)
            ->set('mountedTableActionsData.0.renovacion_duracion_unidad_2', 'mes')
            ->set('mountedTableActionsData.0.renovacion_duracion_cantidad_2', 2)
            ->assertSet('mountedTableActionsData.0.valor_nuevo', '2027-11-22');
    }

    public function test_editar_la_fecha_directamente_descompone_la_duracion(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        Livewire::test(ListSolicitudContratos::class)
            ->mountTableAction('solicitarCambio', $solicitud)
            ->set('mountedTableActionsData.0.valor_nuevo', '2027-03-22')
            ->assertSet('mountedTableActionsData.0.renovacion_duracion_unidad', 'mes')
            ->assertSet('mountedTableActionsData.0.renovacion_duracion_cantidad', 6);
    }

    /**
     * No se puede "renovar" hacia atrás - la nueva fecha debe ser posterior
     * al vencimiento actual del contrato.
     */
    public function test_no_permite_una_fecha_anterior_o_igual_al_vencimiento_actual(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        Livewire::test(ListSolicitudContratos::class)
            ->callTableAction('solicitarCambio', $solicitud, data: [
                'tipo_modificacion' => 'plazo',
                'valor_nuevo' => '2026-09-22',
                'fecha_efectiva' => '2026-09-23',
            ])
            ->assertHasTableActionErrors(['valor_nuevo' => 'after']);

        $this->assertNull(ModificacionContractual::where('solicitud_contrato_id', $solicitud->id)->first());
    }
}
