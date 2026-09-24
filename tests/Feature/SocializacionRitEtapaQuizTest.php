<?php

namespace Tests\Feature;

use App\Livewire\SocializacionRit;
use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\TemaNormativo;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Quiz de comprensión (mejora "excéntrica" pedida por el usuario,
 * 2026-09-23): el trabajador debe responder bien 3 preguntas V/F sobre lo
 * que el RIT dice de verdad, antes de poder aceptar - refuerzo de
 * comprensión informada, no solo un clic. Fail-open: sin preguntas
 * generadas, la etapa se salta entera.
 */
class SocializacionRitEtapaQuizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mismo patron que SocializacionRitEtapaFotoTest: guardarFotoSimple()
        // escribe un archivo real en storage/app/private - sin esto, cada
        // corrida de este test ensucia el disco real con fotos de prueba.
        \Illuminate\Support\Facades\Storage::fake('local');
    }

    private function crearRitConTemas(int $cantidadPreguntas): array
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);

        $temas = [];
        for ($i = 1; $i <= $cantidadPreguntas; $i++) {
            $tema = TemaNormativo::create(['nombre' => "Tema {$i}", 'descripcion' => 'Desc.', 'activo' => true]);
            $rit->temasNormativos()->attach($tema->id, [
                'pregunta_vf' => "¿Pregunta {$i}?",
                'respuesta_correcta' => true,
            ]);
            $temas[] = $tema;
        }

        return [$empresa, $rit, $temas];
    }

    private function llevarATrabajadorAPresentacionRit(Empresa $empresa): object
    {
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '123456789',
            'genero' => 'masculino', 'nombres' => 'Juan', 'apellidos' => 'Perez', 'cargo' => 'Operario', 'active' => true,
        ]);

        return Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->call('guardarFotoSimple', $this->fotoBase64Valida());
    }

    private function fotoBase64Valida(): string
    {
        // 1x1 pixel PNG válido, mismo fixture usado en SocializacionRitEtapaFotoTest.
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    public function test_al_salir_de_presentacion_rit_entra_al_quiz_si_hay_preguntas(): void
    {
        [$empresa, $rit] = $this->crearRitConTemas(3);

        $this->llevarATrabajadorAPresentacionRit($empresa)
            ->call('iniciarQuiz')
            ->assertSet('etapa', 'quiz');
    }

    public function test_sin_preguntas_disponibles_salta_directo_a_aceptacion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);

        $this->llevarATrabajadorAPresentacionRit($empresa)
            ->call('iniciarQuiz')
            ->assertSet('etapa', 'aceptacion');
    }

    public function test_responder_bien_avanza_a_la_siguiente_pregunta(): void
    {
        [$empresa, $rit] = $this->crearRitConTemas(3);

        $this->llevarATrabajadorAPresentacionRit($empresa)
            ->call('iniciarQuiz')
            ->call('responderQuiz', true)
            ->assertSet('quizIndiceActual', 1);
    }

    public function test_responder_mal_no_avanza_y_marca_el_error(): void
    {
        [$empresa, $rit] = $this->crearRitConTemas(3);

        $this->llevarATrabajadorAPresentacionRit($empresa)
            ->call('iniciarQuiz')
            ->call('responderQuiz', false)
            ->assertSet('quizIndiceActual', 0)
            ->assertSet('quizRespuestaIncorrecta', true);
    }

    public function test_completar_las_3_preguntas_avanza_a_aceptacion(): void
    {
        [$empresa, $rit] = $this->crearRitConTemas(3);

        $componente = $this->llevarATrabajadorAPresentacionRit($empresa)->call('iniciarQuiz');

        for ($i = 0; $i < 3; $i++) {
            $componente->call('responderQuiz', true);
        }

        $componente->assertSet('etapa', 'aceptacion');
    }
}
