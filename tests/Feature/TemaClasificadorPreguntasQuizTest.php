<?php

namespace Tests\Feature;

use App\Models\ReglamentoInterno;
use App\Models\TemaNormativo;
use App\Services\TemaClasificadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Quiz de comprensión antes de aceptar el RIT (mejora "excéntrica" pedida
 * por el usuario, 2026-09-23): refuerza que la aceptación es informada, no
 * un clic mecánico. Mismo patrón de generación/cacheo que los resúmenes
 * simples (TemaClasificadorResumenSimpleTest) - reutiliza el mismo hash de
 * texto, no agrega una columna de hash nueva.
 */
class TemaClasificadorPreguntasQuizTest extends TestCase
{
    use RefreshDatabase;

    private function fakearGemini(array $preguntas): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode($preguntas),
                    ]]],
                ]],
            ], 200),
        ]);
    }

    public function test_genera_y_guarda_la_pregunta_vf_por_tema(): void
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => \App\Models\Empresa::factory()->create()->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'Articulo 1. La jornada es de 8 horas diarias.',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Descripción genérica.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id);

        $this->fakearGemini([
            ['id' => $tema->id, 'pregunta' => '¿La jornada de esta empresa es de 8 horas diarias?', 'respuesta' => true],
        ]);

        app(TemaClasificadorService::class)->asegurarPreguntasQuiz($rit);

        $pivot = $rit->temasNormativos()->find($tema->id)->pivot;
        $this->assertSame('¿La jornada de esta empresa es de 8 horas diarias?', $pivot->pregunta_vf);
        $this->assertTrue((bool) $pivot->respuesta_correcta);
    }

    public function test_no_vuelve_a_llamar_a_la_ia_si_el_texto_no_cambio(): void
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => \App\Models\Empresa::factory()->create()->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'Articulo 1. Texto sin cambios.',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Tema X', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, [
            'pregunta_vf' => 'Ya tenia pregunta.',
            'respuesta_correcta' => true,
        ]);
        $rit->forceFill(['resumen_simple_texto_hash' => hash('sha256', $rit->texto_completo)])->save();

        Http::fake();

        app(TemaClasificadorService::class)->asegurarPreguntasQuiz($rit);

        Http::assertNothingSent();
    }

    public function test_falla_de_ia_no_rompe_y_deja_el_tema_sin_pregunta(): void
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => \App\Models\Empresa::factory()->create()->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'Articulo 1. Texto de prueba.',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Tema Y', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        app(TemaClasificadorService::class)->asegurarPreguntasQuiz($rit);

        $this->assertNull($rit->temasNormativos()->find($tema->id)->pivot->pregunta_vf);
    }
}
