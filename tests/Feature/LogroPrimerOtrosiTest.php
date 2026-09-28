<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\ListSolicitudContratos;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LevelUp\Experience\Models\Achievement;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Logro "Primer Otrosí" (2026-09-28) - cualquier tipo de modificación
 * contractual formalizada cuenta. Mismo patrón de acción modal ya probado
 * en SolicitarCambioModalTest.
 */
class LogroPrimerOtrosiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LogrosSeeder::class);

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);

        Permission::findOrCreate('view_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        Permission::findOrCreate('update_solicitud::contrato', 'web');
    }

    public function test_generar_un_otrosi_otorga_el_logro(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_solicitud::contrato', 'view_any_solicitud::contrato', 'update_solicitud::contrato']);
        $this->actingAs($user);

        $empresa = Empresa::factory()->create(['active' => true]);
        $solicitud = SolicitudContrato::create([
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
        ]);

        Livewire::test(ListSolicitudContratos::class)
            ->callTableAction('solicitarCambio', $solicitud, data: [
                'tipo_modificacion' => 'plazo',
                'valor_nuevo' => '2027-06-30',
                'justificacion' => 'Se prorroga el contrato.',
                'fecha_efectiva' => '2027-01-01',
            ])
            ->assertHasNoTableActionErrors();

        $logro = Achievement::where('name', 'Primer Otrosí')->first();
        $renovacion = Achievement::where('name', 'Primera Renovación a Tiempo')->first();
        $empresa->unsetRelation('allAchievements');

        $this->assertNotNull($empresa->allAchievements()->find($logro->id));
        $this->assertNotNull($empresa->allAchievements()->find($renovacion->id));
    }

    /**
     * Mismo modal, ahora una PRÓRROGA ('plazo') con el plazo viejo ya
     * vencido - "Primer Otrosí" igual se otorga (cualquier tipo de cambio
     * cuenta), pero "Primera Renovación a Tiempo" (Cero Vencimientos) NO,
     * porque no fue a tiempo.
     */
    public function test_una_renovacion_ya_vencida_otorga_otrosi_pero_no_cero_vencimientos(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_solicitud::contrato', 'view_any_solicitud::contrato', 'update_solicitud::contrato']);
        $this->actingAs($user);

        $empresa = Empresa::factory()->create(['active' => true]);
        $solicitud = SolicitudContrato::create([
            'empresa_id' => $empresa->id,
            'estado' => 'aprobado',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Ana',
            'trabajador_apellidos' => 'Ruiz',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '456',
            'cargo_contrato' => 'Analista',
            'salario_propuesto' => '2000000',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2024-01-01',
            'fecha_inicio_periodo_actual' => '2024-01-01',
            'fecha_fin_contrato' => '2025-01-01', // ya vencido
        ]);

        Livewire::test(ListSolicitudContratos::class)
            ->callTableAction('solicitarCambio', $solicitud, data: [
                'tipo_modificacion' => 'plazo',
                'valor_nuevo' => '2027-06-30',
                'justificacion' => 'Se prorroga tardíamente.',
                'fecha_efectiva' => '2026-09-28',
            ])
            ->assertHasNoTableActionErrors();

        $empresa->unsetRelation('allAchievements');

        $otrosi = Achievement::where('name', 'Primer Otrosí')->first();
        $ceroVencimientos = Achievement::where('name', 'Primera Renovación a Tiempo')->first();

        $this->assertNotNull($empresa->allAchievements()->find($otrosi->id));
        $this->assertNull($empresa->allAchievements()->find($ceroVencimientos->id));
    }
}
