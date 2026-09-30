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
 * Gemini Omni Flash confirmado accesible (curl directo, 2026-09-29). Video
 * COMPLETO encadenando llamadas de "extend" (pedido explícito del usuario,
 * 2026-09-29, tras confirmar que el costo real es bajo: ~1.170 COP por
 * clip de ~10s). Estos tests SIEMPRE usan Http::fake() - nunca deben
 * golpear la API real (costo real de dinero por cada video generado).
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

    public function test_genera_y_guarda_el_video_actualizando_el_rit(): void
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
        $this->assertNotNull($rit->video_didactico_path);
        $this->assertNotNull($rit->video_didactico_generado_en);
        Storage::disk('local')->assertExists($rit->video_didactico_path);
        $this->assertSame('fake-video-bytes', Storage::disk('local')->get($rit->video_didactico_path));
    }

    public function test_con_un_solo_tema_hace_una_sola_llamada_sin_extender(): void
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
     * Con varios temas, el video completo encadena una llamada inicial +
     * "extend" por cada tema adicional (hasta MAX_SEGMENTOS) - pedido
     * explícito del usuario (2026-09-29) tras confirmar que el costo real
     * es bajo, para cubrir más contenido que un solo clip de ~10s.
     */
    public function test_con_varios_temas_encadena_llamadas_de_extend(): void
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

        $primera = $llamadas->first()[0]->data();
        $this->assertArrayNotHasKey('previous_interaction_id', $primera);

        foreach ($llamadas->slice(1) as [$req]) {
            $body = $req->data();
            $this->assertSame('v1_test', $body['previous_interaction_id']);
            $this->assertIsString($body['input']);
        }
    }

    public function test_incluye_el_logo_de_la_empresa_como_referencia_si_existe(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->respuestaGeminiConVideo(), 200),
        ]);
        Storage::disk('local')->put('logos/renbel.png', 'contenido-fake-del-logo');

        $empresa = Empresa::factory()->create(['active' => true, 'logo_path' => 'logos/renbel.png']);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Horarios.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => 'Trabaja de 8am a 5pm.']);

        app(RitVideoDidacticoService::class)->generar($rit);

        $body = $this->soloLlamadasDeVideo()->first()[0]->data();
        $imagen = collect($body['input'] ?? [])->firstWhere('type', 'image');
        $this->assertNotNull($imagen);
        $this->assertSame(base64_encode('contenido-fake-del-logo'), $imagen['data']);
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
     * (2026-09-29): un clip de ~10s no alcanza para explicar un RIT
     * completo - si hay un diff de cambios, el video se enfoca en eso, no
     * en temas genéricos (cada cambio real es su propio segmento encadenado).
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
