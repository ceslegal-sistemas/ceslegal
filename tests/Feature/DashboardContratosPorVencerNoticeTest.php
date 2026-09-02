<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Tiene un contrato a término fijo por vencer');
    }

    public function test_no_muestra_el_banner_sin_contratos_por_vencer(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->crearSolicitud($empresa, ['fecha_fin_contrato' => now()->addDays(200)->toDateString()]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

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
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('contrato a término fijo por vencer');
    }
}
