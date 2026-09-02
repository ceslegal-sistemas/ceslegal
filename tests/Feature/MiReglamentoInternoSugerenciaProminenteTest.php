<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido explícito del usuario: el bloque "Cambios sugeridos para su
 * Reglamento" dentro de "Mi Reglamento Interno" usaba el mismo estilo
 * discreto del visor de documento plano - debía resaltar más, igual que ya
 * se hizo con el banner del Dashboard (ver
 * dashboard-sugerencias-rit-notice.blade.php).
 */
class MiReglamentoInternoSugerenciaProminenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_bloque_de_sugerencias_usa_el_estilo_prominente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Artículo 1. Texto del reglamento.',
        ]);
        $documento = DocumentoLegal::create([
            'titulo' => 'Ley de prueba',
            'tipo' => 'ley',
            'estado' => 'procesado',
            'activo' => true,
        ]);
        SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 0,
            'tipo_cambio' => 'modificar',
            'texto_anterior' => 'Artículo 1. Texto del reglamento.',
            'texto_propuesto' => 'Artículo 1. Texto nuevo del reglamento.',
            'justificacion_ia' => 'La ley X modifica Y.',
            'estado' => 'pendiente',
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertSeeHtml('rit-viewer-sugerencia')
            ->assertSee('Cambios sugeridos para su Reglamento (1)');
    }
}
