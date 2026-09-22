<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Livewire\SocializacionRit;
use App\Services\VerificacionFacialService;
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

    /**
     * Pedido explícito del usuario (2026-09-22): esta foto se usa después
     * como foto_referencia_path para que AWS Rekognition confirme identidad
     * en un proceso real de descargos - por eso pasa por el mismo servicio
     * de verificación (Capa 2 Gemini Vision) que usa FormularioDescargos.
     */
    public function test_verificar_accesorios_marca_alerta_si_detecta_algo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->mock(VerificacionFacialService::class, function ($mock) {
            $mock->shouldReceive('detectarAccesorios')->once()->andReturn([
                'ok' => false,
                'motivo' => 'Por favor retire las gafas oscuras.',
            ]);
        });

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->call('verificarAccesorios', $this->fotoBase64DePrueba())
            ->assertSet('alertaAccesorios', 'Por favor retire las gafas oscuras.');
    }

    public function test_validar_foto_con_ia_rechaza_foto_de_mala_calidad_sin_avanzar(): void
    {
        Storage::fake('local');
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '111222444',
            'genero' => 'masculino', 'nombres' => 'Rechazo', 'apellidos' => 'Foto', 'cargo' => 'X', 'active' => true,
        ]);
        $this->mock(VerificacionFacialService::class, function ($mock) {
            $mock->shouldReceive('validarCalidadFoto')->once()->andReturn([
                'ok' => false,
                'motivo' => 'La foto está borrosa.',
            ]);
        });

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'foto')
            ->call('validarFotoConIA', $this->fotoBase64DePrueba())
            ->assertSet('etapa', 'foto')
            ->assertSet('errorValidacionFoto', 'La foto está borrosa.');

        $this->assertNull($trabajador->fresh()->foto_referencia_path);
    }

    public function test_validar_foto_con_ia_guarda_y_avanza_si_pasa_la_calidad(): void
    {
        Storage::fake('local');
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '111222555',
            'genero' => 'femenino', 'nombres' => 'Foto', 'apellidos' => 'Valida', 'cargo' => 'X', 'active' => true,
        ]);
        $this->mock(VerificacionFacialService::class, function ($mock) {
            $mock->shouldReceive('validarCalidadFoto')->once()->andReturn(['ok' => true, 'motivo' => null]);
        });

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'foto')
            ->call('validarFotoConIA', $this->fotoBase64DePrueba())
            ->assertSet('etapa', 'presentacion_rit');

        $this->assertNotNull($trabajador->fresh()->foto_referencia_path);
    }
}
