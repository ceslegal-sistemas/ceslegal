<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Livewire\SocializacionRit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SocializacionRitEtapaFotoTest extends TestCase
{
    use RefreshDatabase;

    private function fotoBase64DePrueba(): string
    {
        // PNG 1x1 real, minimo valido para decodificar sin error.
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    public function test_guarda_la_foto_como_referencia_si_estaba_vacia(): void
    {
        Storage::fake('local');
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '777888999',
            'genero' => 'masculino', 'nombres' => 'Luis', 'apellidos' => 'Gómez', 'cargo' => 'Técnico',
            'active' => true, 'foto_referencia_path' => null,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'foto')
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba())
            ->assertSet('etapa', 'presentacion_rit');

        $rutaGuardada = $trabajador->fresh()->foto_referencia_path;
        $this->assertNotNull($rutaGuardada);
        Storage::disk('local')->assertExists($rutaGuardada);
    }

    public function test_no_sobrescribe_una_foto_de_referencia_ya_existente(): void
    {
        Storage::fake('local');
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '888999000',
            'genero' => 'femenino', 'nombres' => 'Marta', 'apellidos' => 'León', 'cargo' => 'Auxiliar',
            'active' => true, 'foto_referencia_path' => 'fotos_referencia/ya_existente.jpg',
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'foto')
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba());

        $this->assertSame('fotos_referencia/ya_existente.jpg', $trabajador->fresh()->foto_referencia_path);
    }
}
