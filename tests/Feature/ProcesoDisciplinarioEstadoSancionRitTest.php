<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ProcesoDisciplinarioResource::resolverEstadoSancionPorAceptacionRit() -
 * regla legal confirmada por William (abogado, reunión 2026-09-29, ver
 * backlog-rit-anterior-si-no-acepto-actualizacion.md). 3 escenarios:
 * empresa sin RIT, RIT existente pero trabajador nunca lo aceptó, y
 * trabajador que sí aceptó (vigente o versión anterior).
 */
class ProcesoDisciplinarioEstadoSancionRitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
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

    private function crearTrabajador(Empresa $empresa): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '123456789',
            'genero' => 'masculino', 'nombres' => 'Juan', 'apellidos' => 'Perez', 'cargo' => 'Operario', 'active' => true,
        ]);
    }

    public function test_sin_rit_en_la_empresa_solo_permite_terminacion_y_no_sancion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);
        $proceso = $this->crearProceso($empresa, $trabajador);

        $estado = ProcesoDisciplinarioResource::resolverEstadoSancionPorAceptacionRit($proceso, []);

        $this->assertTrue($estado['sinRit']);
        $this->assertFalse($estado['nuncaAceptoRit']);
        $this->assertSame(['terminacion', 'no_sancion'], array_keys($estado['opciones']));
    }

    public function test_rit_existente_pero_trabajador_nunca_lo_acepto_solo_permite_llamado_de_atencion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        $proceso = $this->crearProceso($empresa, $trabajador);

        $estado = ProcesoDisciplinarioResource::resolverEstadoSancionPorAceptacionRit($proceso, []);

        $this->assertFalse($estado['sinRit']);
        $this->assertTrue($estado['nuncaAceptoRit']);
        $this->assertSame(['llamado_atencion', 'no_sancion'], array_keys($estado['opciones']));
    }

    public function test_trabajador_que_acepto_el_rit_vigente_tiene_opciones_completas(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now(),
            'texto_rit_hash' => hash('sha256', 'v1'), 'texto_rit_snapshot' => 'v1',
        ]);
        $proceso = $this->crearProceso($empresa, $trabajador);

        $estado = ProcesoDisciplinarioResource::resolverEstadoSancionPorAceptacionRit($proceso, []);

        $this->assertFalse($estado['sinRit']);
        $this->assertFalse($estado['nuncaAceptoRit']);
        $this->assertFalse($estado['usaVersionAnteriorRit']);
        $this->assertSame(
            ['llamado_atencion', 'suspension', 'terminacion', 'no_sancion'],
            array_keys($estado['opciones'])
        );
    }

    public function test_trabajador_que_acepto_una_version_anterior_marca_usa_version_anterior_pero_opciones_completas(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v2 vigente',
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now()->subMonths(2),
            'texto_rit_hash' => hash('sha256', 'v1 anterior'), 'texto_rit_snapshot' => 'v1 anterior',
        ]);
        $proceso = $this->crearProceso($empresa, $trabajador);

        $estado = ProcesoDisciplinarioResource::resolverEstadoSancionPorAceptacionRit($proceso, []);

        $this->assertFalse($estado['sinRit']);
        $this->assertFalse($estado['nuncaAceptoRit']);
        $this->assertTrue($estado['usaVersionAnteriorRit']);
        $this->assertSame(
            ['llamado_atencion', 'suspension', 'terminacion', 'no_sancion'],
            array_keys($estado['opciones'])
        );
    }

    public function test_multa_solo_se_ofrece_cuando_el_analisis_la_indica_y_si_hay_aceptacion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now(),
            'texto_rit_hash' => hash('sha256', 'v1'), 'texto_rit_snapshot' => 'v1',
        ]);
        $proceso = $this->crearProceso($empresa, $trabajador);

        $estado = ProcesoDisciplinarioResource::resolverEstadoSancionPorAceptacionRit(
            $proceso,
            ['sanciones_disponibles' => ['multa']]
        );

        $this->assertArrayHasKey('multa', $estado['opciones']);
    }

    public function test_multa_no_se_ofrece_si_el_trabajador_nunca_acepto_aunque_el_analisis_la_indique(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        $proceso = $this->crearProceso($empresa, $trabajador);

        $estado = ProcesoDisciplinarioResource::resolverEstadoSancionPorAceptacionRit(
            $proceso,
            ['sanciones_disponibles' => ['multa']]
        );

        $this->assertArrayNotHasKey('multa', $estado['opciones']);
    }
}
