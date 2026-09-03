<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\ListSolicitudContratos;
use App\Models\Empresa;
use App\Models\ModificacionContractual;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Reemplaza el Wizard de página completa para "Solicitar un Cambio" por una
 * acción modal directa en la tabla de Historial de Contratos (y el mismo
 * modal en el botón de Ver Contrato) - a pedido explícito del usuario:
 * "prefiero mil veces que aparezca una acción en el listado del historial
 * de contratos que este wizard" (2026-09-02).
 */
class SolicitarCambioModalTest extends TestCase
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
            'estado' => 'aprobado',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123',
            'cargo_contrato' => 'Analista',
            'salario_propuesto' => '2000000',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2026-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
            'fecha_fin_contrato' => '2026-12-31',
        ], $overrides));
    }

    public function test_la_accion_solo_esta_visible_si_el_contrato_esta_aprobado(): void
    {
        $this->actingAsAutorizado();

        $aprobado = $this->crearSolicitud(['estado' => 'aprobado']);
        $borrador = $this->crearSolicitud(['estado' => 'borrador']);

        Livewire::test(ListSolicitudContratos::class)
            ->assertTableActionVisible('solicitarCambio', $aprobado)
            ->assertTableActionHidden('solicitarCambio', $borrador);
    }

    /**
     * 'plazo' es una plantilla literal (sin IA de por medio) - el modal solo
     * tiene el Paso "El Cambio" (el Paso "Revisar y Confirmar" se salta), y
     * el documento se genera de una sin ningún dato adicional.
     */
    public function test_solicitar_cambio_de_plazo_genera_el_documento_y_aplica_la_prorroga(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        Livewire::test(ListSolicitudContratos::class)
            ->callTableAction('solicitarCambio', $solicitud, data: [
                'tipo_modificacion' => 'plazo',
                'valor_nuevo' => '2027-06-30',
                'justificacion' => 'Se prorroga el contrato.',
                'fecha_efectiva' => '2027-01-01',
            ])
            ->assertHasNoTableActionErrors();

        $modificacion = ModificacionContractual::where('solicitud_contrato_id', $solicitud->id)->first();
        $this->assertNotNull($modificacion);
        $this->assertSame('otrosi_generado', $modificacion->estado);
        $this->assertNotNull($modificacion->ruta_otrosi);
        $this->assertNotEmpty($modificacion->texto_otrosi_redactado);

        $solicitud->refresh();
        $this->assertSame('2027-06-30', $solicitud->fecha_fin_contrato->format('Y-m-d'));
    }

    /**
     * Para los 4 tipos redactados con IA, el modal ya trae el texto revisado
     * (Paso "Revisar y Confirmar") en $data - crearYGenerarOtrosi() lo usa
     * tal cual, sin volver a llamar a la IA (evita una llamada HTTP real en
     * el test, y refleja el flujo real: el texto YA fue aprobado antes de
     * llegar aquí).
     */
    public function test_solicitar_cambio_de_salario_usa_el_texto_ya_revisado(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        Livewire::test(ListSolicitudContratos::class)
            ->callTableAction('solicitarCambio', $solicitud, data: [
                'tipo_modificacion' => 'salario',
                // stripCharacters('.') quita los puntos de miles al
                // dehidratar - se guarda '2500000', no '2.500.000'.
                'valor_nuevo' => '2.500.000',
                'justificacion' => 'Ajuste anual.',
                'fecha_efectiva' => '2026-10-01',
                'texto_otrosi_redactado' => '<p>Texto ya revisado por el usuario.</p>',
            ])
            ->assertHasNoTableActionErrors();

        $modificacion = ModificacionContractual::where('solicitud_contrato_id', $solicitud->id)->first();
        $this->assertNotNull($modificacion);
        $this->assertSame('2000000.00', $modificacion->valor_anterior);
        $this->assertSame('2500000', $modificacion->valor_nuevo);
        $this->assertStringContainsString('Texto ya revisado por el usuario.', $modificacion->texto_otrosi_redactado);
        $this->assertSame('otrosi_generado', $modificacion->estado);
        $this->assertNotNull($modificacion->ruta_otrosi);

        $solicitud->refresh();
        $this->assertSame('2500000.00', $solicitud->salario_propuesto);
    }
}
