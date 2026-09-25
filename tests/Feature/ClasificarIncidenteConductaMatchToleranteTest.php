<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Services\IADescargoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario (2026-09-25, RENBEL, proceso de acoso
 * laboral): pese a instruir a la IA "copia LITERAL, sin cambiar ni una
 * letra", la comparación de conducta_rit_aplicable contra el catálogo era
 * === (byte a byte) - una variación mínima de espacios/mayúsculas en la
 * respuesta del modelo hacía fallar la coincidencia en silencio,
 * clasificacion_incidente_ia quedaba sin conducta, y la citación caía al
 * texto genérico de respaldo aunque el catálogo SÍ tuviera la conducta
 * exacta ("El acoso laboral o sexual..."). Se compara normalizando
 * espacios/mayúsculas - sigue sin aceptar nada que no sea, en esencia, una
 * entrada real y completa del catálogo (nunca texto inventado por el
 * modelo).
 */
class ClasificarIncidenteConductaMatchToleranteTest extends TestCase
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

    public function test_acepta_la_conducta_aunque_la_ia_varie_espacios_y_mayusculas(): void
    {
        $conductaReal = 'El acoso laboral o sexual, en cualquiera de sus manifestaciones, contra cualquier persona dentro del ámbito laboral de la empresa.';

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'RIT de prueba',
            'texto_completo' => 'Texto completo de prueba.',
            'activo' => true,
        ]);
        $rit->conductas_sancionables = [
            'leve' => [],
            'grave' => [],
            'gravisima' => [
                ['conducta' => $conductaReal, 'medida' => 'Terminación del contrato con justa causa', 'tipo' => 'terminacion', 'dias_suspension' => null, 'base_legal' => 'Art. 62 CST'],
            ],
        ];
        $rit->saveQuietly();

        // La IA devuelve la conducta con doble espacio y una letra en
        // mayúscula distinta - una variación superficial, no una conducta
        // distinta ni inventada.
        $conductaConVariacion = 'el acoso laboral  o sexual, en cualquiera de sus manifestaciones, contra cualquier persona dentro del  ámbito laboral de la empresa.';

        $this->fakearRespuestaGemini([
            'informacion_suficiente' => true,
            'gravedad_estimada' => 'MUY_GRAVE',
            'certeza' => 'alta',
            'nivel_interrogatorio_minimo' => 'INVESTIGATIVO',
            'factores_riesgo' => [],
            'categoria_riesgo_legal' => 'posible_acoso_o_violencia',
            'justificacion_riesgo_legal' => 'x',
            'conducta_rit_aplicable' => $conductaConVariacion,
            'justificacion' => 'x',
        ]);

        $resultado = app(IADescargoService::class)->clasificarIncidente([
            'empresa_id' => $empresa->id,
            'trabajador_id' => null,
            'cargo' => 'Supervisor',
            'hechos' => 'Presunto acoso hacia una compañera de trabajo.',
            'fechas_ocurrencia' => [],
            'motivos_rit' => [],
        ]);

        // Debe quedar EXACTAMENTE el texto literal del catálogo, no la
        // versión con variaciones que devolvió el modelo.
        $this->assertSame($conductaReal, $resultado['conducta_rit_aplicable']);
    }

    public function test_rechaza_una_conducta_que_no_existe_en_el_catalogo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'RIT de prueba',
            'texto_completo' => 'Texto completo de prueba.',
            'activo' => true,
        ]);
        $rit->conductas_sancionables = [
            'leve' => [['conducta' => 'Llegadas tarde', 'medida' => 'Llamado de atención', 'tipo' => 'llamado_atencion', 'dias_suspension' => null]],
            'grave' => [],
            'gravisima' => [],
        ];
        $rit->saveQuietly();

        $this->fakearRespuestaGemini([
            'informacion_suficiente' => true,
            'gravedad_estimada' => 'GRAVE',
            'certeza' => 'alta',
            'nivel_interrogatorio_minimo' => 'INVESTIGATIVO',
            'factores_riesgo' => [],
            'categoria_riesgo_legal' => 'ninguna',
            'justificacion_riesgo_legal' => '',
            'conducta_rit_aplicable' => 'Una conducta completamente inventada que no está en el catálogo.',
            'justificacion' => 'x',
        ]);

        $resultado = app(IADescargoService::class)->clasificarIncidente([
            'empresa_id' => $empresa->id,
            'trabajador_id' => null,
            'cargo' => 'Analista',
            'hechos' => 'Algún hecho de prueba.',
            'fechas_ocurrencia' => [],
            'motivos_rit' => [],
        ]);

        $this->assertSame('', $resultado['conducta_rit_aplicable']);
    }
}
