<?php

namespace Tests\Feature;

use App\Livewire\SocializacionRit;
use App\Mail\RitAceptado;
use App\Mail\RitPublicacionInformada;
use App\Models\Empresa;
use App\Models\PublicacionReglamentoInterno;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 1 (Publicación) - flujo ligero mientras corren los 15 días hábiles
 * de objeción. Ver docs/superpowers/specs/2026-09-30-socializacion-rit-dos-fases-design.md.
 */
class SocializacionRitFasePublicacionTest extends TestCase
{
    use RefreshDatabase;

    private function empresaEnFasePublicacion(): Empresa
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'Texto vigente.',
            'fecha_publicacion_socializacion' => now()->toDateString(),
        ]);
        return $empresa;
    }

    public function test_un_trabajador_nuevo_en_fase_publicacion_salta_directo_de_datos_a_presentacion(): void
    {
        $empresa = $this->empresaEnFasePublicacion();

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', null)
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '333000333')
            ->set('numeroDocumentoConfirmacion', '333000333')
            ->call('buscarTrabajador')
            ->assertSet('etapa', 'datos')
            ->set('nombres', 'Nuevo')
            ->set('apellidos', 'Publicacion')
            ->set('genero', 'masculino')
            ->set('cargo', '__otro__')
            ->set('cargoPersonalizado', 'Operario')
            ->call('guardarDatos')
            ->assertSet('etapa', 'presentacion_rit')
            ->assertSet('fase', 'publicacion');
    }

    public function test_en_fase_publicacion_el_continuar_de_presentacion_va_directo_a_aceptacion(): void
    {
        $empresa = $this->empresaEnFasePublicacion();
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '444000444',
            'genero' => 'masculino', 'nombres' => 'Ya', 'apellidos' => 'Registrado', 'cargo' => 'Op', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->call('iniciarQuiz')
            ->assertSet('etapa', 'aceptacion')
            ->assertSet('quizPreguntas', []);
    }

    public function test_confirmar_publicacion_crea_el_registro_y_envia_el_correo_ligero(): void
    {
        Mail::fake();
        $empresa = $this->empresaEnFasePublicacion();
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '555000555',
            'genero' => 'femenino', 'nombres' => 'Con', 'apellidos' => 'Correo', 'cargo' => 'Op', 'active' => true,
            'email' => 'trabajador@example.com',
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('email', 'trabajador@example.com')
            ->set('declaracionAceptada', true)
            ->call('confirmarPublicacion')
            ->assertSet('etapa', 'completado');

        $this->assertDatabaseCount('publicaciones_reglamento_interno', 1);
        Mail::assertSent(RitPublicacionInformada::class);
        Mail::assertNotSent(RitAceptado::class);
    }

    public function test_confirmar_publicacion_exige_la_declaracion(): void
    {
        $empresa = $this->empresaEnFasePublicacion();
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '666000666',
            'genero' => 'masculino', 'nombres' => 'Sin', 'apellidos' => 'Declarar', 'cargo' => 'Op', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('declaracionAceptada', false)
            ->call('confirmarPublicacion')
            ->assertHasErrors('declaracionAceptada');

        $this->assertDatabaseCount('publicaciones_reglamento_interno', 0);
    }

    public function test_un_trabajador_que_ya_confirmo_ve_ya_informado_antes_del_limite(): void
    {
        $empresa = $this->empresaEnFasePublicacion();
        $rit = ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')->where('empresa_id', $empresa->id)->first();
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '777000777',
            'genero' => 'masculino', 'nombres' => 'Ya', 'apellidos' => 'Confirmo', 'cargo' => 'Op', 'active' => true,
        ]);
        PublicacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', $rit->texto_completo), 'confirmado_en' => now(),
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '777000777')
            ->set('numeroDocumentoConfirmacion', '777000777')
            ->call('buscarTrabajador')
            ->assertSet('etapa', 'ya_informado');
    }
}
