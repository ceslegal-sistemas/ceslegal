<?php

namespace Tests\Feature;

use App\Livewire\SocializacionRit;
use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\ResumenEjecutivoRitCambio;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Rediseño del visor de cambios del RIT para el trabajador (pedido de
 * Andrés Sarmiento, reunión 2026-10-05): el redline viejo ("todo el
 * documento con lo agregado en verde") se rechazó por feo - ahora se
 * filtra por categoría (Agregado/Eliminado/Modificado) y hay un botón
 * "Resumen ejecutivo" que usa IA para explicar los cambios en lenguaje
 * simple ("legal design").
 */
class SocializacionRitResumenEjecutivoCambiosTest extends TestCase
{
    use RefreshDatabase;

    private function fotoBase64DePrueba(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    /**
     * @return array{0: Empresa, 1: ReglamentoInterno, 2: Trabajador}
     */
    private function crearReaceptacionConCambiosMixtos(): array
    {
        Storage::fake('local');
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritViejo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => false, 'fuente' => 'construido_ia',
            'texto_completo' => "Articulo 1. Jornada de 8 horas.\nArticulo 2. Sin cambios.\nSe debe pagar auxilio de transporte segun la ley vigente para quienes devenguen hasta dos salarios minimos.",
        ]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'mejora_ia',
            'texto_completo' => "Articulo 1. Jornada de 6 horas.\nArticulo 2. Sin cambios.\nLos trabajadores podran laborar desde casa dos dias a la semana previa autorizacion del jefe inmediato.",
        ]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '999000111',
            'genero' => 'masculino', 'nombres' => 'Con', 'apellidos' => 'Cambios', 'cargo' => 'X', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $ritViejo->id,
            'texto_rit_snapshot' => $ritViejo->texto_completo,
            'aceptado_en' => now()->subMonth(),
        ]);

        return [$empresa, $rit, $trabajador];
    }

    public function test_el_redline_muestra_los_3_botones_de_filtro_con_contadores(): void
    {
        [$empresa, $rit, $trabajador] = $this->crearReaceptacionConCambiosMixtos();

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
            ->set('trabajadorId', $trabajador->id)
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba())
            ->assertSee('Agregado')
            ->assertSee('Eliminado')
            ->assertSee('Modificado')
            ->assertSee('laborar desde casa')
            ->assertSee('auxilio de transporte');
    }

    public function test_categoria_sin_cambios_muestra_su_mensaje_de_vacio(): void
    {
        Storage::fake('local');
        $empresa = Empresa::factory()->create(['active' => true]);
        // Solo hay un articulo modificado, nada agregado ni eliminado.
        $ritViejo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => false, 'fuente' => 'construido_ia',
            'texto_completo' => "Articulo 1. Jornada de 8 horas.",
        ]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'mejora_ia',
            'texto_completo' => "Articulo 1. Jornada de 6 horas.",
        ]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '222333444',
            'genero' => 'femenino', 'nombres' => 'Solo', 'apellidos' => 'Modificado', 'cargo' => 'X', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $ritViejo->id,
            'texto_rit_snapshot' => $ritViejo->texto_completo,
            'aceptado_en' => now()->subMonth(),
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
            ->set('trabajadorId', $trabajador->id)
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba())
            ->assertSee('No se agregó nada nuevo.')
            ->assertSee('No se eliminó nada.');
    }

    public function test_boton_resumen_ejecutivo_genera_y_muestra_el_texto_de_la_ia(): void
    {
        [$empresa, $rit, $trabajador] = $this->crearReaceptacionConCambiosMixtos();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => '- Ahora la jornada es de 6 horas, no de 8.',
                    ]]],
                ]],
            ], 200),
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
            ->set('trabajadorId', $trabajador->id)
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba())
            ->assertSet('resumenEjecutivoCambiosRit', '')
            ->call('generarResumenEjecutivoCambiosRit')
            ->assertSet('resumenEjecutivoCambiosRit', '- Ahora la jornada es de 6 horas, no de 8.')
            ->assertSee('Ahora la jornada es de 6 horas');

        $this->assertDatabaseCount('resumenes_ejecutivos_rit_cambios', 1);

        $prompt = Http::recorded()[0][0]->data()['contents'][0]['parts'][0]['text'];
        $this->assertStringContainsString('legal design', $prompt);
    }

    public function test_un_segundo_trabajador_con_el_mismo_diff_reutiliza_el_resumen_cacheado_sin_llamar_a_la_ia(): void
    {
        [$empresa, $rit, $trabajador1] = $this->crearReaceptacionConCambiosMixtos();

        $trabajador2 = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '555666777',
            'genero' => 'femenino', 'nombres' => 'Segundo', 'apellidos' => 'Trabajador', 'cargo' => 'X', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador2->id, 'reglamento_interno_id' => $rit->id,
            'texto_rit_snapshot' => "Articulo 1. Jornada de 8 horas.\nArticulo 2. Sin cambios.\nSe debe pagar auxilio de transporte segun la ley vigente para quienes devenguen hasta dos salarios minimos.",
            'aceptado_en' => now()->subMonth(),
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '- Resumen de cambios.']]]]],
            ], 200),
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
            ->set('trabajadorId', $trabajador1->id)
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba())
            ->call('generarResumenEjecutivoCambiosRit')
            ->assertSet('resumenEjecutivoCambiosRit', '- Resumen de cambios.');

        Http::fake(); // Cualquier llamada nueva a la IA aquí haría fallar assertNothingSent().

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
            ->set('trabajadorId', $trabajador2->id)
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba())
            ->call('generarResumenEjecutivoCambiosRit')
            ->assertSet('resumenEjecutivoCambiosRit', '- Resumen de cambios.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('resumenes_ejecutivos_rit_cambios', 1);
    }

    public function test_doble_clic_no_dispara_dos_llamadas_a_la_ia(): void
    {
        [$empresa, $rit, $trabajador] = $this->crearReaceptacionConCambiosMixtos();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '- Resumen.']]]]],
            ], 200),
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
            ->set('trabajadorId', $trabajador->id)
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba())
            ->call('generarResumenEjecutivoCambiosRit')
            ->call('generarResumenEjecutivoCambiosRit')
            ->assertSet('resumenEjecutivoCambiosRit', '- Resumen.');

        Http::assertSentCount(1);
    }

    public function test_si_la_ia_falla_muestra_mensaje_amigable_y_no_cachea_el_fallo(): void
    {
        [$empresa, $rit, $trabajador] = $this->crearReaceptacionConCambiosMixtos();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa, 'token' => $empresa->tokenSocializacionRit()])
            ->set('trabajadorId', $trabajador->id)
            ->call('guardarFotoSimple', $this->fotoBase64DePrueba())
            ->call('generarResumenEjecutivoCambiosRit')
            ->assertSet('resumenEjecutivoCambiosRit', 'No fue posible generar el resumen ejecutivo en este momento. Puedes revisar el detalle de los cambios arriba.');

        $this->assertDatabaseCount('resumenes_ejecutivos_rit_cambios', 0);
    }
}
