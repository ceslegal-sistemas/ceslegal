<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-10): reorganizar por severidad en
 * vez de una lista plana de 6 filas expandibles una por una - lo que
 * necesita atención va primero y expandido, lo que está bien se colapsa.
 */
class ValidacionesV6ResumenReorganizadoTest extends TestCase
{
    use RefreshDatabase;

    private function render(array $overrides = []): string
    {
        $data = array_merge([
            'estado' => 'completado',
            'resultados' => [],
            'en' => now(),
            'puntosClave' => [],
        ], $overrides);

        return view('filament.components.validaciones-v6-resumen', $data)->render();
    }

    public function test_muestra_bloque_requiere_atencion_cuando_hay_puntos_clave(): void
    {
        $html = $this->render(['puntosClave' => ['La fecha del hecho no coincide con la citación.']]);

        $this->assertStringContainsString('Requiere su atención', $html);
        $this->assertStringContainsString('La fecha del hecho no coincide con la citación.', $html);
    }

    public function test_no_muestra_bloque_requiere_atencion_cuando_no_hay_hallazgos(): void
    {
        $html = $this->render(['puntosClave' => []]);

        $this->assertStringNotContainsString('Requiere su atención', $html);
    }

    public function test_las_filas_sin_hallazgos_quedan_en_un_details_colapsado(): void
    {
        $html = $this->render(['puntosClave' => []]);

        $this->assertStringContainsString('sin observaciones', $html);
    }

    public function test_usa_lord_icon_no_svg_plano_para_el_triangulo_de_advertencia(): void
    {
        $html = $this->render(['puntosClave' => ['Punto de prueba']]);

        $this->assertStringContainsString('lltgvngb.json', $html);
        // El path SVG viejo del triángulo de advertencia ya no debe existir.
        $this->assertStringNotContainsString('M12 9v3.75m-9.303 3.376', $html);
    }

    public function test_preserva_el_wire_ignore_self_del_motor_de_riesgo(): void
    {
        $html = $this->render([
            'resultados' => ['resistencia_judicial' => ['estado' => 'riesgo', 'hallazgos' => ['Hallazgo de riesgo real.']]],
            'onRiskOpen' => true,
        ]);

        // No se puede invocar evaluarMotoresV6() con datos reales de IA aquí sin
        // mockear el servicio completo - lo que SÍ se puede verificar sin mocks es
        // que el mecanismo (el atributo en sí) sigue en el archivo fuente.
        $fuente = file_get_contents(resource_path('views/filament/components/validaciones-v6-resumen.blade.php'));
        $this->assertStringContainsString('wire:ignore.self', $fuente);
        $this->assertStringContainsString('acknowledgeRisk', $fuente);
    }
}
