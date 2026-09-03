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
 * Dentro de la ventana de 45 días ("Sí, renovar"), el modal ya no pregunta
 * "¿Qué quiere cambiar?" - el contexto ya lo dice: es una prórroga de
 * plazo. Pedido explícito del usuario: "no es necesario que tenga que
 * seleccionar qué quiere cambiar cuando solo es plazo, ponerle un poco de
 * lógica" (2026-09-02).
 */
class SolicitarCambioRenovacionSimplificadaTest extends TestCase
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

    public function test_renueva_sin_preguntar_que_tipo_de_cambio_es(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        Livewire::test(ListSolicitudContratos::class)
            ->callTableAction('solicitarCambio', $solicitud, data: [
                'tipo_modificacion' => 'plazo',
                'valor_nuevo' => '2027-03-22',
                'fecha_efectiva' => '2026-09-23',
            ])
            ->assertHasNoTableActionErrors();

        $modificacion = ModificacionContractual::where('solicitud_contrato_id', $solicitud->id)->first();
        $this->assertNotNull($modificacion);
        $this->assertSame('plazo', $modificacion->tipo_modificacion);
        $this->assertSame('otrosi_generado', $modificacion->estado);

        $solicitud->refresh();
        $this->assertSame('2027-03-22', $solicitud->fecha_fin_contrato->format('Y-m-d'));
    }
}
