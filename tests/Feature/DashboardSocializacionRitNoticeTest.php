<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Models\AceptacionReglamentoInterno;
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
}
