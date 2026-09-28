<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Services\LogroSimpleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LevelUp\Experience\Models\Achievement;
use Tests\TestCase;

/**
 * Motor genérico de logros de 1 nivel o de 3 niveles (2026-09-28) - reusado
 * por "Constructor de RIT", "Primer Otrosí", "Cero Vencimientos" y "Empresa
 * Blindada" para no repetir la mecánica grant/increment/celebrar ya probada
 * en LogroDescargosServiceTest.
 */
class LogroSimpleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\LogrosSeeder::class);
    }

    private function crearEmpresa(): Empresa
    {
        return Empresa::factory()->create(['active' => true]);
    }

    public function test_otorga_un_logro_unico_solo_una_vez(): void
    {
        $empresa = $this->crearEmpresa();
        $service = app(LogroSimpleService::class);

        $service->otorgarUnicoSiNoExiste($empresa, 'Constructor de RIT');
        $empresa->unsetRelation('allAchievements');

        $logro = Achievement::where('name', 'Constructor de RIT')->first();
        $this->assertSame(100, $empresa->allAchievements()->find($logro->id)->pivot->progress);

        // Segunda llamada no debe fallar ni duplicar nada.
        $service->otorgarUnicoSiNoExiste($empresa, 'Constructor de RIT');
        $this->assertSame(1, $empresa->allAchievements()->where('achievement_id', $logro->id)->count());
    }

    public function test_incrementar_grupo_de_3_niveles_respeta_los_umbrales(): void
    {
        $empresa = $this->crearEmpresa();
        $service = app(LogroSimpleService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->incrementarGrupo($empresa, LogroSimpleService::UMBRALES_RENOVACION_A_TIEMPO);
            $empresa->unsetRelation('allAchievements');
        }

        $primero = Achievement::where('name', 'Primera Renovación a Tiempo')->first();
        $segundo = Achievement::where('name', 'Renovador Confiable')->first();
        $tercero = Achievement::where('name', 'Cero Vencimientos')->first();

        $this->assertSame(100, $empresa->allAchievements()->find($primero->id)->pivot->progress);
        $this->assertSame(100, $empresa->allAchievements()->find($segundo->id)->pivot->progress);
        $this->assertSame(50, $empresa->allAchievements()->find($tercero->id)->pivot->progress);
    }

    public function test_niveles_de_grupo_expone_el_progreso_de_cada_nivel(): void
    {
        $empresa = $this->crearEmpresa();
        $service = app(LogroSimpleService::class);

        $service->incrementarGrupo($empresa, LogroSimpleService::UMBRALES_ACTUALIZACION_RIT_APROBADA);
        $empresa->unsetRelation('allAchievements');

        $niveles = $service->nivelesDeGrupo($empresa, LogroSimpleService::UMBRALES_ACTUALIZACION_RIT_APROBADA, 'actualizaciones aprobadas');

        $this->assertCount(3, $niveles);
        $this->assertTrue($niveles[0]['completado']);
        $this->assertSame('1 de 1 actualizaciones aprobadas', $niveles[0]['progreso_texto']);
        $this->assertFalse($niveles[1]['completado']);
    }

    public function test_nivel_unico_devuelve_null_si_el_achievement_no_existe(): void
    {
        $empresa = $this->crearEmpresa();

        $nivel = app(LogroSimpleService::class)->nivelUnico($empresa, 'Logro Inexistente', 'texto');

        $this->assertNull($nivel);
    }
}
