<?php

namespace Tests\Feature;

use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Services\RitActualizacionAutomaticaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito del usuario tras una verificación empírica real: el motor
 * (RitActualizacionAutomaticaService) reescribió un bloque-resumen del RIT
 * con el texto completo de una ley, sin notar que la mayoría de ese
 * contenido ya existía en detalle en artículos separados cercanos (mismo
 * RIT) - el resultado era legalmente correcto pero redundante. El prompt
 * ahora le pide explícitamente a la IA verificar redundancia antes de
 * elegir el bloque, manteniendo la regla de "un solo bloque a la vez"
 * (decisión deliberada: mejorar el prompt es de bajo riesgo, permitir
 * tocar varios bloques a la vez habría requerido su propio diseño de
 * seguridad, descartado por el usuario por ahora).
 */
class RitActualizacionEvitaRedundanciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_prompt_pide_verificar_redundancia_antes_de_elegir_el_bloque(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(['cambio_necesario' => false])]]],
                ]],
            ], 200),
        ]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => "Artículo 32. Resumen de permisos.\nArtículo 38. Detalle de citas médicas.",
        ]);
        $documento = DocumentoLegal::create([
            'titulo' => 'Ley de prueba',
            'tipo' => 'ley',
            'estado' => 'procesado',
            'activo' => true,
        ]);

        app(RitActualizacionAutomaticaService::class)->evaluarCambio($rit, $documento);

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($prompt, 'VERIFICACIÓN DE REDUNDANCIA')
                && str_contains($prompt, 'NO lo dupliques')
                && str_contains($prompt, 'bloque MÁS ESPECÍFICO');
        });
    }

    /** Regresión: la regla de un solo bloque a la vez sigue intacta. */
    public function test_sigue_exigiendo_un_solo_bloque_a_la_vez(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(['cambio_necesario' => false])]]],
                ]],
            ], 200),
        ]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Artículo único.',
        ]);
        $documento = DocumentoLegal::create([
            'titulo' => 'Ley de prueba',
            'tipo' => 'ley',
            'estado' => 'procesado',
            'activo' => true,
        ]);

        app(RitActualizacionAutomaticaService::class)->evaluarCambio($rit, $documento);

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($prompt, 'REGLA CENTRAL: solo puedes señalar UN bloque');
        });
    }
}
