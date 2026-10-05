<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages\CreateProcesoDisciplinario;
use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CreateProcesoDisciplinario::validarFechaPosteriorAAceptacion() - regla
 * legal confirmada por William (abogado, reunión 2026-09-29, ver
 * backlog-rit-anterior-si-no-acepto-actualizacion.md): "Si fue el 10 [que
 * aceptó], le puedo citarlo desde el 11... ni siquiera desde el día 10" -
 * la fecha del hecho debe ser ESTRICTAMENTE posterior a la aceptación.
 *
 * Probado directo sobre el método estático (mismo criterio ya usado en
 * CreateProcesoDisciplinarioRiesgoLegalTest: la navegación fillForm()/
 * nextStep() del Wizard es "sabidamente frágil" en este proyecto).
 */
class CreateProcesoDisciplinarioFechaPosteriorAceptacionTest extends TestCase
{
    use RefreshDatabase;

    private function crearTrabajadorConAceptacion(string $fechaAceptacion): Trabajador
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '123456789',
            'genero' => 'masculino', 'nombres' => 'Juan', 'apellidos' => 'Perez', 'cargo' => 'Operario', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => $fechaAceptacion,
            'texto_rit_hash' => hash('sha256', 'v1'), 'texto_rit_snapshot' => 'v1',
        ]);

        return $trabajador;
    }

    public function test_rechaza_la_misma_fecha_de_la_aceptacion(): void
    {
        $trabajador = $this->crearTrabajadorConAceptacion('2026-01-10 10:00:00');
        $fallo = null;

        CreateProcesoDisciplinario::validarFechaPosteriorAAceptacion(
            $trabajador->id,
            '2026-01-10',
            function (string $mensaje) use (&$fallo) { $fallo = $mensaje; }
        );

        $this->assertNotNull($fallo);
        $this->assertStringContainsString('10/01/2026', $fallo);
    }

    public function test_rechaza_una_fecha_anterior_a_la_aceptacion(): void
    {
        $trabajador = $this->crearTrabajadorConAceptacion('2026-01-10 10:00:00');
        $fallo = null;

        CreateProcesoDisciplinario::validarFechaPosteriorAAceptacion(
            $trabajador->id,
            '2026-01-05',
            function (string $mensaje) use (&$fallo) { $fallo = $mensaje; }
        );

        $this->assertNotNull($fallo);
    }

    public function test_acepta_el_dia_siguiente_a_la_aceptacion(): void
    {
        $trabajador = $this->crearTrabajadorConAceptacion('2026-01-10 10:00:00');
        $fallo = null;

        CreateProcesoDisciplinario::validarFechaPosteriorAAceptacion(
            $trabajador->id,
            '2026-01-11',
            function (string $mensaje) use (&$fallo) { $fallo = $mensaje; }
        );

        $this->assertNull($fallo);
    }

    public function test_sin_trabajador_seleccionado_no_falla(): void
    {
        $fallo = null;

        CreateProcesoDisciplinario::validarFechaPosteriorAAceptacion(
            null,
            '2020-01-01',
            function (string $mensaje) use (&$fallo) { $fallo = $mensaje; }
        );

        $this->assertNull($fallo);
    }

    public function test_trabajador_que_nunca_acepto_nada_no_tiene_piso_de_fecha(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '987654321',
            'genero' => 'masculino', 'nombres' => 'Sin', 'apellidos' => 'Aceptar', 'cargo' => 'Operario', 'active' => true,
        ]);
        $fallo = null;

        CreateProcesoDisciplinario::validarFechaPosteriorAAceptacion(
            $trabajador->id,
            '2020-01-01',
            function (string $mensaje) use (&$fallo) { $fallo = $mensaje; }
        );

        $this->assertNull($fallo);
    }
}
