<?php

namespace Tests\Feature;

use App\Models\ReglamentoInterno;
use App\Models\TemaNormativo;
use App\Services\TemaClasificadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-22): el resumen que ve el
 * trabajador debe ser lo que ESTE reglamento dice de verdad, generado por
 * IA, no la descripción genérica del tema - "Aquí debe intervenir la IA
 * y hacer todo bien bonito y entendible para cualquier analfabeta".
 */
class TemaClasificadorResumenSimpleTest extends TestCase
{
    use RefreshDatabase;

    private function fakearGemini(array $resumenes): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode($resumenes),
                    ]]],
                ]],
            ], 200),
        ]);
    }

    public function test_genera_y_guarda_el_resumen_simple_por_tema(): void
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => \App\Models\Empresa::factory()->create()->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'Articulo 1. La jornada es de 8 horas diarias.',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Descripción genérica.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id);

        $this->fakearGemini([
            ['id' => $tema->id, 'resumen' => 'Trabajas 8 horas diarias, según el artículo 1 de tu reglamento.'],
        ]);

        app(TemaClasificadorService::class)->asegurarResumenesSimples($rit);

        $pivot = $rit->temasNormativos()->find($tema->id)->pivot;
        $this->assertSame('Trabajas 8 horas diarias, según el artículo 1 de tu reglamento.', $pivot->resumen_simple);
        $this->assertNotNull($rit->fresh()->resumen_simple_texto_hash);
    }

    public function test_no_vuelve_a_llamar_a_la_ia_si_el_texto_no_cambio(): void
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => \App\Models\Empresa::factory()->create()->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'Articulo 1. Texto sin cambios.',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Tema X', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => 'Ya tenia resumen.']);
        $rit->forceFill(['resumen_simple_texto_hash' => hash('sha256', $rit->texto_completo)])->save();

        Http::fake();

        app(TemaClasificadorService::class)->asegurarResumenesSimples($rit);

        Http::assertNothingSent();
    }

    public function test_falla_de_ia_no_rompe_y_deja_los_temas_sin_resumen(): void
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => \App\Models\Empresa::factory()->create()->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'Articulo 1. Texto de prueba.',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Tema Y', 'descripcion' => 'Desc.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        app(TemaClasificadorService::class)->asegurarResumenesSimples($rit);

        $this->assertNull($rit->temasNormativos()->find($tema->id)->pivot->resumen_simple);
    }
}
