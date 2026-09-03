<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\ViewSolicitudContrato;
use App\Models\Empresa;
use App\Models\ModificacionContractual;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Mismo modal "Solicitar un Cambio" de la tabla de Historial de Contratos
 * (SolicitarCambioModalTest), pero desde el botón del header en "Ver
 * Contrato" - el usuario pidió que ambos lugares usen el mismo modal, en
 * vez del wizard de página completa.
 */
class SolicitarCambioHeaderActionTest extends TestCase
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

    private function crearSolicitud(): SolicitudContrato
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        return SolicitudContrato::create([
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
            'fecha_fin_contrato' => '2026-12-31',
        ]);
    }

    public function test_genera_el_otrosi_desde_el_boton_de_ver_contrato(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_solicitud::contrato', 'view_any_solicitud::contrato', 'update_solicitud::contrato']);
        $this->actingAs($user);

        $solicitud = $this->crearSolicitud();

        Livewire::test(ViewSolicitudContrato::class, ['record' => $solicitud->getRouteKey()])
            ->callAction('solicitarCambio', data: [
                'tipo_modificacion' => 'plazo',
                'valor_nuevo' => '2027-06-30',
                'justificacion' => 'Se prorroga el contrato.',
                'fecha_efectiva' => '2027-01-01',
            ])
            ->assertHasNoActionErrors();

        $modificacion = ModificacionContractual::where('solicitud_contrato_id', $solicitud->id)->first();
        $this->assertNotNull($modificacion);
        $this->assertSame('otrosi_generado', $modificacion->estado);
        $this->assertNotNull($modificacion->ruta_otrosi);
    }
}
