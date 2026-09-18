<?php

namespace Tests\Feature;

use App\Jobs\GenerarYEnviarSancionJob;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * GenerarYEnviarSancionJob reemplaza la ejecución síncrona de
 * DocumentGeneratorService::generarYEnviarSancion()/
 * generarYEnviarConstanciaNoSancion() dentro de los 4 puntos de
 * ProcesoDisciplinarioResource que emiten una sanción - causa raíz real de la
 * lentitud reportada en el demo del 2026-09-16 (bloqueaba un proceso PHP-FPM
 * durante todo el tiempo de Gemini + PDF + email). Ver
 * docs/superpowers/specs/2026-09-18-generar-sancion-cola-asincrona-design.md.
 */
class GenerarYEnviarSancionJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // TimelineService::registrarCreacion() (disparado por el observer al
        // crear el proceso) usa auth()->id() ?? 1 como user_id - la columna
        // es FK real, necesita un usuario con id=1 (mismo patrón ya usado en
        // LogroDescargosHookCierreProcesoTest).
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function crearProceso(): ProcesoDisciplinario
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '111',
            'genero' => 'masculino',
            'nombres' => 'Luis',
            'apellidos' => 'Torres',
            'cargo' => 'Operario',
            'email' => 'luis@test.com',
            'telefono' => '3001111111',
            'direccion' => 'Calle 5',
            'active' => true,
        ]);

        return ProcesoDisciplinario::create([
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);
    }

    public function test_tipo_sancion_null_llama_a_generar_constancia_de_no_sancion(): void
    {
        $proceso = $this->crearProceso();

        $mock = Mockery::mock(DocumentGeneratorService::class);
        $mock->shouldReceive('generarYEnviarConstanciaNoSancion')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->is($proceso)));
        $mock->shouldNotReceive('generarYEnviarSancion');

        (new GenerarYEnviarSancionJob($proceso, null))->handle($mock);

        $this->assertSame('completado', $proceso->fresh()->emision_sancion_estado);
    }

    public function test_tipo_sancion_informado_llama_a_generar_y_enviar_sancion(): void
    {
        $proceso = $this->crearProceso();

        $mock = Mockery::mock(DocumentGeneratorService::class);
        $mock->shouldReceive('generarYEnviarSancion')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->is($proceso)), 'suspension');
        $mock->shouldNotReceive('generarYEnviarConstanciaNoSancion');

        (new GenerarYEnviarSancionJob($proceso, 'suspension'))->handle($mock);

        $this->assertSame('completado', $proceso->fresh()->emision_sancion_estado);
    }

    public function test_si_el_servicio_falla_marca_estado_error_y_relanza(): void
    {
        $proceso = $this->crearProceso();

        $mock = Mockery::mock(DocumentGeneratorService::class);
        $mock->shouldReceive('generarYEnviarSancion')
            ->once()
            ->andThrow(new \Exception('Gemini no disponible'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gemini no disponible');

        try {
            (new GenerarYEnviarSancionJob($proceso, 'llamado_atencion'))->handle($mock);
        } finally {
            $proceso->refresh();
            $this->assertSame('error', $proceso->emision_sancion_estado);
            $this->assertSame('Gemini no disponible', $proceso->emision_sancion_error);
        }
    }

    public function test_failed_tambien_marca_estado_error(): void
    {
        $proceso = $this->crearProceso();

        (new GenerarYEnviarSancionJob($proceso, 'suspension'))->failed(new \Exception('Timeout del worker'));

        $proceso->refresh();
        $this->assertSame('error', $proceso->emision_sancion_estado);
        $this->assertSame('Timeout del worker', $proceso->emision_sancion_error);
    }
}
