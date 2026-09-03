<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardContratosPorVencerNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-02'));

        // SolicitudContratoObserver registra en el timeline con
        // Auth::id() ?? 1 - sin sesión activa (o antes de actingAs) necesita
        // que exista un usuario con id=1 para la FK.
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);

        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        Role::findOrCreate('cliente', 'web');
    }

    /**
     * En producción el permiso se asigna vía RolePermissionSeeder al crear
     * el rol 'cliente' (o al editarlo desde el panel de administración) -
     * acá se simula ese estado real, no solo la columna 'role'.
     */
    private function crearClienteConPermisoDeContratos(Empresa $empresa): User
    {
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $user->assignRole('cliente');
        $user->givePermissionTo('view_any_solicitud::contrato');

        return $user;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearSolicitud(Empresa $empresa, array $overrides = []): SolicitudContrato
    {
        return SolicitudContrato::create(array_merge([
            'empresa_id' => $empresa->id,
            'tipo_contrato' => 'Contrato a Término Fijo',
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123456',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2026-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
        ], $overrides));
    }

    public function test_muestra_el_banner_con_un_contrato_en_ventana_de_alerta(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->crearSolicitud($empresa, ['fecha_fin_contrato' => now()->addDays(30)->toDateString()]);
        $user = $this->crearClienteConPermisoDeContratos($empresa);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Tiene un contrato a término fijo por vencer');
    }

    public function test_no_muestra_el_banner_sin_contratos_por_vencer(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->crearSolicitud($empresa, ['fecha_fin_contrato' => now()->addDays(200)->toDateString()]);
        $user = $this->crearClienteConPermisoDeContratos($empresa);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('contrato a término fijo por vencer')
            ->assertDontSee('contratos a término fijo por vencer');
    }

    public function test_no_muestra_el_banner_si_ya_se_decidio_no_renovar(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->crearSolicitud($empresa, [
            'fecha_fin_contrato' => now()->addDays(20)->toDateString(),
            'decision_no_renovacion_en' => now(),
        ]);
        $user = $this->crearClienteConPermisoDeContratos($empresa);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('contrato a término fijo por vencer');
    }

    /**
     * Pedido explícito del usuario (2026-09-03): si a un cliente se le
     * quita el permiso de "Gestión de Contratos", el banner de "contrato
     * por vencer" debe desaparecer aunque el contrato siga vencido - antes
     * el banner solo dependía del rol, no del permiso real.
     */
    public function test_no_muestra_el_banner_si_al_cliente_le_quitaron_el_permiso_de_contratos(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->crearSolicitud($empresa, ['fecha_fin_contrato' => now()->addDays(30)->toDateString()]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $user->assignRole('cliente');
        // Sin givePermissionTo('view_any_solicitud::contrato') - simula el
        // permiso retirado desde el panel de administración.

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('contrato a término fijo por vencer');
    }
}
