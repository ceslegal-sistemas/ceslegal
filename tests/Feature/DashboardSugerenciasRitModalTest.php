<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Models\Bufete;
use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pop-up "insistente" pedido por el jefe, ADEMÁS del banner ya existente
 * (ver DashboardSugerenciasRitNoticeTest.php, que se conserva sin cambios).
 * A diferencia del banner (cliente + bufete), este modal es solo para
 * 'cliente' - el bufete gestiona el RIT de varias empresas y se vería
 * bombardeado con un modal por cada una.
 */
class DashboardSugerenciasRitModalTest extends TestCase
{
    use RefreshDatabase;

    private function crearSugerenciaPendiente(Empresa $empresa): SugerenciaActualizacionRit
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Texto del RIT.',
        ]);

        $documento = DocumentoLegal::create([
            'titulo' => 'Ley de prueba',
            'tipo' => 'ley',
            'estado' => 'procesado',
            'activo' => true,
        ]);

        return SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 0,
            'tipo_cambio' => 'modificar',
            'texto_anterior' => 'Texto viejo',
            'texto_propuesto' => 'Texto nuevo',
            'justificacion_ia' => 'La ley X modifica Y.',
            'estado' => 'pendiente',
        ]);
    }

    public function test_muestra_el_modal_al_cliente_con_sugerencia_pendiente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->crearSugerenciaPendiente($empresa);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('sugrit-modal-overlay', false);
    }

    public function test_no_muestra_el_modal_sin_sugerencias_pendientes(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('sugrit-modal-overlay', false);
    }

    public function test_no_muestra_el_modal_a_bufete_aunque_vea_el_banner(): void
    {
        $bufete = Bufete::factory()->create();
        $empresa = Empresa::factory()->create(['active' => true, 'bufete_id' => $bufete->id]);
        $this->crearSugerenciaPendiente($empresa);
        $bufeteUser = User::factory()->create(['role' => 'bufete', 'bufete_id' => $bufete->id, 'active' => true]);

        Livewire::actingAs($bufeteUser)->test(Dashboard::class)
            ->assertSee('actualización legal que aplica a su Reglamento Interno')
            ->assertDontSee('sugrit-modal-overlay', false);
    }

    public function test_no_muestra_el_modal_a_super_admin(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->crearSugerenciaPendiente($empresa);
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertDontSee('sugrit-modal-overlay', false);
    }
}
