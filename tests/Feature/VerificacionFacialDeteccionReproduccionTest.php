<?php

namespace Tests\Feature;

use App\Services\VerificacionFacialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito del usuario tras confirmar que la detección de parpadeo
 * (EAR y MediaPipe) no es confiable en su navegador: en vez de probar "vida"
 * con un micro-gesto biométrico, detectarAccesorios() ahora también rechaza
 * fotos que sean una reproducción (foto de pantalla, de otra foto, o
 * impresa) - usando la misma IA de Gemini que ya funciona de forma
 * confiable hoy para el chequeo de accesorios (gafas/tapabocas/gorra).
 */
class VerificacionFacialDeteccionReproduccionTest extends TestCase
{
    use RefreshDatabase;

    private const FOTO_BASE64 = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAA==';

    private function respuestaGemini(bool $accesorio, ?string $motivo): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode(['accesorio' => $accesorio, 'motivo' => $motivo]),
                    ]]],
                ]],
            ], 200),
        ]);
    }

    public function test_el_prompt_incluye_el_criterio_de_reproduccion_pantalla_o_foto_impresa(): void
    {
        $this->respuestaGemini(false, null);

        app(VerificacionFacialService::class)->detectarAccesorios(self::FOTO_BASE64);

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($prompt, 'REPRODUCCIÓN')
                && str_contains($prompt, 'moiré')
                && str_contains($prompt, 'pantalla');
        });
    }

    public function test_rechaza_una_foto_detectada_como_reproduccion(): void
    {
        $this->respuestaGemini(true, 'Debe tomarse la foto en vivo frente a la cámara, no a partir de otra foto, pantalla o imagen impresa.');

        $resultado = app(VerificacionFacialService::class)->detectarAccesorios(self::FOTO_BASE64);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('en vivo', $resultado['motivo']);
    }

    /**
     * Regresión: el criterio nuevo no debe romper la detección de accesorios
     * que ya funcionaba (gafas, tapabocas, gorra).
     */
    public function test_sigue_rechazando_accesorios_normales(): void
    {
        $this->respuestaGemini(true, 'Por favor retírese las gafas para verificar su identidad correctamente.');

        $resultado = app(VerificacionFacialService::class)->detectarAccesorios(self::FOTO_BASE64);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('gafas', $resultado['motivo']);
    }

    public function test_acepta_una_foto_en_vivo_sin_accesorios(): void
    {
        $this->respuestaGemini(false, null);

        $resultado = app(VerificacionFacialService::class)->detectarAccesorios(self::FOTO_BASE64);

        $this->assertTrue($resultado['ok']);
        $this->assertNull($resultado['motivo']);
    }
}
