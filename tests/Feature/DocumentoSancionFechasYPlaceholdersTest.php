<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario tras revisar un documento real de sanción
 * (RENBEL, proceso PD-2026-0022): la sección "5. Qué significa esto para
 * usted" quedó con "[Día] de [Mes] de 2026 hasta el [Día] de [Mes] de
 * 2026" sin resolver - el prompt le pedía a la IA la fecha efectiva de la
 * suspensión sin darle ninguna fecha real con la cual completarla.
 *
 * Decisión confirmada por el usuario: la fecha se calcula sola (día
 * siguiente a la notificación, por los días calendario acordados, ambos
 * incluidos) - nunca se le pide al cliente ni se deja que la IA la
 * invente.
 */
class DocumentoSancionFechasYPlaceholdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function crearProceso(?int $diasSuspension = 8): ProcesoDisciplinario
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '123456',
            'genero' => 'masculino',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'cargo' => 'Analista',
            'email' => 'juan@test.com',
            'telefono' => '3001234567',
            'direccion' => 'Calle 1',
            'active' => true,
        ]);

        return ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0001',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
            'dias_suspension' => $diasSuspension,
        ]);
    }

    private function invocarConstruirPrompt(ProcesoDisciplinario $proceso): string
    {
        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'construirPromptSancionLenguajeClaro');
        $metodo->setAccessible(true);

        return $metodo->invoke(
            $service,
            $proceso,
            $proceso->trabajador,
            $proceso->empresa,
            'suspension',
            'Sin descargos.',
            false,
        );
    }

    public function test_el_prompt_incluye_fecha_de_inicio_al_dia_siguiente_y_fin_calculado(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02'));

        $proceso = $this->crearProceso(diasSuspension: 8);
        $prompt = $this->invocarConstruirPrompt($proceso);

        // Día siguiente a la notificación (2026-09-02 -> 2026-09-03).
        $this->assertStringContainsString('Fecha de inicio de la suspensión: 3 de septiembre de 2026', $prompt);
        // 8 días calendario desde el 3, ambos incluidos -> termina el 10.
        $this->assertStringContainsString('Fecha de fin de la suspensión: 10 de septiembre de 2026', $prompt);
        $this->assertStringContainsString('ÚSALAS EXACTAMENTE', $prompt);

        Carbon::setTestNow();
    }

    public function test_no_calcula_fechas_si_el_tipo_no_es_suspension(): void
    {
        $proceso = $this->crearProceso(diasSuspension: null);
        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'construirPromptSancionLenguajeClaro');
        $metodo->setAccessible(true);

        $prompt = $metodo->invoke(
            $service,
            $proceso,
            $proceso->trabajador,
            $proceso->empresa,
            'llamado_atencion',
            'Sin descargos.',
            false,
        );

        $this->assertStringNotContainsString('Fecha de inicio de la suspensión', $prompt);
    }

    public function test_limpia_placeholders_entre_corchetes_sin_resolver(): void
    {
        $proceso = $this->crearProceso();
        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'limpiarPlaceholdersSinResolver');
        $metodo->setAccessible(true);

        $resultado = $metodo->invoke(
            $service,
            '<p>Suspendido desde el [Día] de [Mes] de 2026.</p>',
            $proceso,
        );

        $this->assertStringNotContainsString('[Día]', $resultado);
        $this->assertStringNotContainsString('[Mes]', $resultado);
        $this->assertStringContainsString('<p>Suspendido desde el  de  de 2026.</p>', $resultado);
    }

    public function test_no_toca_el_contenido_si_no_hay_placeholders(): void
    {
        $proceso = $this->crearProceso();
        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'limpiarPlaceholdersSinResolver');
        $metodo->setAccessible(true);

        $original = '<p>Texto normal sin corchetes.</p>';
        $resultado = $metodo->invoke($service, $original, $proceso);

        $this->assertSame($original, $resultado);
    }
}
