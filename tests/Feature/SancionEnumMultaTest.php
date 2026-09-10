<?php

namespace Tests\Feature;

use App\Models\AnalisisJuridico;
use App\Models\Empresa;
use App\Models\Impugnacion;
use App\Models\ProcesoDisciplinario;
use App\Models\Sancion;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real en producción (2026-09-10): "SQLSTATE[01000]: Warning: 1265
 * Data truncated for column 'tipo_sancion'" al emitir una sanción tipo
 * 'multa' - el ENUM de sanciones.tipo_sancion nunca incluyó 'multa' ni
 * 'no_sancion'. analisis_juridicos e impugnaciones tenían el mismo
 * problema a medias (les faltaba 'multa').
 */
class SancionEnumMultaTest extends TestCase
{
    use RefreshDatabase;

    private function crearProceso(): ProcesoDisciplinario
    {
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '1007568729',
            'nombres' => 'Juan Pablo', 'apellidos' => 'Rendon Beltran', 'cargo' => 'Desarrollador de Software',
            'area' => 'Tecnología', 'fecha_ingreso' => '2026-01-01', 'active' => true,
        ]);

        return ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-MULTA', 'empresa_id' => $empresa->id, 'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);
    }

    public function test_puede_crear_una_sancion_tipo_multa(): void
    {
        $proceso = $this->crearProceso();

        $sancion = Sancion::create([
            'proceso_id' => $proceso->id,
            'tipo_sancion' => 'multa',
            'motivo_sancion' => 'Motivo de prueba.',
            'fundamento_legal' => 'Artículo 112 del Código Sustantivo del Trabajo.',
        ]);

        $this->assertSame('multa', $sancion->fresh()->tipo_sancion);
    }

    public function test_puede_crear_una_sancion_tipo_no_sancion(): void
    {
        $proceso = $this->crearProceso();

        $sancion = Sancion::create([
            'proceso_id' => $proceso->id,
            'tipo_sancion' => 'no_sancion',
            'motivo_sancion' => 'Motivo de prueba.',
            'fundamento_legal' => 'N/A.',
        ]);

        $this->assertSame('no_sancion', $sancion->fresh()->tipo_sancion);
    }

    public function test_analisis_juridico_acepta_multa_recomendada(): void
    {
        $proceso = $this->crearProceso();
        $abogado = User::factory()->create(['role' => 'abogado', 'active' => true]);

        $analisis = AnalisisJuridico::create([
            'proceso_id' => $proceso->id,
            'abogado_id' => $abogado->id,
            'fecha_analisis' => now(),
            'analisis_hechos' => 'Análisis de hechos de prueba.',
            'analisis_pruebas' => 'Análisis de pruebas de prueba.',
            'analisis_normativo' => 'Análisis normativo de prueba.',
            'conclusion' => 'Conclusión de prueba.',
            'recomendacion' => 'sancion',
            'fundamento_legal' => 'Artículo 112 del Código Sustantivo del Trabajo.',
            'tipo_sancion_recomendada' => 'multa',
        ]);

        $this->assertSame('multa', $analisis->fresh()->tipo_sancion_recomendada);
    }

    public function test_impugnacion_acepta_multa_como_nueva_sancion(): void
    {
        $proceso = $this->crearProceso();
        $sancion = Sancion::create([
            'proceso_id' => $proceso->id,
            'tipo_sancion' => 'llamado_atencion',
            'motivo_sancion' => 'Motivo de prueba.',
            'fundamento_legal' => 'N/A.',
        ]);

        $impugnacion = Impugnacion::create([
            'proceso_id' => $proceso->id,
            'sancion_id' => $sancion->id,
            'fecha_impugnacion' => now(),
            'motivos_impugnacion' => 'Motivos de prueba.',
            'nueva_sancion_tipo' => 'multa',
        ]);

        $this->assertSame('multa', $impugnacion->fresh()->nueva_sancion_tipo);
    }
}
