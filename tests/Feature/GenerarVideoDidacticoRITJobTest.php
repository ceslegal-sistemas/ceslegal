<?php

namespace Tests\Feature;

use App\Jobs\GenerarVideoDidacticoRITJob;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerarVideoDidacticoRITJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Rediseño 2026-10-14 (RitVideoDidacticoService): ya no basta con
     * mockear una sola respuesta de video para cualquier URL - el servicio
     * primero llama al modelo de TEXTO (generateContent, el guionista) y
     * solo después al de VIDEO (interactions) por cada clip del guion ya
     * verificado. texto_completo también necesita ser realista (>500
     * caracteres): el guionista exige un mínimo para poder trabajar.
     */
    public function test_handle_genera_el_video_y_actualiza_el_rit(): void
    {
        $guion = [
            'titulo_serie' => 'Conozca su Reglamento',
            'clips' => [
                ['seccion' => 'Apertura', 'subtitulo' => '', 'tipo' => 'apertura', 'importancia' => 1, 'narracion' => 'Bienvenido a su Reglamento Interno.', 'escena' => '', 'cita_rit' => '', 'referencia' => null],
                ['seccion' => 'Jornada laboral', 'subtitulo' => 'Horario', 'tipo' => 'contenido', 'importancia' => 2, 'narracion' => 'Su jornada es de cuarenta y ocho horas semanales.', 'escena' => '', 'cita_rit' => 'La jornada de trabajo es de cuarenta y ocho horas semanales', 'referencia' => 'Artículo 1'],
                ['seccion' => 'Obligaciones', 'subtitulo' => 'Horario', 'tipo' => 'contenido', 'importancia' => 2, 'narracion' => 'Debe reportar cualquier ausencia con anticipación.', 'escena' => '', 'cita_rit' => 'reportar cualquier ausencia con anticipación a su jefe inmediato', 'referencia' => 'Artículo 15'],
                ['seccion' => 'Faltas leves', 'subtitulo' => 'Llegadas tarde', 'tipo' => 'contenido', 'importancia' => 3, 'narracion' => 'Llegar tarde sin justificación es una falta leve.', 'escena' => '', 'cita_rit' => 'la llegada tardía reiterada sin justificación', 'referencia' => 'Artículo 60'],
                ['seccion' => 'Procedimiento disciplinario', 'subtitulo' => 'Descargos', 'tipo' => 'contenido', 'importancia' => 3, 'narracion' => 'Siempre se le citará a dar su versión de los hechos.', 'escena' => '', 'cita_rit' => 'podrá presentar su versión de los hechos', 'referencia' => 'Artículo 70'],
                ['seccion' => 'Cierre', 'subtitulo' => '', 'tipo' => 'cierre', 'importancia' => 1, 'narracion' => 'Gracias por su atención.', 'escena' => '', 'cita_rit' => '', 'referencia' => null],
            ],
        ];

        Http::fake(function ($request) use ($guion) {
            if (str_contains($request->url(), '/v1beta/interactions')) {
                return Http::response([
                    'id' => 'v1_test',
                    'status' => 'completed',
                    'steps' => [['type' => 'model_output', 'content' => [['type' => 'video', 'data' => 'ZmFrZQ==']]]],
                ], 200);
            }
            if (str_contains(json_encode($request->data()), 'guionista de contenidos educativos')) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => json_encode($guion, JSON_UNESCAPED_UNICODE)]]], 'finishReason' => 'STOP']],
                ], 200);
            }

            // Clasificador de temas de ReglamentoInterno::create() - no es parte de este test.
            return Http::response(['candidates' => [['content' => ['parts' => [['text' => '[]']]], 'finishReason' => 'STOP']]], 200);
        });

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia',
            'texto_completo' => 'REGLAMENTO INTERNO DE TRABAJO. ARTÍCULO 1. JORNADA LABORAL. La jornada de trabajo es de cuarenta y ocho horas semanales, distribuidas de lunes a viernes, con un descanso de una hora para el almuerzo. '
                . 'ARTÍCULO 15. OBLIGACIONES DEL TRABAJADOR. El trabajador debe cumplir con el horario establecido y reportar cualquier ausencia con anticipación a su jefe inmediato. '
                . 'ARTÍCULO 60. FALTAS LEVES. Constituye falta leve la llegada tardía reiterada sin justificación, la cual será sancionada con llamado de atención escrito en la hoja de vida laboral. '
                . 'ARTÍCULO 70. PROCEDIMIENTO DISCIPLINARIO. Antes de aplicar cualquier sanción, la empresa citará al trabajador a diligencia de descargos, donde podrá presentar su versión de los hechos.',
            'video_didactico_estado' => 'generando',
        ]);
        $user = User::factory()->create();

        (new GenerarVideoDidacticoRITJob($rit, $user->id))->handle(app(\App\Services\RitVideoDidacticoService::class));

        $rit->refresh();
        $this->assertSame('completado', $rit->video_didactico_estado);
        // video_didactico_path quedó obsoleto con el rediseño de "capítulos
        // independientes" (commit cf2c2c18) - RitVideoDidacticoService::generar()
        // ahora SIEMPRE lo deja en null y guarda la ruta de cada capítulo en
        // video_didactico_capitulos. Este test nunca se actualizó - lo
        // corrijo aquí (encontrado en la pasada de repaso, 2026-10-03).
        $this->assertNotEmpty($rit->video_didactico_capitulos);
        $this->assertNotEmpty($rit->video_didactico_capitulos[0]['path'] ?? null);
    }

    public function test_failed_marca_el_rit_con_error_y_notifica(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'video_didactico_estado' => 'generando',
        ]);
        $user = User::factory()->create();

        $job = new GenerarVideoDidacticoRITJob($rit, $user->id);
        $job->failed(new \RuntimeException('cuota agotada'));

        $rit->refresh();
        $this->assertSame('error', $rit->video_didactico_estado);
        $this->assertSame('cuota agotada', $rit->video_didactico_error);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
    }
}
