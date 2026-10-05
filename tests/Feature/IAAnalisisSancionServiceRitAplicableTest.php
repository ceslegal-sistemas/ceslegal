<?php

namespace Tests\Feature;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\IAAnalisisSancionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IAAnalisisSancionService::obtenerContextoRIT() (privado, probado por
 * reflexión - mismo patrón ya usado en TablaSancionesDocumentoSinRitTest) -
 * regla legal confirmada por William (abogado, reunión 2026-09-29, ver
 * backlog-rit-anterior-si-no-acepto-actualizacion.md): si el trabajador no
 * aceptó el RIT activo pero sí una versión anterior, el análisis debe
 * regirse por el texto de ESA versión, no por el RIT activo.
 */
class IAAnalisisSancionServiceRitAplicableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function crearTrabajador(Empresa $empresa): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '123456789',
            'genero' => 'masculino', 'nombres' => 'Juan', 'apellidos' => 'Perez', 'cargo' => 'Operario', 'active' => true,
        ]);
    }

    private function crearProceso(Empresa $empresa, Trabajador $trabajador): ProcesoDisciplinario
    {
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($user);

        return ProcesoDisciplinario::create([
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);
    }

    private function invocarObtenerContextoRIT(Empresa $empresa, ProcesoDisciplinario $proceso): array
    {
        $metodo = new \ReflectionMethod(IAAnalisisSancionService::class, 'obtenerContextoRIT');
        $metodo->setAccessible(true);

        return $metodo->invoke(new IAAnalisisSancionService(), $empresa, $proceso);
    }

    public function test_usa_el_snapshot_de_la_version_anterior_si_no_acepto_la_vigente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia',
            'texto_completo' => "Articulo 1. Jornada.\nARTICULO 44. REGIMEN DISCIPLINARIO.\nFalta leve vigente: llegar tarde.",
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'aceptado_en' => now()->subMonths(3),
            'texto_rit_hash' => hash('sha256', 'version vieja distinta'),
            'texto_rit_snapshot' => "Articulo 1. Jornada.\nARTICULO 44. REGIMEN DISCIPLINARIO.\nFalta leve ANTERIOR: no usar uniforme.",
        ]);
        $proceso = $this->crearProceso($empresa, $trabajador);

        [$sanciones, $contextoRag] = $this->invocarObtenerContextoRIT($empresa->fresh(), $proceso);

        $this->assertSame([], $sanciones);
        $this->assertStringContainsString('no usar uniforme', $contextoRag);
        $this->assertStringNotContainsString('llegar tarde', $contextoRag);
    }

    public function test_usa_el_rit_activo_si_acepto_la_version_vigente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1 vigente',
            // faltas_leves no vacío: evita que extraerSancionesParaEmail() caiga a
            // extracción con IA (Gemini) - no es el foco de este test.
            'respuestas_cuestionario' => ['faltas_leves' => ['x'], 'faltas_graves' => [], 'sanciones' => []],
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now(),
            'texto_rit_hash' => hash('sha256', 'v1 vigente'), 'texto_rit_snapshot' => 'v1 vigente',
        ]);
        $proceso = $this->crearProceso($empresa, $trabajador);

        [$sanciones, $contextoRag] = $this->invocarObtenerContextoRIT($empresa->fresh(), $proceso);

        $this->assertSame('', $contextoRag);
        $this->assertArrayHasKey('faltas_leves', $sanciones);
    }

    public function test_usa_el_rit_activo_como_informativo_si_nunca_acepto_ninguna_version(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'respuestas_cuestionario' => ['faltas_leves' => ['x'], 'faltas_graves' => [], 'sanciones' => []],
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        $proceso = $this->crearProceso($empresa, $trabajador);

        [$sanciones, $contextoRag] = $this->invocarObtenerContextoRIT($empresa->fresh(), $proceso);

        $this->assertSame('', $contextoRag);
        $this->assertArrayHasKey('faltas_leves', $sanciones);
    }

    /**
     * Fail-open: una fila vieja de AceptacionReglamentoInterno sin
     * texto_rit_snapshot (anterior a la migración 2026-09-28) no debe
     * romper el análisis - se usa el RIT activo normalmente.
     */
    public function test_sin_snapshot_guardado_cae_al_rit_activo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'respuestas_cuestionario' => ['faltas_leves' => ['x'], 'faltas_graves' => [], 'sanciones' => []],
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now()->subMonths(4),
            'texto_rit_hash' => hash('sha256', 'version vieja sin snapshot'), 'texto_rit_snapshot' => null,
        ]);
        $proceso = $this->crearProceso($empresa, $trabajador);

        [$sanciones, $contextoRag] = $this->invocarObtenerContextoRIT($empresa->fresh(), $proceso);

        $this->assertSame('', $contextoRag);
        $this->assertArrayHasKey('faltas_leves', $sanciones);
    }
}
