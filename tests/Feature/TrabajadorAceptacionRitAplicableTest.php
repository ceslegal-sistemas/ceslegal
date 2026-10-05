<?php

namespace Tests\Feature;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regla legal confirmada por William (abogado, reunión 2026-09-29, ver
 * backlog-rit-anterior-si-no-acepto-actualizacion.md): no se puede aplicar
 * el RIT activo a un trabajador que no lo ha aceptado - el proceso
 * disciplinario debe regirse por la última versión que SÍ aceptó, o por
 * ninguna si nunca aceptó nada.
 */
class TrabajadorAceptacionRitAplicableTest extends TestCase
{
    use RefreshDatabase;

    private function crearTrabajador(Empresa $empresa): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '123456789',
            'genero' => 'masculino', 'nombres' => 'Juan', 'apellidos' => 'Perez', 'cargo' => 'Operario', 'active' => true,
        ]);
    }

    public function test_devuelve_la_aceptacion_vigente_si_acepto_el_rit_activo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v2 vigente',
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        $vigente = AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now(),
            'texto_rit_hash' => hash('sha256', 'v2 vigente'), 'texto_rit_snapshot' => 'v2 vigente',
        ]);

        $resultado = $trabajador->aceptacionRitAplicable();

        $this->assertNotNull($resultado);
        $this->assertSame($vigente->id, $resultado->id);
    }

    public function test_devuelve_la_aceptacion_anterior_si_no_acepto_la_version_vigente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v2 vigente',
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        $anterior = AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now()->subMonths(2),
            'texto_rit_hash' => hash('sha256', 'v1 anterior'), 'texto_rit_snapshot' => 'v1 anterior',
        ]);

        $resultado = $trabajador->aceptacionRitAplicable();

        $this->assertNotNull($resultado);
        $this->assertSame($anterior->id, $resultado->id);
    }

    public function test_devuelve_null_si_nunca_acepto_ninguna_version(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $trabajador = $this->crearTrabajador($empresa);

        $this->assertNull($trabajador->aceptacionRitAplicable());
    }

    public function test_devuelve_la_mas_reciente_entre_varias_aceptaciones_anteriores(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v3 vigente',
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now()->subMonths(6),
            'texto_rit_hash' => hash('sha256', 'v1'), 'texto_rit_snapshot' => 'v1',
        ]);
        $masReciente = AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now()->subMonths(2),
            'texto_rit_hash' => hash('sha256', 'v2'), 'texto_rit_snapshot' => 'v2',
        ]);

        $resultado = $trabajador->aceptacionRitAplicable();

        $this->assertSame($masReciente->id, $resultado->id);
    }

    public function test_sin_rit_activo_en_la_empresa_devuelve_la_ultima_aceptacion_si_existe(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritViejo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => false, 'fuente' => 'construido_ia', 'texto_completo' => 'viejo',
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        $aceptacion = AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $ritViejo->id, 'aceptado_en' => now()->subYear(),
            'texto_rit_hash' => hash('sha256', 'viejo'), 'texto_rit_snapshot' => 'viejo',
        ]);

        $resultado = $trabajador->aceptacionRitAplicable();

        $this->assertSame($aceptacion->id, $resultado->id);
    }
}
