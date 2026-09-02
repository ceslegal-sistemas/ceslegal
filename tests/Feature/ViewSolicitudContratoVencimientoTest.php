<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ViewSolicitudContratoVencimientoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-02'));
        Permission::findOrCreate('view_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

    public function test_muestra_la_alerta_y_los_botones_dentro_de_la_ventana(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_solicitud::contrato', 'view_any_solicitud::contrato']);
        $this->actingAs($user);

        $solicitud = $this->crearSolicitud(['fecha_fin_contrato' => now()->addDays(20)->toDateString()]);

        $this->get(SolicitudContratoResource::getUrl('view', ['record' => $solicitud]))
            ->assertSuccessful()
            ->assertSee('Este contrato está por vencer')
            ->assertSee('Sí, renovar')
            ->assertSee('No renovar');
    }

    public function test_no_muestra_nada_fuera_de_la_ventana(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_solicitud::contrato', 'view_any_solicitud::contrato']);
        $this->actingAs($user);

        $solicitud = $this->crearSolicitud(['fecha_fin_contrato' => now()->addDays(200)->toDateString()]);

        $this->get(SolicitudContratoResource::getUrl('view', ['record' => $solicitud]))
            ->assertSuccessful()
            ->assertDontSee('Este contrato está por vencer');
    }

    public function test_muestra_aviso_informativo_tras_decidir_no_renovar(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_solicitud::contrato', 'view_any_solicitud::contrato']);
        $this->actingAs($user);

        $solicitud = $this->crearSolicitud([
            'fecha_fin_contrato' => now()->addDays(20)->toDateString(),
            'decision_no_renovacion_en' => now(),
        ]);

        $this->get(SolicitudContratoResource::getUrl('view', ['record' => $solicitud]))
            ->assertSuccessful()
            ->assertSee('Ya decidió no renovar este contrato')
            ->assertDontSee('Este contrato está por vencer');
    }
}
