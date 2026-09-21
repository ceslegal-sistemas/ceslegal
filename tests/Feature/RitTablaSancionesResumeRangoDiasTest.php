<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Services\ReglamentoInternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario con un PDF de citación descargado
 * (proceso PD-2026-0126, 2026-09-18): la columna "Sanción aplicable" para la
 * gravedad GRAVE mostraba "Suspensión hasta 2 día(s) / Suspensión hasta 3
 * día(s) / Suspensión hasta 5 día(s) / Suspensión hasta 4 día(s)" - una por
 * cada conducta "grave" configurada en el wizard del RIT con su propia
 * cantidad de días, concatenadas con "/" en vez de resumidas.
 * ReglamentoInternoService::filasTablaSanciones() ahora agrupa por TIPO de
 * sanción (no por conducta) y muestra un rango de días cuando varias
 * conductas de la misma gravedad comparten el mismo tipo de sanción.
 */
class RitTablaSancionesResumeRangoDiasTest extends TestCase
{
    use RefreshDatabase;

    private function crearRitConSancionesConfiguradas(array $sancionesConfiguradas): ReglamentoInterno
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        return ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'fuente' => 'construido_ia',
            'activo' => true,
            'texto_completo' => 'Texto de prueba.',
            'respuestas_cuestionario' => [
                'sanciones_configuradas' => $sancionesConfiguradas,
            ],
        ]);
    }

    public function test_varias_conductas_graves_con_distintos_dias_muestran_un_rango(): void
    {
        $rit = $this->crearRitConSancionesConfiguradas([
            ['tipo_falta' => 'grave', 'nombre' => 'Conducta A', 'tipo_sancion' => 'suspension', 'dias_suspension' => 2],
            ['tipo_falta' => 'grave', 'nombre' => 'Conducta B', 'tipo_sancion' => 'suspension', 'dias_suspension' => 3],
            ['tipo_falta' => 'grave', 'nombre' => 'Conducta C', 'tipo_sancion' => 'suspension', 'dias_suspension' => 5],
            ['tipo_falta' => 'grave', 'nombre' => 'Conducta D', 'tipo_sancion' => 'suspension', 'dias_suspension' => 4],
        ]);

        $filas = app(ReglamentoInternoService::class)->filasTablaSanciones($rit);
        $filaGrave = collect($filas)->firstWhere('gravedad', 'Grave');

        $this->assertSame('Suspensión de 2 a 5 día(s)', $filaGrave['sancion']);
        $this->assertCount(4, $filaGrave['conductas']);
    }

    public function test_una_sola_conducta_grave_sigue_mostrando_el_dia_exacto(): void
    {
        $rit = $this->crearRitConSancionesConfiguradas([
            ['tipo_falta' => 'grave', 'nombre' => 'Conducta única', 'tipo_sancion' => 'suspension', 'dias_suspension' => 3],
        ]);

        $filas = app(ReglamentoInternoService::class)->filasTablaSanciones($rit);
        $filaGrave = collect($filas)->firstWhere('gravedad', 'Grave');

        $this->assertSame('Suspensión hasta 3 día(s)', $filaGrave['sancion']);
    }

    public function test_tipos_de_sancion_distintos_en_la_misma_gravedad_si_se_separan_con_barra(): void
    {
        $rit = $this->crearRitConSancionesConfiguradas([
            ['tipo_falta' => 'grave', 'nombre' => 'Conducta suspensión', 'tipo_sancion' => 'suspension', 'dias_suspension' => 3],
            ['tipo_falta' => 'grave', 'nombre' => 'Conducta terminación', 'tipo_sancion' => 'terminacion'],
        ]);

        $filas = app(ReglamentoInternoService::class)->filasTablaSanciones($rit);
        $filaGrave = collect($filas)->firstWhere('gravedad', 'Grave');

        $this->assertSame('Suspensión hasta 3 día(s) / Terminación del contrato con justa causa', $filaGrave['sancion']);
    }

    public function test_leve_con_una_sola_conducta_no_se_afecta(): void
    {
        $rit = $this->crearRitConSancionesConfiguradas([
            ['tipo_falta' => 'leve', 'nombre' => 'Llegar tarde', 'tipo_sancion' => 'llamado_atencion'],
        ]);

        $filas = app(ReglamentoInternoService::class)->filasTablaSanciones($rit);
        $filaLeve = collect($filas)->firstWhere('gravedad', 'Leve');

        $this->assertSame('Llamado de atención', $filaLeve['sancion']);
    }
}
