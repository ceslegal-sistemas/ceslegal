<?php

namespace Tests\Feature;

use App\Livewire\SocializacionRit;
use App\Mail\RitNoComprendidoAlertaRrhh;
use App\Models\Empresa;
use App\Models\RechazoComprensionRit;
use App\Models\ReglamentoInterno;
use App\Models\TemaNormativo;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Escalamiento a RRHH si el trabajador insiste en "no entendí" el RIT
 * (pedido de Andrés Sarmiento, reunión 2026-10-03). Primera vez: aviso
 * suave, sigue en el proceso. Segunda vez (manual o por fallar la misma
 * pregunta del quiz 3 veces seguidas): bloquea y avisa a RRHH por correo.
 */
class SocializacionRitNoComprendioTest extends TestCase
{
    use RefreshDatabase;

    private function crearEmpresaTrabajadorEnFaseSocializacion(): array
    {
        $empresa = Empresa::factory()->create(['active' => true, 'email_contacto' => 'rrhh@empresa-test.com']);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '777888999',
            'genero' => 'masculino', 'nombres' => 'Carlos', 'apellidos' => 'Duda', 'cargo' => 'Op', 'active' => true,
        ]);

        return [$empresa, $rit, $trabajador];
    }

    public function test_primera_vez_muestra_aviso_y_no_bloquea(): void
    {
        [$empresa, $rit, $trabajador] = $this->crearEmpresaTrabajadorEnFaseSocializacion();
        Mail::fake();

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->call('marcarNoComprendio', 'boton_manual')
            ->assertSet('vecesNoComprendio', 1)
            ->assertSet('etapa', 'aceptacion');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('rechazos_comprension_rit', 1);
    }

    public function test_segunda_vez_bloquea_y_avisa_a_rrhh(): void
    {
        [$empresa, $rit, $trabajador] = $this->crearEmpresaTrabajadorEnFaseSocializacion();
        Mail::fake();

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('nombres', 'Carlos')
            ->set('apellidos', 'Duda')
            ->set('numeroDocumento', '777888999')
            ->set('etapa', 'aceptacion')
            ->call('marcarNoComprendio', 'boton_manual')
            ->call('marcarNoComprendio', 'boton_manual')
            ->assertSet('vecesNoComprendio', 2)
            ->assertSet('etapa', 'no_comprendido_bloqueado');

        Mail::assertSent(RitNoComprendidoAlertaRrhh::class, function (RitNoComprendidoAlertaRrhh $mail) use ($empresa) {
            return $mail->hasTo('rrhh@empresa-test.com')
                && $mail->nombreTrabajador === 'Carlos Duda'
                && $mail->empresa?->id === $empresa->id;
        });
        $this->assertDatabaseCount('rechazos_comprension_rit', 2);
    }

    public function test_no_falla_si_la_empresa_no_tiene_email_contacto(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'email_contacto' => '']);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '111000999',
            'genero' => 'masculino', 'nombres' => 'Sin', 'apellidos' => 'Correo', 'cargo' => 'Op', 'active' => true,
        ]);
        Mail::fake();

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->call('marcarNoComprendio', 'boton_manual')
            ->call('marcarNoComprendio', 'boton_manual')
            ->assertSet('etapa', 'no_comprendido_bloqueado');

        Mail::assertNothingSent();
    }

    /**
     * Fallar la MISMA pregunta 3 veces seguidas cuenta como la 1ra señal de
     * "no comprendió", igual que el botón manual.
     */
    public function test_fallar_la_misma_pregunta_3_veces_dispara_el_primer_aviso(): void
    {
        [$empresa, $rit, $trabajador] = $this->crearEmpresaTrabajadorEnFaseSocializacion();
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, ['pregunta_vf' => '¿Pregunta?', 'respuesta_correcta' => true]);
        Storage::fake('local');

        $componente = Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
            ->set('trabajadorId', $trabajador->id)
            ->call('guardarFotoSimple', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ->call('iniciarQuiz')
            ->call('responderQuiz', false)
            ->call('responderQuiz', false)
            ->assertSet('vecesNoComprendio', 0)
            ->call('responderQuiz', false)
            ->assertSet('vecesNoComprendio', 1);

        $this->assertDatabaseHas('rechazos_comprension_rit', ['origen' => 'fallo_quiz_repetido']);
    }

    /**
     * Pedido explícito del usuario (2026-10-05): el aviso del 1er "no
     * entendí" recomendaba repasar el material pero no existía ningún botón
     * para volver a verlo - volverARevisar() debe mandar de vuelta a
     * 'presentacion_rit' y reiniciar la casilla de aceptación (para que la
     * reconfirme de forma consciente tras repasar).
     */
    public function test_volver_a_revisar_regresa_a_presentacion_rit_y_reinicia_la_declaracion(): void
    {
        [$empresa, $rit, $trabajador] = $this->crearEmpresaTrabajadorEnFaseSocializacion();

        // La vista de 'presentacion_rit' necesita un $token real para armar
        // la URL del video didáctico (rit.socializar.video) - sin esto,
        // Livewire falla al re-renderizar tras el cambio de etapa.
        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->set('declaracionAceptada', true)
            ->call('volverARevisar')
            ->assertSet('etapa', 'presentacion_rit')
            ->assertSet('declaracionAceptada', false);
    }
}
