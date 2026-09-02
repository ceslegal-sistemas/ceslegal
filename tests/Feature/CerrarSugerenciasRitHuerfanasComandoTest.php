<?php

namespace Tests\Feature;

use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comando de limpieza de una sola vez, para correr en producción tras el
 * fix de ReglamentoInterno::desactivarActivosDe() - cierra las
 * sugerencias que ya quedaron huérfanas de antes del fix (ej. empresa
 * RENBEL).
 */
class CerrarSugerenciasRitHuerfanasComandoTest extends TestCase
{
    use RefreshDatabase;

    private function crearSugerencia(Empresa $empresa, ReglamentoInterno $rit): SugerenciaActualizacionRit
    {
        $documento = DocumentoLegal::create([
            'titulo' => 'Ley de prueba', 'tipo' => 'ley',
            'estado' => 'procesado', 'activo' => true,
        ]);

        return SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 0,
            'tipo_cambio' => 'modificar',
            'texto_anterior' => 'x',
            'texto_propuesto' => 'y',
            'justificacion_ia' => 'z',
            'estado' => 'pendiente',
        ]);
    }

    public function test_cierra_solo_las_sugerencias_de_un_rit_inactivo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritInactivo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => false,
            'fuente' => 'subido', 'texto_completo' => 'x',
        ]);
        $ritActivo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true,
            'fuente' => 'mejora_ia', 'texto_completo' => 'y',
        ]);
        $huerfana = $this->crearSugerencia($empresa, $ritInactivo);
        $vigente = $this->crearSugerencia($empresa, $ritActivo);

        $this->artisan('rit:cerrar-sugerencias-huerfanas')->assertExitCode(0);

        $this->assertSame('rechazada', $huerfana->fresh()->estado);
        $this->assertNull($huerfana->fresh()->resuelto_por);
        $this->assertSame('pendiente', $vigente->fresh()->estado);
    }

    public function test_dry_run_no_modifica_nada(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritInactivo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => false,
            'fuente' => 'subido', 'texto_completo' => 'x',
        ]);
        $huerfana = $this->crearSugerencia($empresa, $ritInactivo);

        $this->artisan('rit:cerrar-sugerencias-huerfanas --dry-run')->assertExitCode(0);

        $this->assertSame('pendiente', $huerfana->fresh()->estado);
    }
}
