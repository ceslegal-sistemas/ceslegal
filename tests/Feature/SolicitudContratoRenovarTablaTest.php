<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\ListSolicitudContratos;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "Sí, renovar"/"No renovar" ahora también son acciones de fila en
 * Historial de Contratos (antes solo vivían en el header de Ver Contrato) -
 * a pedido explícito del usuario: quiere las acciones directamente en el
 * listado, sin tener que entrar al contrato primero.
 */
class SolicitudContratoRenovarTablaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-02'));

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
        ], $overrides));
    }

    public function test_visibles_dentro_de_la_ventana_de_45_dias(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud(['fecha_fin_contrato' => now()->addDays(20)->toDateString()]);

        Livewire::test(ListSolicitudContratos::class)
            ->assertTableActionVisible('renovarContrato', $solicitud)
            ->assertTableActionVisible('noRenovarContrato', $solicitud);
    }

    public function test_ocultas_fuera_de_la_ventana(): void
    {
        $this->actingAsAutorizado();
        $solicitud = $this->crearSolicitud(['fecha_fin_contrato' => now()->addDays(200)->toDateString()]);

        Livewire::test(ListSolicitudContratos::class)
            ->assertTableActionHidden('renovarContrato', $solicitud)
            ->assertTableActionHidden('noRenovarContrato', $solicitud);
    }
}
