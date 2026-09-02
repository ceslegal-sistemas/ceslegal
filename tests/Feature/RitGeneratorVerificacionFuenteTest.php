<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Services\RITGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario tras una prueba empírica real de construcción
 * de RIT: el Capítulo IX (Escala de Sanciones) citó "Artículo 53 del Código
 * Sustantivo del Trabajo" para el límite de suspensión (8 días/2 meses) -
 * el artículo real es el 112 CST. El "53" pertenecía a una ley DISTINTA
 * (Ley 2365 de 2024, Código General Disciplinario) presente en el mismo
 * contexto (Biblioteca Jurídica) para ese mismo capítulo - el modelo mezcló
 * la numeración de dos fuentes distintas. El prompt ahora exige verificar
 * que número y nombre de ley/código coincidan en la MISMA fuente antes de
 * citar.
 */
class RitGeneratorVerificacionFuenteTest extends TestCase
{
    use RefreshDatabase;

    private function invocarConstruirPrompt(): string
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $cap = [
            'numero' => 'IX',
            'titulo' => 'ESCALA DE SANCIONES',
            'instrucciones' => 'Instrucciones de prueba.',
        ];

        $service = new RITGeneratorService();
        $metodo = new \ReflectionMethod(RITGeneratorService::class, 'construirPrompt');
        $metodo->setAccessible(true);

        return $metodo->invoke(
            $service,
            $cap,
            'Contexto de empresa de prueba',
            'Art. 112 CST - texto de prueba',
            'Fragmento de otra ley con Artículo 53',
            1,
            $empresa,
        );
    }

    public function test_el_prompt_exige_verificar_que_numero_y_ley_coincidan_en_la_misma_fuente(): void
    {
        $prompt = $this->invocarConstruirPrompt();

        $this->assertStringContainsString('VERIFICACIÓN DE FUENTE', $prompt);
        $this->assertStringContainsString('NUNCA le pongas a contenido', $prompt);
        $this->assertStringContainsString('Artículo 112 del CST', $prompt);
    }

    /** Regresión: la regla anti-alucinación original sigue intacta. */
    public function test_sigue_prohibiendo_citar_lo_que_no_esta_en_el_contexto(): void
    {
        $prompt = $this->invocarConstruirPrompt();

        $this->assertStringContainsString('REGLA FUNDAMENTAL - ANTI-ALUCINACIÓN', $prompt);
        $this->assertStringContainsString('PROHIBICIÓN ABSOLUTA', $prompt);
    }
}
