<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tarjeta "Detalles del Reglamento" (pedido del usuario, 2026-09-30):
 * primera prueba del estilo visual inspirado en Hostinger (hPanel) - filas
 * etiqueta/valor limpias dentro de una tarjeta. Deliberadamente NO repite
 * fecha de publicación/plazo de objeción (ya viven en otros bloques de esta
 * misma página).
 */
class MiReglamentoInternoDetallesCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_fuente_version_y_fecha_de_actualizacion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'mejora_ia',
            'texto_completo' => 'v1', 'version' => 3,
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertSee('Detalles del Reglamento')
            ->assertSee('Mejorado con IA')
            ->assertSee('3')
            ->assertSee('Vigente');
    }

    public function test_no_repite_fecha_de_publicacion_ni_plazo_de_objecion(): void
    {
        $fuente = file_get_contents(resource_path('views/filament/components/rit-detalles-card.blade.php'));

        $this->assertStringNotContainsString('fecha_publicacion_socializacion', $fuente);
        $this->assertStringNotContainsString('fechaLimiteObjecion', $fuente);
    }
}
