<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReglamentoInternoInvalidaCacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug real reportado por el usuario: al mejorar/actualizar un RIT (o
     * volver a subirlo), sanciones_extraidas y conductas_sancionables
     * quedaban con datos de la versión VIEJA para siempre - nada los
     * invalidaba, así que el sistema nunca volvía a extraer por su cuenta
     * (afectaba tanto "Mi Reglamento Interno" como la cláusula de Faltas
     * Graves del contrato). Http::fake() evita que el clasificador de temas
     * (que también corre en este mismo evento) llame a Gemini de verdad.
     */
    public function test_actualizar_el_texto_del_rit_invalida_las_conductas_ya_calculadas(): void
    {
        Http::fake();

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Texto original del RIT con su capítulo de faltas.',
            'sanciones_extraidas' => ['faltas_leves' => ['x'], 'faltas_graves' => ['y'], 'faltas_muy_graves' => []],
            'conductas_sancionables' => ['leve' => [['conducta' => 'x']], 'grave' => [['conducta' => 'y']], 'gravisima' => []],
            'organigrama' => [['nombre_cargo' => 'Gerente', 'instancia_sancionatoria' => 'primera_instancia']],
        ]);

        $rit->update(['texto_completo' => 'Texto NUEVO y distinto del RIT, versión mejorada.']);
        $rit->refresh();

        $this->assertNull($rit->sanciones_extraidas);
        $this->assertNull($rit->conductas_sancionables);
        $this->assertNull($rit->organigrama);
    }

    public function test_actualizar_un_campo_distinto_a_texto_completo_no_invalida_nada(): void
    {
        Http::fake();

        $empresa = Empresa::factory()->create(['active' => true]);
        // texto_completo se crea SOLO (como en la vida real: al crear el
        // registro las conductas todavía no existen, se calculan después
        // de forma perezosa) - si se pusieran ambos en el mismo create(),
        // texto_completo ya cuenta como "dirty" en el insert y el nuevo
        // guard invalidaría de entrada, que es justamente lo correcto
        // cuando de verdad se crea un RIT nuevo con texto nuevo.
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Texto del RIT.',
        ]);
        $rit->conductas_sancionables = ['leve' => [], 'grave' => [['conducta' => 'y']], 'gravisima' => []];
        $rit->saveQuietly();

        $rit->update(['nombre' => 'Nuevo nombre del archivo']);
        $rit->refresh();

        $this->assertNotNull($rit->conductas_sancionables);
        $this->assertSame('y', $rit->conductas_sancionables['grave'][0]['conducta']);
    }
}
