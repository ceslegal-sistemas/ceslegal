<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-10-14): el documento de sanción debe
 * tener el mismo "Legal Design" que la Resolución de Impugnación
 * (resources/views/pdfs/resoluciones/impugnacion.blade.php) - franja teal
 * con icono en cada sección, tabla de datos del caso, caja "En pocas
 * palabras" y membrete - sin tocar el contenido que redacta la IA
 * (ANTECEDENTES, HECHOS, CARGOS IMPUTADOS, etc.).
 *
 * Mismo patrón de Reflection ya usado en este archivo para probar partes
 * deterministas del documento de sanción sin mockear la llamada real a la
 * IA (ver SancionCargosImputadosYCalificacionTest, MembreteEnSancionTest).
 */
class SancionLegalDesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function crearProceso(string $tipoSancion = 'llamado_atencion'): array
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '111222333',
            'genero' => 'masculino',
            'nombres' => 'Trabajador',
            'apellidos' => 'De Prueba',
            'cargo' => 'Operario',
            'email' => 'trabajador.prueba@test.com',
            'telefono' => '3000000001',
            'direccion' => 'Calle Ficticia 2',
            'active' => true,
        ]);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0200',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        return [$proceso, $trabajador, $empresa];
    }

    private function invocarEnvolver(string $contenido, ProcesoDisciplinario $proceso, $trabajador, $empresa, string $tipoSancion): string
    {
        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'envolverEnHTMLCompleto');
        $metodo->setAccessible(true);

        return $metodo->invoke($service, $contenido, $proceso, $trabajador, $empresa, $tipoSancion);
    }

    private function invocarEncabezados(string $html): string
    {
        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'inyectarEncabezadosSeccionSancion');
        $metodo->setAccessible(true);

        return $metodo->invoke($service, $html);
    }

    public function test_el_shell_incluye_la_franja_teal_con_el_titulo_de_la_sancion(): void
    {
        [$proceso, $trabajador, $empresa] = $this->crearProceso();

        $html = $this->invocarEnvolver('<p>contenido</p>', $proceso, $trabajador, $empresa, 'llamado_atencion');

        $this->assertStringContainsString('documento-header', $html);
        $this->assertStringContainsString('Resolución de Llamado de Atención', $html);
        $this->assertStringContainsString('<p>contenido</p>', $html);
    }

    public function test_el_shell_incluye_la_tabla_de_datos_con_el_trabajador_y_la_sancion(): void
    {
        [$proceso, $trabajador, $empresa] = $this->crearProceso();

        $html = $this->invocarEnvolver('<p>contenido</p>', $proceso, $trabajador, $empresa, 'suspension');

        $this->assertStringContainsString('tabla-datos', $html);
        $this->assertStringContainsString('Trabajador De Prueba', $html);
        $this->assertStringContainsString('Operario', $html);
        $this->assertStringContainsString('Sanción impuesta', $html);
        $this->assertStringContainsString('Suspensión Laboral', $html);
    }

    public function test_el_shell_incluye_la_caja_en_pocas_palabras(): void
    {
        [$proceso, $trabajador, $empresa] = $this->crearProceso();

        $html = $this->invocarEnvolver('<p>contenido</p>', $proceso, $trabajador, $empresa, 'terminacion');

        $this->assertStringContainsString('caja--simple', $html);
        $this->assertStringContainsString('En pocas palabras', $html);
        $this->assertStringContainsString('Terminación de Contrato', $html);
    }

    public function test_sin_sancion_muestra_un_resumen_y_una_fila_distintos(): void
    {
        [$proceso, $trabajador, $empresa] = $this->crearProceso();

        $html = $this->invocarEnvolver('<p>contenido</p>', $proceso, $trabajador, $empresa, 'no_sancion');

        $this->assertStringContainsString('decidió no imponer ninguna sanción', $html);
        $this->assertStringContainsString('Sin sanción - proceso cerrado', $html);
        $this->assertStringNotContainsString('Sanción impuesta', $html);
    }

    public function test_los_h3_planos_se_convierten_en_franjas_de_seccion_numeradas(): void
    {
        $html = '<h3>ANTECEDENTES</h3><p>a</p><h3>HECHOS</h3><p>b</p>';

        $resultado = $this->invocarEncabezados($html);

        $this->assertStringContainsString('seccion-header', $resultado);
        $this->assertStringContainsString('parte-01-white.svg', $this->leerIconoUsado($resultado, 1));
        $this->assertStringContainsString('<span class="titulo">ANTECEDENTES</span>', $resultado);
        $this->assertStringContainsString('<span class="titulo">HECHOS</span>', $resultado);
        $this->assertStringNotContainsString('<h3>', $resultado);
    }

    /**
     * Los iconos se inyectan como data URI base64, no como ruta de archivo -
     * se decodifica el primer <img src="..."> para confirmar qué SVG se usó.
     */
    private function leerIconoUsado(string $html, int $ordinal): string
    {
        preg_match_all('/<img src="data:image\/svg\+xml;base64,([^"]+)"/', $html, $m);
        $svg = base64_decode($m[1][$ordinal - 1] ?? '');

        // El propio archivo no lleva su nombre adentro; en su lugar se compara
        // contra el archivo real esperado en disco para ese ordinal.
        $esperado = file_get_contents(public_path('images/contrato-legal-design/parte-0' . $ordinal . '-white.svg'));

        return $svg === $esperado ? 'parte-0' . $ordinal . '-white.svg' : 'otro-icono';
    }
}
