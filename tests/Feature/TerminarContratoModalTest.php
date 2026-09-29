<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\ListSolicitudContratos;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\TerminacionContrato;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "Terminar Contrato" (justa causa/Art. 62 CST, sin justa causa +
 * indemnización Art. 64 CST) - mismo patrón de acción modal en la tabla de
 * Historial de Contratos que "Solicitar un Cambio" (ver
 * SolicitarCambioModalTest.php), nunca página completa.
 */
class TerminarContratoModalTest extends TestCase
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
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(100000000, 999999999),
            'genero' => 'masculino',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'cargo' => 'Analista',
            'active' => true,
        ]);

        return SolicitudContrato::create(array_merge([
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'estado' => 'aprobado',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => $trabajador->numero_documento,
            'cargo_contrato' => 'Analista',
            'salario_propuesto' => '3000000',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2024-01-01',
            'fecha_inicio_periodo_actual' => '2024-01-01',
            'fecha_fin_contrato' => '2026-12-31',
        ], $overrides));
    }

    public function test_la_accion_solo_esta_visible_para_los_4_tipos_laborales_aprobados(): void
    {
        $this->actingAsAutorizado();

        $fijoAprobado = $this->crearSolicitud();
        $fijoBorrador = $this->crearSolicitud(['estado' => 'borrador']);
        $prestacionServicios = $this->crearSolicitud(['tipo_contrato' => 'Contrato de Prestación de Servicios']);

        Livewire::test(ListSolicitudContratos::class)
            ->assertTableActionVisible('terminarContrato', $fijoAprobado)
            ->assertTableActionHidden('terminarContrato', $fijoBorrador)
            ->assertTableActionHidden('terminarContrato', $prestacionServicios);
    }

    public function test_terminar_con_justa_causa_no_calcula_indemnizacion(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud();

        Livewire::test(ListSolicitudContratos::class)
            ->callTableAction('terminarContrato', $solicitud, data: [
                'tipo' => 'con_justa_causa',
                'motivo' => 'Incumplimiento reiterado de sus funciones.',
                // Relativa a hoy (no un string fijo): el DatePicker exige
                // minDate(today()) - una fecha fija se vuelve invalida con
                // el paso del tiempo (bug real encontrado 2026-09-29).
                'fecha_terminacion' => now()->addDays(5)->format('Y-m-d'),
            ])
            ->assertHasNoTableActionErrors();

        $terminacion = TerminacionContrato::where('solicitud_contrato_id', $solicitud->id)->first();
        $this->assertNotNull($terminacion);
        $this->assertNull($terminacion->monto_indemnizacion);
        $this->assertSame('terminado', $solicitud->fresh()->estado);
        $this->assertFalse($solicitud->trabajador->fresh()->active);
    }

    public function test_terminar_sin_justa_causa_calcula_y_registra_la_indemnizacion(): void
    {
        $this->actingAsAutorizado();
        // Relativas a hoy y exactamente 30 dias de diferencia (lo que el
        // test verifica abajo) - fechas fijas se vuelven invalidas con el
        // paso del tiempo por el minDate(today()) del DatePicker (bug real
        // encontrado 2026-09-29).
        $fechaTerminacion = now()->addDays(5);
        $solicitud = $this->crearSolicitud([
            'fecha_fin_contrato' => $fechaTerminacion->copy()->addDays(30)->format('Y-m-d'),
        ]);

        Livewire::test(ListSolicitudContratos::class)
            ->callTableAction('terminarContrato', $solicitud, data: [
                'tipo' => 'sin_justa_causa',
                'fecha_terminacion' => $fechaTerminacion->format('Y-m-d'),
            ])
            ->assertHasNoTableActionErrors();

        $terminacion = TerminacionContrato::where('solicitud_contrato_id', $solicitud->id)->first();
        $this->assertNotNull($terminacion);
        $this->assertSame(30, $terminacion->dias_indemnizacion);
        $this->assertNotNull($terminacion->ruta_documento);
    }
}
