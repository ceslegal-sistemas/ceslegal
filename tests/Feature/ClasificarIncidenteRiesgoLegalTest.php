<?php

namespace Tests\Feature;

use App\Services\IADescargoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-11): un caso real donde el
 * trabajador "tocó a una compañera sin su consentimiento" se clasificó
 * como GRAVE con certeza alta y la IA mencionó explícitamente en su
 * justificación la posibilidad de acoso/violencia sexual - pero el
 * sistema no distinguía esto de cualquier otro factor de riesgo
 * genérico, y el usuario pudo avanzar el wizard sin ninguna advertencia
 * especial. Se agrega categoria_riesgo_legal como campo estructurado.
 */
class ClasificarIncidenteRiesgoLegalTest extends TestCase
{
    use RefreshDatabase;

    private function fakearRespuestaGemini(array $json): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode($json)]]]],
                ],
            ], 200),
        ]);
    }

    public function test_el_prompt_incluye_las_instrucciones_de_categoria_riesgo_legal(): void
    {
        $this->fakearRespuestaGemini([
            'informacion_suficiente' => true,
            'gravedad_estimada' => 'GRAVE',
            'certeza' => 'alta',
            'nivel_interrogatorio_minimo' => 'INVESTIGATIVO',
            'factores_riesgo' => [],
            'categoria_riesgo_legal' => 'ninguna',
            'justificacion_riesgo_legal' => '',
            'justificacion' => 'x',
        ]);

        app(IADescargoService::class)->clasificarIncidente([
            'empresa_id' => null,
            'trabajador_id' => null,
            'cargo' => 'Analista',
            'hechos' => 'El trabajador llegó tarde sin avisar.',
            'fechas_ocurrencia' => [],
            'motivos_rit' => [],
        ]);

        Http::assertSent(function ($request) {
            $texto = $request->data()['contents'][0]['parts'][0]['text'] ?? '';
            return str_contains($texto, 'categoria_riesgo_legal')
                && str_contains($texto, 'posible_acoso_o_violencia');
        });
    }

    public function test_normaliza_a_ninguna_cuando_la_ia_devuelve_un_valor_no_permitido(): void
    {
        $this->fakearRespuestaGemini([
            'informacion_suficiente' => true,
            'gravedad_estimada' => 'GRAVE',
            'certeza' => 'alta',
            'nivel_interrogatorio_minimo' => 'INVESTIGATIVO',
            'factores_riesgo' => [],
            'categoria_riesgo_legal' => 'algo_que_la_ia_invento',
            'justificacion_riesgo_legal' => 'x',
            'justificacion' => 'x',
        ]);

        $resultado = app(IADescargoService::class)->clasificarIncidente([
            'empresa_id' => null,
            'trabajador_id' => null,
            'cargo' => 'Analista',
            'hechos' => 'El trabajador llegó tarde sin avisar.',
            'fechas_ocurrencia' => [],
            'motivos_rit' => [],
        ]);

        $this->assertSame('ninguna', $resultado['categoria_riesgo_legal']);
    }

    public function test_conserva_posible_acoso_o_violencia_cuando_la_ia_lo_marca(): void
    {
        $this->fakearRespuestaGemini([
            'informacion_suficiente' => true,
            'gravedad_estimada' => 'GRAVE',
            'certeza' => 'alta',
            'nivel_interrogatorio_minimo' => 'INVESTIGATIVO',
            'factores_riesgo' => ['Posible acoso laboral o sexual'],
            'categoria_riesgo_legal' => 'posible_acoso_o_violencia',
            'justificacion_riesgo_legal' => 'El relato describe contacto físico no consentido.',
            'justificacion' => 'x',
        ]);

        $resultado = app(IADescargoService::class)->clasificarIncidente([
            'empresa_id' => null,
            'trabajador_id' => null,
            'cargo' => 'Analista',
            'hechos' => 'El trabajador tocó a una compañera sin su consentimiento.',
            'fechas_ocurrencia' => [],
            'motivos_rit' => [],
        ]);

        $this->assertSame('posible_acoso_o_violencia', $resultado['categoria_riesgo_legal']);
        $this->assertSame('El relato describe contacto físico no consentido.', $resultado['justificacion_riesgo_legal']);
    }

    public function test_fuerza_ninguna_cuando_informacion_es_insuficiente_aunque_la_ia_diga_lo_contrario(): void
    {
        $this->fakearRespuestaGemini([
            'informacion_suficiente' => false,
            'elementos_faltantes' => ['Especifique la fecha.'],
            'categoria_riesgo_legal' => 'posible_acoso_o_violencia',
            'justificacion_riesgo_legal' => 'x',
        ]);

        $resultado = app(IADescargoService::class)->clasificarIncidente([
            'empresa_id' => null,
            'trabajador_id' => null,
            'cargo' => 'Analista',
            'hechos' => 'Algo pasó.',
            'fechas_ocurrencia' => [],
            'motivos_rit' => [],
        ]);

        $this->assertSame('ninguna', $resultado['categoria_riesgo_legal']);
    }

    public function test_hechos_vacios_devuelve_categoria_ninguna_sin_llamar_a_la_ia(): void
    {
        Http::fake();

        $resultado = app(IADescargoService::class)->clasificarIncidente([
            'empresa_id' => null,
            'trabajador_id' => null,
            'cargo' => 'Analista',
            'hechos' => '',
            'fechas_ocurrencia' => [],
            'motivos_rit' => [],
        ]);

        $this->assertSame('ninguna', $resultado['categoria_riesgo_legal']);
        Http::assertNothingSent();
    }
}
