<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ModificacionContractualReskinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('create_modificacion::contractual', 'web');
        Permission::findOrCreate('view_any_modificacion::contractual', 'web');
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
        ]);
    }

    /**
     * Cuando se llega con solicitud_contrato_id en la URL (botón "Solicitar
     * un Cambio" desde Ver Contrato), el wizard debe arrancar en el Paso 2
     * ("El Cambio") - el cliente no debe tener que elegir el contrato de
     * nuevo si ya estaba parado en su página.
     */
    public function test_el_wizard_salta_al_paso_2_si_el_contrato_viene_prefijado(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['create_modificacion::contractual', 'view_any_modificacion::contractual']);
        $this->actingAs($user);

        $solicitud = $this->crearSolicitud();

        $this->get(ModificacionContractualResource::getUrl('create', ['solicitud_contrato_id' => $solicitud->id]))
            ->assertSuccessful()
            ->assertSee('getSteps().at(1)', false);
    }

    /**
     * Creando desde el listado plano (bufete/super_admin, sin contrato
     * prefijado), el wizard debe arrancar normalmente en el Paso 1.
     */
    public function test_el_wizard_arranca_en_el_paso_1_sin_contrato_prefijado(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['create_modificacion::contractual', 'view_any_modificacion::contractual']);
        $this->actingAs($user);

        $this->get(ModificacionContractualResource::getUrl('create'))
            ->assertSuccessful()
            ->assertSee('getSteps().at(0)', false);
    }

    public function test_el_hero_de_crear_muestra_mensaje_generico_sin_registro(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['create_modificacion::contractual', 'view_any_modificacion::contractual']);
        $this->actingAs($user);

        $this->get(ModificacionContractualResource::getUrl('create'))
            ->assertSuccessful()
            ->assertSee('Nuevo Otrosí de Contrato');
    }

    /**
     * Un cliente nunca debe ver "Otrosíes de Contrato" como ítem de menú
     * aparte - siempre llega ahí desde su propio contrato. bufete/
     * super_admin sí lo ven (gestión/auditoría del historial completo).
     */
    public function test_el_menu_se_oculta_para_cliente_pero_no_para_otros_roles(): void
    {
        $cliente = User::factory()->create(['role' => 'cliente', 'active' => true]);
        $this->actingAs($cliente);
        $this->assertFalse(ModificacionContractualResource::shouldRegisterNavigation());

        $superAdmin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($superAdmin);
        $this->assertTrue(ModificacionContractualResource::shouldRegisterNavigation());

        $bufete = User::factory()->create(['role' => 'bufete', 'active' => true]);
        $this->actingAs($bufete);
        $this->assertTrue(ModificacionContractualResource::shouldRegisterNavigation());
    }
}
