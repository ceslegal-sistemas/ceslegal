<?php

namespace Tests\Feature;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Livewire\SocializacionRit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocializacionRitPresentacionRitTest extends TestCase
{
    use RefreshDatabase;

    private function fotoBase64DePrueba(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    /**
     * IMPORTANTE: estos tests pasan por guardarFotoSimple() (el metodo real
     * de la Task 8, que es donde se calcula esPrimeraAceptacion/cambiosRit),
     * en vez de hacer ->set('etapa', 'presentacion_rit') directamente.
     * Livewire NO recalcula nada automaticamente solo por cambiar $etapa a
     * mano en un test - si el test no pasa por el metodo real que hace el
     * calculo, esPrimeraAceptacion se queda en su valor por defecto (true)
     * sin importar el escenario, y el test "pasaria" sin probar nada real.
     */
    public function test_primera_aceptacion_muestra_el_rit_completo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'Texto completo del RIT.']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '121212121',
            'genero' => 'masculino', 'nombres' => 'Nuevo', 'apellidos' => 'Trabajador', 'cargo' => 'X', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba())
            ->assertSet('etapa', 'presentacion_rit')
            ->assertSet('esPrimeraAceptacion', true)
            ->assertSee('Texto completo del RIT');
    }

    public function test_reaceptacion_muestra_el_diff_no_el_texto_completo_sin_marcar(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritViejo = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => false, 'fuente' => 'construido_ia', 'texto_completo' => "Articulo 1. Version vieja.\nArticulo 2. Sin cambios."]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'mejora_ia', 'texto_completo' => "Articulo 1. Version nueva mejorada.\nArticulo 2. Sin cambios."]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '232323232',
            'genero' => 'femenino', 'nombres' => 'Ya', 'apellidos' => 'Acepto', 'cargo' => 'X', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $ritViejo->id, 'aceptado_en' => now()->subMonth(),
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba())
            ->assertSet('etapa', 'presentacion_rit')
            ->assertSet('esPrimeraAceptacion', false);
    }

    /**
     * Hallazgo de la 2da ronda de revision del plan: el calculo del diff
     * resolvia $ultimaAceptacion->reglamentoInterno (belongsTo) SIN bypass
     * del scope - mismo riesgo del Gotcha critico #3, esta vez en una
     * relacion belongsTo, no hasOne. Este test reproduce el escenario real
     * (mismo patron que
     * test_bufete_con_otra_empresa_activa_en_sesion_no_interfiere de
     * SocializacionRitEtapaDocumentoTest): una sesion de bufete con OTRA
     * empresa activa no debe impedir calcular el diff de la empresa real
     * del token.
     */
    public function test_reaceptacion_calcula_el_diff_aunque_haya_otra_empresa_activa_en_sesion(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $empresaDelToken = Empresa::factory()->create(['active' => true]);
        $ritViejo = ReglamentoInterno::create(['empresa_id' => $empresaDelToken->id, 'activo' => false, 'fuente' => 'construido_ia', 'texto_completo' => "Articulo 1. Version vieja."]);
        ReglamentoInterno::create(['empresa_id' => $empresaDelToken->id, 'activo' => true, 'fuente' => 'mejora_ia', 'texto_completo' => "Articulo 1. Version nueva mejorada."]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresaDelToken->id, 'tipo_documento' => 'CC', 'numero_documento' => '454545454',
            'genero' => 'masculino', 'nombres' => 'Reacepta', 'apellidos' => 'ConOtraSesion', 'cargo' => 'X', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $ritViejo->id, 'aceptado_en' => now()->subMonth(),
        ]);

        $otraEmpresa = Empresa::factory()->create(['active' => true]);
        $bufete = \App\Models\Bufete::factory()->create();
        $usuarioBufete = \App\Models\User::factory()->create(['role' => 'bufete', 'bufete_id' => $bufete->id, 'active' => true]);
        $this->actingAs($usuarioBufete);
        \App\Support\EmpresaActiva::set($otraEmpresa->id);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresaDelToken])
            ->set('trabajadorId', $trabajador->id)
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba())
            ->assertSet('etapa', 'presentacion_rit')
            ->assertSet('esPrimeraAceptacion', false)
            ->assertSet('cambiosRit', fn ($cambios) => !empty($cambios));
    }
}
