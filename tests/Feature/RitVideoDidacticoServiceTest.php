<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\TemaNormativo;
use App\Services\RitVideoDidacticoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Segunda socialización" con video de IA (pedido del equipo, 2026-09-28) -
 * Gemini Omni Flash confirmado accesible (curl directo, 2026-09-29).
 *
 * REDISEÑO 2026-09-30: cada tema/cambio genera un CAPÍTULO independiente
 * (interacción nueva, sin previous_interaction_id) en vez de encadenar
 * "extend" - Gemini tiene un límite duro de ~30-40s totales por video
 * extendido (error real de producción), así que encadenar topaba la
 * cobertura a 3-4 temas de los hasta 27 que puede tener un RIT. Sin tope
 * artificial: un capítulo por cada tema YA clasificado (misma lista que
 * "Temas que cubre su Reglamento") o por cada cambio real.
 *
 * Estos tests SIEMPRE usan Http::fake() - nunca deben golpear la API real
 * (costo real de dinero por cada video generado).
 */
class RitVideoDidacticoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function respuestaGeminiConVideo(string $videoBase64 = 'ZmFrZS12aWRlby1ieXRlcw==', string $id = 'v1_test'): array
    {
        return [
            'id' => $id,
            'status' => 'completed',
            'steps' => [
                ['type' => 'user_input', 'content' => [['type' => 'text', 'text' => 'x']]],
                [
                    'type' => 'model_output',
                    'content' => [
                        ['type' => 'video', 'mime_type' => 'video/mp4', 'data' => $videoBase64],
                    ],
                ],
            ],
        ];
    }

    /** Solo las llamadas al endpoint de video - filtra las de TemaClasificadorService (generateContent). */
    private function soloLlamadasDeVideo(): \Illuminate\Support\Collection
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), '/v1beta/interactions'));
    }

    public function test_genera_un_capitulo_por_tema_y_guarda_el_arreglo_en_el_rit(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->respuestaGeminiConVideo(), 200),
        ]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Horarios.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => 'Trabaja de 8am a 5pm.']);

        app(RitVideoDidacticoService::class)->generar($rit);

        $rit->refresh();
        $this->assertSame('completado', $rit->video_didactico_estado);
        $this->assertNull($rit->video_didactico_path);
        $this->assertNotNull($rit->video_didactico_generado_en);
        $this->assertCount(1, $rit->video_didactico_capitulos);
        $this->assertSame('Jornada laboral', $rit->video_didactico_capitulos[0]['titulo']);
        Storage::disk('local')->assertExists($rit->video_didactico_capitulos[0]['path']);
        $this->assertSame('fake-video-bytes', Storage::disk('local')->get($rit->video_didactico_capitulos[0]['path']));
    }

    public function test_con_un_solo_tema_hace_una_sola_llamada_independiente(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->respuestaGeminiConVideo(), 200),
        ]);

        $empresa = Empresa::factory()->create(['active' => true, 'razon_social' => 'RENBEL SAS']);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Horarios.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => 'Trabaja de 8am a 5pm.']);

        app(RitVideoDidacticoService::class)->generar($rit);

        $llamadas = $this->soloLlamadasDeVideo();
        $this->assertCount(1, $llamadas);

        $body = $llamadas->first()[0]->data();
        $textoInput = collect($body['input'] ?? [])->firstWhere('type', 'text')['text'] ?? '';
        $this->assertStringContainsString('RENBEL', $textoInput);
        $this->assertStringContainsString('Jornada laboral', $textoInput);
        $this->assertStringContainsString('Trabaja de 8am a 5pm.', $textoInput);
        $this->assertArrayNotHasKey('previous_interaction_id', $body);
    }

    /**
     * Con varios temas, cada uno genera su propio capítulo INDEPENDIENTE
     * (interacción nueva, nunca previous_interaction_id) - rediseño
     * 2026-09-30 tras confirmar que encadenar con "extend" topa la
     * cobertura por el límite duro de Gemini de ~30-40s totales.
     */
    public function test_con_varios_temas_hace_una_llamada_independiente_por_cada_uno(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->respuestaGeminiConVideo(), 200),
        ]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        foreach (['Jornada laboral', 'Vacaciones', 'SG-SST'] as $nombre) {
            $tema = TemaNormativo::create(['nombre' => $nombre, 'descripcion' => 'Desc.', 'activo' => true]);
            $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => "Resumen de {$nombre}."]);
        }

        app(RitVideoDidacticoService::class)->generar($rit);

        $llamadas = $this->soloLlamadasDeVideo();
        $this->assertCount(3, $llamadas);

        foreach ($llamadas as [$req]) {
            $this->assertArrayNotHasKey('previous_interaction_id', $req->data());
        }

        $rit->refresh();
        $this->assertCount(3, $rit->video_didactico_capitulos);
        $this->assertSame(['Jornada laboral', 'Vacaciones', 'SG-SST'], array_column($rit->video_didactico_capitulos, 'titulo'));
    }

    /**
     * Sin tope artificial: si el RIT tiene 10 temas clasificados (como en
     * un caso real de producción), el video debe generar 10 capítulos, no
     * recortar a un número fijo - pedido explícito del usuario 2026-09-30.
     */
    public function test_no_recorta_el_numero_de_capitulos(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->respuestaGeminiConVideo(), 200),
        ]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        for ($i = 1; $i <= 10; $i++) {
            $tema = TemaNormativo::create(['nombre' => "Tema {$i}", 'descripcion' => 'Desc.', 'activo' => true]);
            $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => "Resumen {$i}."]);
        }

        app(RitVideoDidacticoService::class)->generar($rit);

        $this->assertCount(10, $this->soloLlamadasDeVideo());
        $this->assertCount(10, $rit->fresh()->video_didactico_capitulos);
    }

    public function test_incluye_el_logo_de_la_empresa_en_cada_capitulo(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->respuestaGeminiConVideo(), 200),
        ]);
        Storage::disk('local')->put('logos/renbel.png', 'contenido-fake-del-logo');

        $empresa = Empresa::factory()->create(['active' => true, 'logo_path' => 'logos/renbel.png']);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        foreach (['Jornada laboral', 'Vacaciones'] as $nombre) {
            $tema = TemaNormativo::create(['nombre' => $nombre, 'descripcion' => 'Desc.', 'activo' => true]);
            $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => "Resumen de {$nombre}."]);
        }

        app(RitVideoDidacticoService::class)->generar($rit);

        foreach ($this->soloLlamadasDeVideo() as [$req]) {
            $imagen = collect($req->data()['input'] ?? [])->firstWhere('type', 'image');
            $this->assertNotNull($imagen, 'Cada capítulo debe llevar el logo, no solo el primero.');
            $this->assertSame(base64_encode('contenido-fake-del-logo'), $imagen['data']);
        }
    }

    public function test_sin_logo_no_envia_ninguna_imagen(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->respuestaGeminiConVideo(), 200),
        ]);

        $empresa = Empresa::factory()->create(['active' => true, 'logo_path' => null]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Horarios.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => 'Trabaja de 8am a 5pm.']);

        app(RitVideoDidacticoService::class)->generar($rit);

        $body = $this->soloLlamadasDeVideo()->first()[0]->data();
        $this->assertNull(collect($body['input'] ?? [])->firstWhere('type', 'image'));
    }

    public function test_lanza_excepcion_si_la_api_falla(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'RESOURCE_EXHAUSTED']], 429),
        ]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Horarios.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => 'x']);

        $this->expectException(\RuntimeException::class);

        app(RitVideoDidacticoService::class)->generar($rit);
    }

    public function test_lanza_excepcion_si_no_hay_temas_ni_cambios(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);

        $this->expectException(\RuntimeException::class);

        app(RitVideoDidacticoService::class)->generar($rit);
    }

    /**
     * Pedido explícito del usuario tras ver el video de prueba real
     * (2026-09-29): si hay un diff de cambios, el video se enfoca en eso,
     * no en temas genéricos (cada cambio real es su propio capítulo).
     */
    public function test_con_cambios_el_prompt_se_enfoca_en_que_cambio_no_en_temas_genericos(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->respuestaGeminiConVideo(), 200),
        ]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'mejora_ia', 'texto_completo' => 'v2',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Horarios.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => 'No debería aparecer en el prompt.']);

        $cambios = [
            ['tipo' => 'igual', 'texto' => 'Artículo 1 sin cambios.'],
            ['tipo' => 'agregado', 'texto' => 'Nuevo artículo sobre teletrabajo.'],
            [
                'tipo' => 'modificado',
                'palabras' => [
                    ['tipo' => 'igual', 'texto' => 'La jornada es de '],
                    ['tipo' => 'eliminado', 'texto' => '44'],
                    ['tipo' => 'agregado', 'texto' => '42'],
                    ['tipo' => 'igual', 'texto' => ' horas semanales.'],
                ],
            ],
        ];

        app(RitVideoDidacticoService::class)->generar($rit, $cambios);

        $llamadas = $this->soloLlamadasDeVideo();
        $this->assertCount(2, $llamadas); // 2 cambios reales (se descarta el 'igual')

        $textoCompleto = $llamadas->map(function ($par) {
            $body = $par[0]->data();
            return is_array($body['input']) ? (collect($body['input'])->firstWhere('type', 'text')['text'] ?? '') : $body['input'];
        })->implode(' | ');

        $this->assertStringContainsString('Nuevo artículo sobre teletrabajo', $textoCompleto);
        $this->assertStringContainsString('La jornada es de 42 horas semanales', $textoCompleto);
        $this->assertStringNotContainsString('No debería aparecer en el prompt', $textoCompleto);
        $this->assertStringNotContainsString('sin cambios', $textoCompleto);
    }

    public function test_lanza_excepcion_si_la_respuesta_no_trae_video(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['id' => 'v1_x', 'status' => 'completed', 'steps' => []], 200),
        ]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Horarios.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => 'x']);

        $this->expectException(\RuntimeException::class);

        app(RitVideoDidacticoService::class)->generar($rit);
    }
}
