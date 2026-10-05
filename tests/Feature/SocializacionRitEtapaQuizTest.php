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
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
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

        return Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
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

    public function test_sin_preguntas_disponibles_salta_directo_a_foto_aceptacion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);

        $this->llevarATrabajadorAPresentacionRit($empresa)
            ->call('iniciarQuiz')
            ->assertSet('etapa', 'foto_aceptacion');
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

    public function test_completar_las_3_preguntas_avanza_a_foto_aceptacion(): void
    {
        [$empresa, $rit] = $this->crearRitConTemas(3);

        $componente = $this->llevarATrabajadorAPresentacionRit($empresa)->call('iniciarQuiz');

        for ($i = 0; $i < 3; $i++) {
            $componente->call('responderQuiz', true);
        }

        $componente->assertSet('etapa', 'foto_aceptacion');
    }

    /**
     * Evidencia jurídica (2026-09-28): cada intento (fallido o exitoso)
     * queda registrado con su timestamp en $quizRespuestas - no solo el
     * estado transitorio de la pregunta actual.
     */
    public function test_registra_cada_intento_en_quizrespuestas_con_timestamp(): void
    {
        [$empresa, $rit] = $this->crearRitConTemas(1);

        $componente = $this->llevarATrabajadorAPresentacionRit($empresa)
            ->call('iniciarQuiz')
            ->call('responderQuiz', false)
            ->call('responderQuiz', true);

        $respuestas = $componente->get('quizRespuestas');

        $this->assertCount(2, $respuestas[0]['intentos']);
        $this->assertFalse($respuestas[0]['intentos'][0]['correcta']);
        $this->assertTrue($respuestas[0]['intentos'][1]['correcta']);
        $this->assertNotEmpty($respuestas[0]['intentos'][0]['respondido_en']);
    }

    /**
     * Bug real reportado por el usuario (2026-10-03): a un trabajador
     * hombre le salió la pregunta de quiz sobre permisos de lactancia
     * ("...para amamantar a tu hijo..."), redactada siempre en segunda
     * persona como si quien responde fuera la madre. Ese tema no debe
     * aparecer en el banco de preguntas de un trabajador que no es mujer.
     */
    public function test_pregunta_de_lactancia_no_sale_para_trabajador_no_femenino(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);

        $temaLactancia = TemaNormativo::create(['nombre' => 'Protección a la mujer embarazada y lactancia', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($temaLactancia->id, [
            'pregunta_vf' => 'RENBEL S.A.S. te dará dos descansos de 30 minutos cada uno para amamantar a tu hijo durante los primeros 12 meses de edad del bebé.',
            'respuesta_correcta' => true,
        ]);
        $otroTema = TemaNormativo::create(['nombre' => 'Jornada laboral y horas extras', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($otroTema->id, [
            'pregunta_vf' => '¿La jornada maxima es de 8 horas diarias?',
            'respuesta_correcta' => true,
        ]);

        // $trabajador por defecto en llevarATrabajadorAPresentacionRit() ya es 'masculino'.
        $componente = $this->llevarATrabajadorAPresentacionRit($empresa)->call('iniciarQuiz');

        $preguntas = collect($componente->get('quizPreguntas'))->pluck('pregunta');
        $this->assertFalse($preguntas->contains(fn ($p) => str_contains($p, 'amamantar')));
    }

    /**
     * Selección múltiple (pedido de Andrés Sarmiento, 2026-10-03): el quiz
     * ahora puede mezclar preguntas V/F con preguntas de 4 opciones.
     */
    public function test_pregunta_de_seleccion_multiple_se_responde_con_el_indice(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Vacaciones', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, [
            'pregunta_vf' => '¿Cuántos días de vacaciones da la empresa al año?',
            'tipo_pregunta' => 'multiple',
            'opciones' => ['10 días', '15 días', '20 días', '30 días'],
            'respuesta_correcta_indice' => 2,
        ]);

        $componente = $this->llevarATrabajadorAPresentacionRit($empresa)->call('iniciarQuiz');

        $componente->assertSet('quizPreguntas.0.tipo', 'multiple')
            ->assertSet('quizPreguntas.0.opciones', ['10 días', '15 días', '20 días', '30 días']);

        // Respuesta incorrecta: no avanza, marca el error.
        $componente->call('responderQuizMultiple', 0)
            ->assertSet('quizIndiceActual', 0)
            ->assertSet('quizRespuestaIncorrecta', true);

        // Respuesta correcta (índice 2): avanza y, al ser la única pregunta, pasa a foto_aceptacion.
        $componente->call('responderQuizMultiple', 2)
            ->assertSet('etapa', 'foto_aceptacion');

        $respuestas = $componente->get('quizRespuestas');
        $this->assertCount(2, $respuestas[0]['intentos']);
        $this->assertFalse($respuestas[0]['intentos'][0]['correcta']);
        $this->assertTrue($respuestas[0]['intentos'][1]['correcta']);
    }

    /**
     * Preguntas solo-de-lo-que-cambió en actualizaciones (pedido de Andrés
     * Sarmiento, 2026-10-03): si el trabajador ya había aceptado una versión
     * anterior (texto_rit_snapshot distinto del actual), el quiz solo debe
     * salir de los temas que la IA identificó como afectados por el cambio -
     * no de todos los temas del RIT.
     */
    public function test_en_una_actualizacion_el_quiz_solo_sale_de_los_temas_que_cambiaron(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'mejora_ia',
            'texto_completo' => "Articulo 1. La jornada ahora es de 7 horas.\nArticulo 2. Sin cambios.",
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $temaCambiado = TemaNormativo::create(['nombre' => 'Jornada laboral y horas extras', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($temaCambiado->id, ['pregunta_vf' => '¿La jornada es de 7 horas?', 'respuesta_correcta' => true]);
        $temaSinCambios = TemaNormativo::create(['nombre' => 'Vacaciones', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($temaSinCambios->id, ['pregunta_vf' => '¿Las vacaciones son de 15 días?', 'respuesta_correcta' => true]);

        \Illuminate\Support\Facades\Http::fake([
            'generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([$temaCambiado->id]),
                    ]]],
                ]],
            ], 200),
        ]);

        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '222333444',
            'genero' => 'masculino', 'nombres' => 'Ya', 'apellidos' => 'Acepto', 'cargo' => 'Op', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'texto_rit_snapshot' => "Articulo 1. La jornada es de 8 horas.\nArticulo 2. Sin cambios.",
            'texto_rit_hash' => hash('sha256', "Articulo 1. La jornada es de 8 horas.\nArticulo 2. Sin cambios."),
            'aceptado_en' => now()->subMonth(),
        ]);

        $componente = Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
            ->set('trabajadorId', $trabajador->id)
            ->call('guardarFotoSimple', $this->fotoBase64Valida())
            ->assertSet('esPrimeraAceptacion', false)
            ->call('iniciarQuiz');

        $preguntas = collect($componente->get('quizPreguntas'))->pluck('pregunta');
        $this->assertTrue($preguntas->contains('¿La jornada es de 7 horas?'));
        $this->assertFalse($preguntas->contains('¿Las vacaciones son de 15 días?'));
    }

    private function crearRitConTemasMixtos(Empresa $empresa, ReglamentoInterno $rit): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $tema = TemaNormativo::create(['nombre' => "Multiple {$i}", 'descripcion' => 'Desc.', 'activo' => true]);
            $rit->temasNormativos()->attach($tema->id, [
                'pregunta_vf' => "¿Pregunta múltiple {$i}?",
                'tipo_pregunta' => 'multiple',
                'opciones' => ['a', 'b', 'c', 'd'],
                'respuesta_correcta_indice' => 0,
            ]);
        }
        for ($i = 1; $i <= 5; $i++) {
            $tema = TemaNormativo::create(['nombre' => "VF {$i}", 'descripcion' => 'Desc.', 'activo' => true]);
            $rit->temasNormativos()->attach($tema->id, [
                'pregunta_vf' => "¿Pregunta vf {$i}?",
                'respuesta_correcta' => true,
            ]);
        }
    }

    /**
     * Pedido explícito del usuario (2026-10-05): el quiz ya no puede quedar
     * a lo que la IA haya elegido libremente por tema - siempre debe traer
     * exactamente 3 de selección múltiple y 2 de Sí/No, cuando el banco
     * tiene suficiente variedad de ambos tipos.
     */
    public function test_el_quiz_siempre_trae_exactamente_3_multiples_y_2_vf(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $this->crearRitConTemasMixtos($empresa, $rit);

        $componente = $this->llevarATrabajadorAPresentacionRit($empresa)->call('iniciarQuiz');

        $preguntas = collect($componente->get('quizPreguntas'));
        $this->assertCount(5, $preguntas);
        $this->assertCount(3, $preguntas->where('tipo', 'multiple'));
        $this->assertCount(2, $preguntas->where('tipo', 'vf'));
    }

    /**
     * Pedido explícito del usuario (2026-10-05): "no debe salir primero 3 de
     * selección múltiple y 2 de sí/no" - el orden de las 5 preguntas debe
     * ser aleatorio, no un bloque fijo. Se corre el quiz muchas veces y se
     * verifica que la PRIMERA pregunta no siempre es del mismo tipo - con 25
     * corridas independientes la probabilidad de que salga siempre el mismo
     * tipo por puro azar es prácticamente cero.
     */
    public function test_el_orden_de_las_preguntas_es_aleatorio(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $this->crearRitConTemasMixtos($empresa, $rit);

        $tiposEnPrimeraPosicion = [];
        for ($intento = 0; $intento < 25; $intento++) {
            $trabajador = Trabajador::create([
                'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => (string) (900000000 + $intento),
                'genero' => 'masculino', 'nombres' => 'Juan', 'apellidos' => 'Perez', 'cargo' => 'Operario', 'active' => true,
            ]);

            $componente = Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
                ->set('trabajadorId', $trabajador->id)
                ->call('guardarFotoSimple', $this->fotoBase64Valida())
                ->call('iniciarQuiz');

            $tiposEnPrimeraPosicion[] = $componente->get('quizPreguntas')[0]['tipo'];
        }

        $this->assertContains('multiple', $tiposEnPrimeraPosicion);
        $this->assertContains('vf', $tiposEnPrimeraPosicion);
    }
}
