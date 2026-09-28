<?php

namespace Tests\Feature;

use App\Livewire\SocializacionRit;
use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Services\VerificacionFacialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Evidencia jurídica (2026-09-28): selfie nueva en CADA aceptación del RIT,
 * distinta de la foto de referencia (etapa 'foto', foto_referencia_path del
 * trabajador) - pedido explícito del usuario. Reusa
 * foto-simple-captura.blade.php parametrizado, sin tocar foto_referencia_path.
 */
class SocializacionRitEtapaFotoAceptacionTest extends TestCase
{
    use RefreshDatabase;

    private function fotoBase64DePrueba(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    private function crearTrabajador(Empresa $empresa): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '222333444',
            'genero' => 'masculino', 'nombres' => 'Foto', 'apellidos' => 'Aceptacion', 'cargo' => 'X', 'active' => true,
        ]);
    }

    public function test_rechaza_foto_de_mala_calidad_sin_avanzar(): void
    {
        Storage::fake('local');
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = $this->crearTrabajador($empresa);
        $this->mock(VerificacionFacialService::class, function ($mock) {
            $mock->shouldReceive('validarCalidadFoto')->once()->andReturn([
                'ok' => false,
                'motivo' => 'La foto está borrosa.',
            ]);
        });

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'foto_aceptacion')
            ->call('validarFotoAceptacionConIA', $this->fotoBase64DePrueba())
            ->assertSet('etapa', 'foto_aceptacion')
            ->assertSet('errorValidacionFotoAceptacion', 'La foto está borrosa.');

        $this->assertSame('', $trabajador->fresh()->foto_referencia_path ?? '');
    }

    public function test_guarda_el_base64_en_memoria_y_avanza_a_aceptacion_sin_tocar_foto_referencia(): void
    {
        Storage::fake('local');
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = $this->crearTrabajador($empresa);
        $trabajador->update(['foto_referencia_path' => 'fotos_referencia/ya_existente.jpg']);
        $this->mock(VerificacionFacialService::class, function ($mock) {
            $mock->shouldReceive('validarCalidadFoto')->once()->andReturn(['ok' => true, 'motivo' => null]);
        });

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'foto_aceptacion')
            ->call('validarFotoAceptacionConIA', $this->fotoBase64DePrueba())
            ->assertSet('etapa', 'aceptacion')
            ->assertSet('fotoAceptacionBase64', $this->fotoBase64DePrueba());

        $this->assertSame('fotos_referencia/ya_existente.jpg', $trabajador->fresh()->foto_referencia_path);
    }

    public function test_aceptar_reglamento_persiste_la_foto_de_aceptacion_y_el_quiz(): void
    {
        Storage::fake('local');
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = $this->crearTrabajador($empresa);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->set('fotoAceptacionBase64', $this->fotoBase64DePrueba())
            ->set('quizRespuestas', [
                ['pregunta' => '¿P1?', 'respuesta_correcta' => true, 'intentos' => [
                    ['respuesta_dada' => true, 'correcta' => true, 'respondido_en' => now()->toISOString()],
                ]],
            ])
            ->set('declaracionAceptada', true)
            ->call('aceptarReglamento')
            ->assertSet('etapa', 'completado');

        $aceptacion = AceptacionReglamentoInterno::where('trabajador_id', $trabajador->id)->firstOrFail();
        $this->assertSame(hash('sha256', 'v1'), $aceptacion->texto_rit_hash);
        $this->assertNotNull($aceptacion->foto_aceptacion_path);
        $this->assertCount(1, $aceptacion->quiz_resultado);
        $this->assertNotNull($aceptacion->ruta_acta);
    }
}
