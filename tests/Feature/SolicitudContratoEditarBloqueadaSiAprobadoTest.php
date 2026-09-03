<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\ListSolicitudContratos;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Hallazgo real (2026-09-02): "Editar" en Historial de Contratos estaba
 * SIEMPRE visible, sin importar el estado - un contrato ya 'aprobado' se
 * podía modificar directo (salario, cargo, jornada...) sin dejar ningún
 * rastro, saltándose por completo el flujo formal de Otrosí
 * (ModificacionContractualResource). Ahora solo se puede editar mientras
 * está en 'borrador'.
 */
class SolicitudContratoEditarBloqueadaSiAprobadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);

        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        Permission::findOrCreate('view_solicitud::contrato', 'web');
        Permission::findOrCreate('update_solicitud::contrato', 'web');
    }

    private function actingAsAutorizado(): User
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_any_solicitud::contrato', 'view_solicitud::contrato', 'update_solicitud::contrato']);
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
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2026-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
        ], $overrides));
    }

    public function test_editar_visible_mientras_esta_en_borrador(): void
    {
        $this->actingAsAutorizado();

        $solicitud = $this->crearSolicitud(['estado' => 'borrador']);

        Livewire::test(ListSolicitudContratos::class)
            ->assertSuccessful()
            ->assertTableActionVisible('edit', $solicitud);
    }

    public function test_editar_oculto_una_vez_aprobado(): void
    {
        $this->actingAsAutorizado();

        $solicitud = $this->crearSolicitud(['estado' => 'aprobado']);

        Livewire::test(ListSolicitudContratos::class)
            ->assertTableActionHidden('edit', $solicitud);
    }

    public function test_editar_oculto_una_vez_rechazado(): void
    {
        $this->actingAsAutorizado();

        $solicitud = $this->crearSolicitud(['estado' => 'rechazado']);

        Livewire::test(ListSolicitudContratos::class)
            ->assertTableActionHidden('edit', $solicitud);
    }
}
