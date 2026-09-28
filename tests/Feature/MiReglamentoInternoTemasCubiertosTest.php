<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\TemaNormativo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Temas que cubre su Reglamento" (2026-09-27) - vitrina de solo lectura
 * sobre la taxonomía de 27 temas ya clasificada para el RIT de la empresa
 * (ver rit-taxonomia-temas-implementada.md). Cero llamadas nuevas a IA:
 * reutiliza ReglamentoInterno::temasNormativos() y su resumen_simple ya
 * generado para el quiz de comprensión del trabajador.
 */
class MiReglamentoInternoTemasCubiertosTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_los_temas_clasificados_con_su_resumen(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral y horas extra', 'descripcion' => 'x', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => 'Su Reglamento define horarios y turnos.']);

        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        // El contador usa el badge de marca ya existente
        // (.rit-badge/.rit-badge-ia), no una pastilla inventada.
        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertSee('Temas que cubre su Reglamento')
            ->assertSeeHtml('<span class="rit-badge rit-badge-ia">1</span>')
            ->assertSee('Jornada laboral y horas extra')
            ->assertSee('Su Reglamento define horarios y turnos.');
    }

    public function test_no_muestra_la_seccion_si_todavia_no_hay_temas_clasificados(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertDontSee('Temas que cubre su Reglamento');
    }

    public function test_muestra_el_tema_sin_resumen_si_todavia_no_se_genero(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Régimen disciplinario', 'descripcion' => 'x', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id);

        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertSee('Régimen disciplinario');
    }
}
