<?php

namespace Tests\Feature;

use App\Services\ReglamentoInternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerarConductasWizardTest extends TestCase
{
    use RefreshDatabase;

    private function respuestaGemini(array $conductas)
    {
        return Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode($conductas)]]],
            ]],
        ], 200);
    }

    /**
     * Pedido explícito del usuario: si ya se generaron conductas y le dan de
     * nuevo al botón para conseguir MÁS, la IA no debe repetir/parafrasear
     * las que ya están - antes ni siquiera se le informaba a la IA que ya
     * existían (el botón reemplazaba todo el listado, así que esto nunca
     * importaba).
     */
    public function test_pide_conductas_nuevas_y_distintas_cuando_ya_hay_existentes(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => $this->respuestaGemini([
                ['nombre' => 'Conducta nueva', 'tipo_falta' => 'leve', 'tipo_sancion' => 'llamado_atencion', 'dias_suspension' => null],
            ]),
        ]);

        app(ReglamentoInternoService::class)->generarConductasParaWizard([
            'actividad'  => 'Comercio al por menor',
            'cargos'     => 'Vendedor, Cajero',
            'existentes' => ['Llegar tarde reiteradamente', 'Ausentarse sin permiso'],
        ]);

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($prompt, 'PROHIBIDO repetir o parafrasear')
                && str_contains($prompt, 'Llegar tarde reiteradamente')
                && str_contains($prompt, 'Ausentarse sin permiso')
                && str_contains($prompt, 'conductas NUEVAS adicionales');
        });
    }

    public function test_no_incluye_instruccion_de_no_repetir_cuando_es_la_primera_generacion(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => $this->respuestaGemini([
                ['nombre' => 'Conducta base', 'tipo_falta' => 'leve', 'tipo_sancion' => 'llamado_atencion', 'dias_suspension' => null],
            ]),
        ]);

        app(ReglamentoInternoService::class)->generarConductasParaWizard([
            'actividad' => 'Comercio al por menor',
            'cargos'    => 'Vendedor',
        ]);

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

            return !str_contains($prompt, 'PROHIBIDO repetir o parafrasear')
                && str_contains($prompt, 'Entre 15 y 30 conductas en total');
        });
    }

    /**
     * Pedido explícito del usuario: si el paso "SST y Conducta" ya tiene
     * riesgos/situaciones a prevenir seleccionados, la IA debe usarlos como
     * contexto real (por eso ese paso ahora va ANTES de "Disciplina" en el
     * wizard).
     */
    public function test_incluye_riesgos_y_situaciones_a_prevenir_en_el_prompt(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => $this->respuestaGemini([
                ['nombre' => 'Conducta de seguridad', 'tipo_falta' => 'grave', 'tipo_sancion' => 'suspension', 'dias_suspension' => 3],
            ]),
        ]);

        app(ReglamentoInternoService::class)->generarConductasParaWizard([
            'actividad' => 'Construcción',
            'cargos'    => 'Operario',
            'riesgos'   => 'mecanico, alturas',
            'prevenir'  => 'Uso indebido de herramientas eléctricas',
        ]);

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($prompt, 'Principales riesgos identificados en la empresa: mecanico, alturas')
                && str_contains($prompt, 'Uso indebido de herramientas eléctricas');
        });
    }
}
