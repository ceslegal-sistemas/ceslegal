<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\HistoricoVideoDidacticoRit;
use App\Models\ReglamentoInterno;
use App\Models\TemaNormativo;
use App\Services\RitVideoDidacticoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * REDISEÑO 2026-10-14 (modificaciones de Andrés Sarmiento, integradas y
 * probadas el mismo día): los abogados asesores reportaron que el video no
 * tenía profundidad ni coherencia. El flujo pasa de "1 clip por tema,
 * improvisado por el modelo de video" a dos pasos:
 *  A. Un GUIONISTA (modelo de texto) escribe el guion completo de la serie;
 *     cada clip trae una cita literal del RIT que PHP verifica sin IA -
 *     un clip cuya cita no aparece se descarta (anti-alucinación).
 *  B. El modelo de VIDEO solo dice la narración ya escrita y verificada.
 * Es reanudable: un clip ya generado (archivo en disco) no se vuelve a
 * pagar en un reintento.
 *
 * Esta suite reemplaza por completo la anterior (1 capítulo = 1 tema, sin
 * guion ni verificación) - la arquitectura cambió de raíz, no solo el
 * prompt.
 *
 * Estos tests SIEMPRE usan Http::fake() - nunca deben golpear la API real
 * (costo real de dinero por cada video/guion generado).
 */
class RitVideoDidacticoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** Texto de RIT realista (>500 caracteres), con pasajes literales que los clips de prueba citan. */
    private function textoRitRealista(): string
    {
        return 'REGLAMENTO INTERNO DE TRABAJO. ARTÍCULO 1. JORNADA LABORAL. La jornada de trabajo es de cuarenta y ocho horas semanales, distribuidas de lunes a viernes, con un descanso de una hora para el almuerzo. '
            . 'ARTÍCULO 15. OBLIGACIONES DEL TRABAJADOR. El trabajador debe cumplir con el horario establecido y reportar cualquier ausencia con anticipación a su jefe inmediato. '
            . 'ARTÍCULO 60. FALTAS LEVES. Constituye falta leve la llegada tardía reiterada sin justificación, la cual será sancionada con llamado de atención escrito en la hoja de vida laboral. '
            . 'ARTÍCULO 70. PROCEDIMIENTO DISCIPLINARIO. Antes de aplicar cualquier sanción, la empresa citará al trabajador a diligencia de descargos, donde podrá presentar su versión de los hechos y las pruebas que considere pertinentes.';
    }

    /** @return array{seccion:string,subtitulo:string,tipo:string,importancia:int,narracion:string,escena:string,cita_rit:string,referencia:?string} */
    private function clip(string $tipo, string $seccion, string $narracion, string $citaRit = '', ?string $referencia = null): array
    {
        return [
            'seccion' => $seccion,
            'subtitulo' => $tipo === 'apertura' || $tipo === 'cierre' ? '' : 'Lo esencial',
            'tipo' => $tipo,
            'importancia' => 2,
            'narracion' => $narracion,
            'escena' => 'El presentador habla a cámara.',
            'cita_rit' => $citaRit,
            'referencia' => $referencia,
        ];
    }

    /** Guion válido mínimo: apertura + 4 clips con citas literales del RIT real + cierre. */
    private function guionValido(): array
    {
        return [
            'titulo_serie' => 'Conozca su Reglamento Interno',
            'clips' => [
                $this->clip('apertura', 'Apertura', 'Bienvenido, hoy conocerá su Reglamento Interno de Trabajo.'),
                $this->clip('contenido', 'Jornada laboral', 'Su jornada es de cuarenta y ocho horas semanales.', 'La jornada de trabajo es de cuarenta y ocho horas semanales', 'Artículo 1'),
                $this->clip('contenido', 'Obligaciones', 'Debe reportar cualquier ausencia con anticipación.', 'reportar cualquier ausencia con anticipación a su jefe inmediato', 'Artículo 15'),
                $this->clip('contenido', 'Faltas leves', 'Llegar tarde sin justificación es una falta leve.', 'la llegada tardía reiterada sin justificación', 'Artículo 60'),
                $this->clip('contenido', 'Procedimiento disciplinario', 'Siempre se le citará a dar su versión de los hechos.', 'podrá presentar su versión de los hechos y las pruebas', 'Artículo 70'),
                $this->clip('cierre', 'Cierre', 'Recuerde que puede consultar el reglamento completo.'),
            ],
        ];
    }

    private function respuestaGeminiTexto(array $guion, string $finishReason = 'STOP'): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode($guion, JSON_UNESCAPED_UNICODE)]]],
                'finishReason' => $finishReason,
            ]],
        ];
    }

    private function respuestaGeminiVideo(string $videoBase64 = 'ZmFrZS12aWRlby1ieXRlcw==', string $id = 'v1_test'): array
    {
        return [
            'id' => $id,
            'status' => 'completed',
            'steps' => [
                ['type' => 'user_input', 'content' => [['type' => 'text', 'text' => 'x']]],
                ['type' => 'model_output', 'content' => [['type' => 'video', 'mime_type' => 'video/mp4', 'data' => $videoBase64]]],
            ],
        ];
    }

    /**
     * Solo las llamadas de generateContent del GUIONISTA de este servicio -
     * ReglamentoInterno::create() dispara, por su cuenta, una clasificación
     * de temas (TemaClasificadorService) que TAMBIÉN pega a generateContent;
     * se filtra por un texto propio del prompt del guionista para no contar
     * esas llamadas ajenas (mismo criterio que soloLlamadasDeVideo() en la
     * suite anterior, que filtraba por URL para excluir al clasificador).
     */
    private function llamadasDeTexto(): \Illuminate\Support\Collection
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'generateContent')
            && str_contains(json_encode($r->data()), 'guionista de contenidos educativos'));
    }

    private function llamadasDeVideo(): \Illuminate\Support\Collection
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), '/v1beta/interactions'));
    }

    private function crearRit(string $texto = null, string $fuente = 'construido_ia'): ReglamentoInterno
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        return ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => $fuente,
            'texto_completo' => $texto ?? $this->textoRitRealista(),
        ]);
    }

    public function test_lanza_excepcion_si_el_texto_del_rit_es_muy_corto(): void
    {
        $rit = $this->crearRit('Reglamento muy corto.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no tiene texto suficiente');

        app(RitVideoDidacticoService::class)->generar($rit);
    }

    public function test_genera_el_guion_y_un_clip_de_video_por_cada_clip_valido(): void
    {
        Http::fake([
            '*generateContent*' => Http::response($this->respuestaGeminiTexto($this->guionValido()), 200),
            '*/v1beta/interactions*' => Http::response($this->respuestaGeminiVideo(), 200),
        ]);

        $rit = $this->crearRit();

        app(RitVideoDidacticoService::class)->generar($rit);

        $this->assertCount(1, $this->llamadasDeTexto());
        $this->assertCount(6, $this->llamadasDeVideo());

        $rit->refresh();
        $this->assertSame('completado', $rit->video_didactico_estado);
        $this->assertNull($rit->video_didactico_path);
        $this->assertCount(6, $rit->video_didactico_capitulos);
        $this->assertSame('Jornada laboral', $rit->video_didactico_capitulos[1]['seccion']);
        Storage::disk('local')->assertExists($rit->video_didactico_capitulos[0]['path']);
    }

    public function test_descarta_un_clip_cuya_cita_no_existe_literalmente_en_el_rit(): void
    {
        $guion = $this->guionValido();
        $guion['clips'][] = $this->clip('contenido', 'Inventado', 'Esto no está en el reglamento.', 'una frase que nunca aparece en el texto del rit', 'Artículo 99');

        Http::fake([
            '*generateContent*' => Http::response($this->respuestaGeminiTexto($guion), 200),
            '*/v1beta/interactions*' => Http::response($this->respuestaGeminiVideo(), 200),
        ]);

        $rit = $this->crearRit();

        $servicio = app(RitVideoDidacticoService::class);
        $servicio->generar($rit);

        // 6 válidos (el 7mo, inventado, se descartó) - nunca se pagó un video para él.
        $this->assertCount(6, $this->llamadasDeVideo());

        $guionGuardado = $servicio->generarGuion($rit);
        $this->assertCount(1, $guionGuardado['clips_rechazados']);
        $this->assertSame('cita no encontrada literalmente en el RIT', $guionGuardado['clips_rechazados'][0]['motivo']);
    }

    public function test_reintenta_el_guion_si_mas_de_un_25_por_ciento_de_citas_fallan(): void
    {
        $guionMalo = [
            'titulo_serie' => 'x',
            'clips' => [
                $this->clip('apertura', 'Apertura', 'Bienvenido.'),
                $this->clip('contenido', 'A', 'x', 'esto no aparece en el rit de ninguna forma'),
                $this->clip('contenido', 'B', 'x', 'esto tampoco aparece en el texto'),
                $this->clip('contenido', 'C', 'x', 'ni esto otro mas'),
                $this->clip('cierre', 'Cierre', 'Gracias.'),
            ],
        ];

        // Http::sequence() no sirve aquí: ReglamentoInterno::create() dispara su
        // propia llamada a generateContent (el clasificador de temas) que
        // consumiría el primer elemento de la secuencia pensada para el
        // guionista. Se enruta a mano por contenido del body en su lugar.
        $intentoGuion = 0;
        Http::fake(function ($request) use ($guionMalo, &$intentoGuion) {
            if (str_contains($request->url(), '/v1beta/interactions')) {
                return Http::response($this->respuestaGeminiVideo(), 200);
            }
            if (str_contains(json_encode($request->data()), 'guionista de contenidos educativos')) {
                $intentoGuion++;

                return Http::response($this->respuestaGeminiTexto($intentoGuion === 1 ? $guionMalo : $this->guionValido()), 200);
            }

            // Clasificador de temas de ReglamentoInterno::create() - no es parte de este test.
            return Http::response(['candidates' => [['content' => ['parts' => [['text' => '[]']]], 'finishReason' => 'STOP']]], 200);
        });

        $rit = $this->crearRit();

        app(RitVideoDidacticoService::class)->generar($rit);

        // El primer intento tuvo 3/5 citas malas (60% > 25%) - debió reintentar.
        $this->assertCount(2, $this->llamadasDeTexto());
        $this->assertCount(6, $this->llamadasDeVideo());
    }

    public function test_lanza_excepcion_si_quedan_muy_pocos_clips_verificados(): void
    {
        $guionDegenerado = [
            'titulo_serie' => 'x',
            'clips' => [
                $this->clip('apertura', 'Apertura', 'Bienvenido.'),
                $this->clip('cierre', 'Cierre', 'Gracias.'),
            ],
        ];

        Http::fake([
            '*generateContent*' => Http::response($this->respuestaGeminiTexto($guionDegenerado), 200),
        ]);

        $rit = $this->crearRit();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('clips verificados');

        app(RitVideoDidacticoService::class)->generar($rit);
    }

    public function test_reusa_el_guion_cacheado_en_una_segunda_llamada(): void
    {
        Http::fake([
            '*generateContent*' => Http::response($this->respuestaGeminiTexto($this->guionValido()), 200),
            '*/v1beta/interactions*' => Http::response($this->respuestaGeminiVideo(), 200),
        ]);

        $rit = $this->crearRit();
        $servicio = app(RitVideoDidacticoService::class);

        $servicio->generarGuion($rit);
        $servicio->generarGuion($rit);

        $this->assertCount(1, $this->llamadasDeTexto());
    }

    public function test_regenerar_guion_ignora_la_cache(): void
    {
        Http::fake([
            '*generateContent*' => Http::response($this->respuestaGeminiTexto($this->guionValido()), 200),
        ]);

        $rit = $this->crearRit();
        $servicio = app(RitVideoDidacticoService::class);

        $servicio->generarGuion($rit);
        $servicio->generarGuion($rit, [], regenerar: true);

        $this->assertCount(2, $this->llamadasDeTexto());
    }

    public function test_es_reanudable_no_vuelve_a_pagar_un_clip_ya_generado(): void
    {
        Http::fake([
            '*generateContent*' => Http::response($this->respuestaGeminiTexto($this->guionValido()), 200),
            '*/v1beta/interactions*' => Http::response($this->respuestaGeminiVideo(), 200),
        ]);

        $rit = $this->crearRit();
        $servicio = app(RitVideoDidacticoService::class);

        // Simula que el primer clip ya se generó y pagó en un intento anterior.
        $guion = $servicio->generarGuion($rit);
        $rutaYaGenerada = "rit-videos/{$rit->empresa_id}/video_{$rit->id}_cap1_yaexiste.mp4";
        Storage::disk('local')->put($rutaYaGenerada, 'contenido-ya-generado');
        $guion['clips'][0]['archivo'] = $rutaYaGenerada;
        Storage::disk('local')->put($guion['manifiesto'], json_encode($guion, JSON_UNESCAPED_UNICODE));

        $servicio->generar($rit);

        // 6 clips en total, 1 ya estaba hecho -> solo 5 llamadas nuevas de video.
        $this->assertCount(5, $this->llamadasDeVideo());
        $this->assertSame('contenido-ya-generado', Storage::disk('local')->get($rit->fresh()->video_didactico_capitulos[0]['path']));
    }

    public function test_incluye_el_logo_de_la_empresa_en_cada_clip_de_video(): void
    {
        Http::fake([
            '*generateContent*' => Http::response($this->respuestaGeminiTexto($this->guionValido()), 200),
            '*/v1beta/interactions*' => Http::response($this->respuestaGeminiVideo(), 200),
        ]);
        Storage::disk('local')->put('logos/renbel.png', 'contenido-fake-del-logo');

        $empresa = Empresa::factory()->create(['active' => true, 'logo_path' => 'logos/renbel.png']);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => $this->textoRitRealista(),
        ]);

        app(RitVideoDidacticoService::class)->generar($rit);

        foreach ($this->llamadasDeVideo() as [$req]) {
            $imagen = collect($req->data()['input'] ?? [])->firstWhere('type', 'image');
            $this->assertNotNull($imagen, 'Cada clip debe llevar el logo, no solo el primero.');
            $this->assertSame(base64_encode('contenido-fake-del-logo'), $imagen['data']);
        }
    }

    public function test_la_narracion_del_guion_llega_exacta_al_prompt_del_clip_de_video(): void
    {
        Http::fake([
            '*generateContent*' => Http::response($this->respuestaGeminiTexto($this->guionValido()), 200),
            '*/v1beta/interactions*' => Http::response($this->respuestaGeminiVideo(), 200),
        ]);

        $rit = $this->crearRit();

        app(RitVideoDidacticoService::class)->generar($rit);

        $body = $this->llamadasDeVideo()->values()->get(1)[0]->data();
        $texto = collect($body['input'] ?? [])->firstWhere('type', 'text')['text'] ?? '';
        $this->assertStringContainsString('Su jornada es de cuarenta y ocho horas semanales.', $texto);
    }

    public function test_modo_cambios_usa_el_prompt_de_actualizacion_no_el_de_rit_completo(): void
    {
        $guionCambios = [
            'titulo_serie' => 'x',
            'clips' => [
                $this->clip('apertura', 'Apertura', 'El reglamento se actualizó.'),
                $this->clip('contenido', 'Jornada laboral', 'Ahora la jornada es distinta.', 'La jornada de trabajo es de cuarenta y ocho horas semanales'),
                $this->clip('cierre', 'Cierre', 'Eso fue todo.'),
            ],
        ];

        Http::fake([
            '*generateContent*' => Http::response($this->respuestaGeminiTexto($guionCambios), 200),
            '*/v1beta/interactions*' => Http::response($this->respuestaGeminiVideo(), 200),
        ]);

        $rit = $this->crearRit(fuente: 'mejora_ia');
        $cambios = [
            ['tipo' => 'igual', 'texto' => 'Sin cambios aquí.'],
            [
                'tipo' => 'modificado',
                'palabras' => [
                    ['tipo' => 'igual', 'texto' => 'La jornada es de '],
                    ['tipo' => 'eliminado', 'texto' => '44'],
                    ['tipo' => 'agregado', 'texto' => '48'],
                    ['tipo' => 'igual', 'texto' => ' horas.'],
                ],
            ],
        ];

        app(RitVideoDidacticoService::class)->generar($rit, $cambios);

        $promptGuion = $this->llamadasDeTexto()->first()[0]->data()['contents'][0]['parts'][0]['text'] ?? '';
        $this->assertStringContainsString('MODO: ACTUALIZACIÓN DEL REGLAMENTO', $promptGuion);
        $this->assertStringContainsString('La jornada es de 48 horas.', $promptGuion);
    }

    public function test_archiva_el_video_vigente_antes_de_reemplazarlo(): void
    {
        Http::fake([
            '*generateContent*' => Http::response($this->respuestaGeminiTexto($this->guionValido()), 200),
            '*/v1beta/interactions*' => Http::response($this->respuestaGeminiVideo(), 200),
        ]);

        $rit = $this->crearRit();
        $rit->update([
            'video_didactico_capitulos' => [['titulo' => 'Capítulo viejo', 'path' => 'rit-videos/1/viejo.mp4']],
            'video_didactico_generado_en' => now()->subDay(),
        ]);

        app(RitVideoDidacticoService::class)->generar($rit);

        $this->assertDatabaseCount('historico_videos_didacticos_rit', 1);
        $historico = HistoricoVideoDidacticoRit::first();
        $this->assertSame('Capítulo viejo', $historico->capitulos[0]['titulo']);
    }

    public function test_lanza_excepcion_si_la_api_de_video_falla(): void
    {
        Http::fake([
            '*generateContent*' => Http::response($this->respuestaGeminiTexto($this->guionValido()), 200),
            '*/v1beta/interactions*' => Http::response(['error' => ['message' => 'RESOURCE_EXHAUSTED']], 429),
        ]);

        $rit = $this->crearRit();

        $this->expectException(\RuntimeException::class);

        app(RitVideoDidacticoService::class)->generar($rit);
    }

    public function test_lanza_excepcion_si_el_guion_no_es_json_valido(): void
    {
        Http::fake([
            '*generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'esto no es json']]], 'finishReason' => 'STOP']],
            ], 200),
        ]);

        $rit = $this->crearRit();

        $this->expectException(\RuntimeException::class);

        app(RitVideoDidacticoService::class)->generar($rit);
    }

    public function test_guion_en_texto_produce_texto_legible_para_los_abogados(): void
    {
        $guion = [
            'clips' => [
                ['seccion' => 'Jornada', 'subtitulo' => 'Horario', 'tipo' => 'contenido', 'importancia' => 2, 'narracion' => 'Su jornada es de 48 horas.', 'cita_rit' => 'la jornada es de 48 horas', 'referencia' => 'Artículo 1'],
            ],
        ];

        $texto = app(RitVideoDidacticoService::class)->guionEnTexto($guion);

        $this->assertStringContainsString('Jornada', $texto);
        $this->assertStringContainsString('Su jornada es de 48 horas.', $texto);
        $this->assertStringContainsString('Artículo 1', $texto);
    }
}
