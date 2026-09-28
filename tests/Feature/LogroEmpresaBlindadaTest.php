<?php

namespace Tests\Feature;

use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use App\Models\User;
use App\Services\RitActualizacionAutomaticaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LevelUp\Experience\Models\Achievement;
use Tests\TestCase;

/**
 * Logro "Empresa Blindada" (2026-09-28) - cada sugerencia de actualización
 * del RIT aprobada cuenta para el grupo de 3 niveles (1/5/10), sin importar
 * el tipo de cambio.
 */
class LogroEmpresaBlindadaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\LogrosSeeder::class);
    }

    private function crearSugerenciaPendiente(Empresa $empresa, ReglamentoInterno $rit): SugerenciaActualizacionRit
    {
        $documento = DocumentoLegal::create(['titulo' => 'Ley de prueba', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => true]);

        return SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 0,
            'tipo_cambio' => 'modificar',
            'texto_anterior' => 'Artículo único.',
            'texto_propuesto' => 'Artículo único modificado.',
            'justificacion_ia' => 'Prueba.',
            'estado' => 'pendiente',
        ]);
    }

    public function test_aprobar_una_sugerencia_otorga_el_primer_nivel(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'subido', 'texto_completo' => 'Artículo único.',
        ]);
        $sugerencia = $this->crearSugerenciaPendiente($empresa, $rit);
        $resolutor = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        app(RitActualizacionAutomaticaService::class)->aplicarSugerencia($sugerencia, $resolutor);

        $logro = Achievement::where('name', 'Primera Actualización Aprobada')->first();
        $empresa->unsetRelation('allAchievements');

        $this->assertNotNull($empresa->allAchievements()->find($logro->id));
    }
}
