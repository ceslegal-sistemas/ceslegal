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

        Mail::assertSent(RitAceptado::class, function (RitAceptado $mail) use ($empresa) {
            return $mail->hasTo('trabajador@example.com')
                && $mail->nombreTrabajador === 'Con Correo'
                && $mail->nombreEmpresa === 'RENBEL 3.0'
                // Bug real corregido 2026-09-30: el correo nunca recibía la
                // empresa, así que siempre mostraba el rojo genérico de LUPE
                // en vez del logo real de la empresa.
                && $mail->empresa?->id === $empresa->id;
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

    public function test_aceptar_revisa_y_otorga_el_logro_de_100_por_ciento(): void
    {
        $this->seed(\Database\Seeders\LogrosSeeder::class);

        $empresa = \App\Models\Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        \App\Models\ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $trabajador = \App\Models\Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '444555666',
            'genero' => 'masculino', 'nombres' => 'Unico', 'apellidos' => 'Trabajador', 'cargo' => 'Op', 'active' => true,
        ]);

        \Livewire\Livewire::test(\App\Livewire\SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('declaracionAceptada', true)
            ->call('aceptarReglamento');

        $logro = \LevelUp\Experience\Models\Achievement::where('name', \App\Services\LogroSocializacionRitService::NOMBRE_LOGRO)->first();
        $this->assertNotNull($empresa->allAchievements()->find($logro->id));
    }

    /**
     * Bug real reportado por el usuario (2026-09-30): el correo de
     * confirmación mostraba el rojo genérico de LUPE en vez del logo real
     * de la empresa - nunca se le pasaba la empresa al mailable. La ruta
     * pública `logo-empresa.mostrar` ya existía pero nunca se conectó.
     */
    public function test_el_correo_muestra_el_logo_real_de_la_empresa_si_existe(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('logos/renbel.png', 'contenido-fake-del-logo');
        $empresa = Empresa::factory()->create(['active' => true, 'logo_path' => 'logos/renbel.png']);

        $html = (new RitAceptado('Con Logo', $empresa->razon_social, $empresa))->render();

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString(route('logo-empresa.mostrar', ['empresa' => $empresa->id]), $html);
        $this->assertStringNotContainsString('<h2>Reglamento Interno de Trabajo</h2>', $html);
    }

    public function test_el_correo_usa_el_encabezado_generico_si_la_empresa_no_tiene_logo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'logo_path' => null]);

        $html = (new RitAceptado('Sin Logo', $empresa->razon_social, $empresa))->render();

        $this->assertStringContainsString('<h2>Reglamento Interno de Trabajo</h2>', $html);
        $this->assertStringNotContainsString('<img', $html);
    }
}
