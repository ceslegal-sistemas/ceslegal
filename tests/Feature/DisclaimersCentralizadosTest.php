<?php

namespace Tests\Feature;

use App\Livewire\SocializacionRit;
use App\Models\ConfiguracionTexto;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Auditoría de disclaimers (pedido de Andrés Sarmiento, 2026-10-03): 3
 * disclaimers que vivían hardcodeados directo en Blade se centralizaron en
 * configuraciones_textos, igual que disclaimer_descargos. Estos tests
 * verifican que el valor en BD realmente se usa (no solo que no revienta).
 */
class DisclaimersCentralizadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_disclaimer_rit_publicacion_se_lee_de_configuraciones_textos(): void
    {
        ConfiguracionTexto::where('clave', 'disclaimer_rit_publicacion')->update([
            'valor' => 'Texto editado a mano para **:empresa**.',
        ]);

        // "ACME SAS" (no "ACMESA" ni similar) a propósito con un nombre que
        // NO termine en un tipo societario reconocido (SAS, LTDA, S.A....) -
        // Empresa::razonSocial() (mutator) le quita ese sufijo al guardar
        // (comportamiento intencional, ver Empresa::TIPO_SOCIETARIO_PATRON),
        // así que "ACME SAS" se habría guardado como solo "ACME".
        $empresa = Empresa::factory()->create(['active' => true, 'razon_social' => 'ACME DEMO']);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->toDateString(),
        ]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '999000111',
            'genero' => 'masculino', 'nombres' => 'Edit', 'apellidos' => 'Config', 'cargo' => 'Op', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->assertSee('Texto editado a mano para')
            ->assertSee('ACME DEMO');
    }

    public function test_disclaimer_rit_socializacion_se_lee_de_configuraciones_textos(): void
    {
        ConfiguracionTexto::where('clave', 'disclaimer_rit_socializacion')->update([
            'valor' => 'Otro texto editado para **:empresa**.',
        ]);

        $empresa = Empresa::factory()->create(['active' => true, 'razon_social' => 'BETA DEMO']);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '999000222',
            'genero' => 'masculino', 'nombres' => 'Edit', 'apellidos' => 'ConfigDos', 'cargo' => 'Op', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->assertSee('Otro texto editado para')
            ->assertSee('BETA DEMO');
    }

    public function test_disclaimer_autorizador_se_lee_de_configuraciones_textos(): void
    {
        ConfiguracionTexto::where('clave', 'disclaimer_autorizador')->update([
            'valor' => 'Texto de autorizador editado a mano.',
        ]);

        $html = view('filament.components.webcam-autorizador')->render();

        $this->assertStringContainsString('Texto de autorizador editado a mano.', $html);
    }
}
