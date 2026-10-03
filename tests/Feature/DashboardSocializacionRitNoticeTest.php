<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Models\AceptacionReglamentoInterno;
use App\Models\CulminacionSocializacionRit;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tarjeta combinada "progreso de aceptacion del RIT" + banner de compartir
 * en el Dashboard (mejora "excentrica" pedida por el usuario, 2026-09-23:
 * "el de socializar rit tambien poner en el dashboard"). Solo visible para
 * 'cliente' con empresa y RIT activo, mismo criterio que la tarjeta de
 * logros de Descargos.
 */
class DashboardSocializacionRitNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function crearEmpresaConRitYUsuario(): array
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        return [$empresa, $user];
    }

    public function test_muestra_el_progreso_de_aceptacion_para_cliente(): void
    {
        [$empresa, $user] = $this->crearEmpresaConRitYUsuario();
        Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '111222333',
            'genero' => 'masculino', 'nombres' => 'Sin', 'apellidos' => 'Aceptar', 'cargo' => 'Op', 'active' => true,
        ]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('0 de 1 trabajadores han aceptado el Reglamento Interno vigente');
    }

    public function test_muestra_mensaje_de_completo_al_100_por_ciento(): void
    {
        [$empresa, $user] = $this->crearEmpresaConRitYUsuario();
        $rit = ReglamentoInterno::where('empresa_id', $empresa->id)->first();
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '444555666',
            'genero' => 'masculino', 'nombres' => 'Unico', 'apellidos' => 'Trabajador', 'cargo' => 'Op', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now(),
            'texto_rit_hash' => hash('sha256', (string) $rit->texto_completo),
        ]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('¡Todo tu equipo aceptó el Reglamento Interno!');
    }

    public function test_no_muestra_la_tarjeta_para_bufete(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $user = User::factory()->create(['role' => 'bufete', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('han aceptado el Reglamento Interno vigente');
    }

    /**
     * "Culminar Socialización del RIT" también debe funcionar desde el
     * Dashboard (pedido explícito del usuario, 2026-10-03: "en el dashboard
     * no sale el boton") - mismo trait InteractsConCulminacionSocializacionRit
     * que usa Mi Reglamento Interno, resolviendo empresa/RIT por su cuenta.
     */
    public function test_muestra_el_boton_de_culminar_en_fase_2(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Culminar Socialización del RIT');
    }

    public function test_no_muestra_el_boton_de_culminar_en_fase_1(): void
    {
        [$empresa, $user] = $this->crearEmpresaConRitYUsuario();

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('Culminar Socialización del RIT');
    }

    public function test_muestra_la_constancia_de_culminacion_en_el_dashboard(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        CulminacionSocializacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'user_id' => $user->id,
            'texto_rit_hash' => hash('sha256', $rit->texto_completo),
            'declarado_en' => now(),
        ]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Socialización culminada el')
            ->assertDontSee('Culminar Socialización del RIT');
    }
}
