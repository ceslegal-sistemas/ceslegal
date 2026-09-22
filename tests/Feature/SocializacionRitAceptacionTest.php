<?php

namespace Tests\Feature;

use App\Mail\RitAceptado;
use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Livewire\SocializacionRit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SocializacionRitAceptacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_aceptar_crea_el_registro_con_ip_y_pasa_a_completado(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '343434343',
            'genero' => 'masculino', 'nombres' => 'Acepta', 'apellidos' => 'Ya', 'cargo' => 'X', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->set('declaracionAceptada', true)
            ->call('aceptarReglamento')
            ->assertSet('etapa', 'completado');

        $this->assertDatabaseHas('aceptaciones_reglamento_interno', [
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
        ]);
    }

    public function test_no_permite_aceptar_sin_marcar_la_declaracion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '454545454',
            'genero' => 'femenino', 'nombres' => 'No', 'apellidos' => 'Acepta', 'cargo' => 'X', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->set('declaracionAceptada', false)
            ->call('aceptarReglamento')
            ->assertHasErrors('declaracionAceptada')
            ->assertSet('etapa', 'aceptacion');

        $this->assertDatabaseCount('aceptaciones_reglamento_interno', 0);
    }

    /**
     * Pedido explícito del usuario (2026-09-22): al terminar y aceptar se
     * le debe enviar un correo al trabajador.
     */
    public function test_envia_correo_de_confirmacion_al_aceptar(): void
    {
        Mail::fake();
        $empresa = Empresa::factory()->create(['active' => true, 'razon_social' => 'RENBEL 3.0']);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '565656565',
            'genero' => 'masculino', 'nombres' => 'Con', 'apellidos' => 'Correo', 'cargo' => 'X', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->set('nombres', 'Con')
            ->set('apellidos', 'Correo')
            ->set('email', 'trabajador@example.com')
            ->set('declaracionAceptada', true)
            ->call('aceptarReglamento')
            ->assertSet('etapa', 'completado');

        Mail::assertSent(RitAceptado::class, function (RitAceptado $mail) {
            return $mail->hasTo('trabajador@example.com')
                && $mail->nombreTrabajador === 'Con Correo'
                && $mail->nombreEmpresa === 'RENBEL 3.0';
        });
    }

    public function test_no_falla_el_registro_si_no_hay_correo(): void
    {
        Mail::fake();
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '676767676',
            'genero' => 'femenino', 'nombres' => 'Sin', 'apellidos' => 'Correo', 'cargo' => 'X', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->set('declaracionAceptada', true)
            ->call('aceptarReglamento')
            ->assertSet('etapa', 'completado');

        Mail::assertNothingSent();
    }
}
