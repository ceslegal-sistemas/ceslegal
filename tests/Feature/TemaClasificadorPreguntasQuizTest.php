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
            'tipo_pregunta' => 'vf',
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

    /**
     * Selección múltiple (pedido de Andrés Sarmiento, 2026-10-03): la IA
     * puede devolver tipo="multiple" con 4 opciones - se guarda en las
     * columnas nuevas (tipo_pregunta/opciones/respuesta_correcta_indice).
     */
    public function test_genera_y_guarda_la_pregunta_de_seleccion_multiple(): void
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => \App\Models\Empresa::factory()->create()->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'Articulo 1. Las vacaciones son de 15 dias habiles al año.',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Vacaciones', 'descripcion' => 'Descripción genérica.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id);

        $this->fakearGemini([
            [
                'id' => $tema->id,
                'tipo' => 'multiple',
                'pregunta' => '¿Cuántos días de vacaciones da esta empresa al año?',
                'opciones' => ['10 días', '15 días', '20 días', '30 días'],
                'respuesta_indice' => 1,
            ],
        ]);

        app(TemaClasificadorService::class)->asegurarPreguntasQuiz($rit);

        $pivot = $rit->temasNormativos()->find($tema->id)->pivot;
        $this->assertSame('multiple', $pivot->tipo_pregunta);
        $this->assertSame(['10 días', '15 días', '20 días', '30 días'], $pivot->opciones);
        $this->assertSame(1, $pivot->respuesta_correcta_indice);
        $this->assertNull($pivot->respuesta_correcta);
    }

    /**
     * Fail-open: si la IA dice tipo="multiple" pero sin las 4 opciones
     * válidas, se trata como V/F en vez de descartar la pregunta entera.
     */
    public function test_multiple_sin_opciones_validas_cae_a_vf(): void
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => \App\Models\Empresa::factory()->create()->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'Articulo 1. Texto de prueba.',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Tema Incompleto', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id);

        $this->fakearGemini([
            ['id' => $tema->id, 'tipo' => 'multiple', 'pregunta' => '¿Pregunta mal formada?', 'opciones' => ['Solo una opción'], 'respuesta' => true],
        ]);

        app(TemaClasificadorService::class)->asegurarPreguntasQuiz($rit);

        $pivot = $rit->temasNormativos()->find($tema->id)->pivot;
        $this->assertSame('vf', $pivot->tipo_pregunta);
        $this->assertTrue((bool) $pivot->respuesta_correcta);
    }

    /**
     * Preguntas solo-de-lo-que-cambió en actualizaciones (pedido de Andrés
     * Sarmiento, 2026-10-03): identificarTemasDelCambio() le pasa a la IA
     * SOLO los bloques que cambiaron (no el RIT completo) y el listado de
     * temas, y espera de vuelta los IDs afectados.
     */
    public function test_identifica_los_temas_afectados_por_el_cambio(): void
    {
        $temaJornada = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Desc.', 'activo' => true]);
        $temaVacaciones = TemaNormativo::create(['nombre' => 'Vacaciones', 'descripcion' => 'Desc.', 'activo' => true]);
        $temas = collect([$temaJornada, $temaVacaciones]);

        $this->fakearGemini([$temaJornada->id]);

        // Forma REAL de RitDiffService::compararDocumentos(): un bloque
        // 'modificado' trae 'palabras' (diff palabra por palabra), NUNCA un
        // 'texto' de nivel superior - a diferencia de 'agregado'/'eliminado'.
        $cambios = [
            ['tipo' => 'igual', 'texto' => 'Articulo 1. Texto sin cambios.'],
            ['tipo' => 'modificado', 'palabras' => [
                ['tipo' => 'igual', 'texto' => 'Articulo 2. La jornada '],
                ['tipo' => 'agregado', 'texto' => 'ahora '],
                ['tipo' => 'igual', 'texto' => 'es de 7 horas.'],
            ]],
        ];

        $ids = app(TemaClasificadorService::class)->identificarTemasDelCambio($cambios, $temas);

        $this->assertSame([$temaJornada->id], $ids);
    }

    public function test_sin_bloques_cambiados_no_llama_a_la_ia(): void
    {
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Desc.', 'activo' => true]);

        Http::fake();

        $ids = app(TemaClasificadorService::class)->identificarTemasDelCambio(
            [['tipo' => 'igual', 'texto' => 'Nada cambió.']],
            collect([$tema])
        );

        $this->assertSame([], $ids);
        Http::assertNothingSent();
    }

    public function test_fallo_de_ia_al_identificar_temas_del_cambio_devuelve_vacio(): void
    {
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Desc.', 'activo' => true]);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        $ids = app(TemaClasificadorService::class)->identificarTemasDelCambio(
            [['tipo' => 'modificado', 'texto' => 'Algo cambió.', 'palabras' => []]],
            collect([$tema])
        );

        $this->assertSame([], $ids);
    }

    public function test_guarda_el_hash_del_texto_al_generar_las_preguntas(): void
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => \App\Models\Empresa::factory()->create()->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'Articulo 1. Texto de prueba para hash.',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Tema Z', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id);

        $this->fakearGemini([
            ['id' => $tema->id, 'pregunta' => '¿Pregunta de prueba?', 'respuesta' => true],
        ]);

        app(TemaClasificadorService::class)->asegurarPreguntasQuiz($rit);

        $this->assertSame(hash('sha256', $rit->texto_completo), $rit->fresh()->resumen_simple_texto_hash);
    }
}
