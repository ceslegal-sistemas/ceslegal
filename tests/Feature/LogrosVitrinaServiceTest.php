<?php

namespace Tests\Feature;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Services\LogroDescargosService;
use App\Services\LogroSocializacionRitService;
use App\Services\LogrosVitrinaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vitrina "Mis Logros" (2026-09-25): combina los 3 niveles de
 * LogroDescargosService y el logro de LogroSocializacionRitService en una
 * sola lista normalizada, sin duplicar la lógica de progreso de cada uno.
 */
class LogrosVitrinaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\LogrosSeeder::class);
    }

    private function crearEmpresa(): Empresa
    {
        return Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
    }

    public function test_incluye_todas_las_familias_de_logros(): void
    {
        $empresa = $this->crearEmpresa();

        $logros = app(LogrosVitrinaService::class)->paraEmpresa($empresa);

        $this->assertSame(
            [
                'Primer plazo cumplido', 'Gestor puntual', 'Constancia total',
                'Reglamento 100% aceptado',
                'Constructor de RIT', 'Primer Otrosí',
                'Primera Renovación a Tiempo', 'Renovador Confiable', 'Cero Vencimientos',
                'Primera Actualización Aprobada', 'Reglamento Actualizado', 'Empresa Blindada',
            ],
            array_column($logros, 'nombre')
        );

        foreach ($logros as $logro) {
            $this->assertFalse($logro['completado']);
            $this->assertSame(0, $logro['progreso_porcentaje']);
            $this->assertNotEmpty($logro['imagen']);
        }
    }

    public function test_marca_completado_y_fecha_el_logro_de_socializacion_rit_al_100_por_ciento(): void
    {
        $empresa = $this->crearEmpresa();
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '123456789',
            'genero' => 'masculino', 'nombres' => 'T', 'apellidos' => 'P', 'cargo' => 'Op', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now(),
        ]);
        app(LogroSocializacionRitService::class)->revisarYOtorgar($empresa);
        $empresa->unsetRelation('allAchievements');

        $logros = app(LogrosVitrinaService::class)->paraEmpresa($empresa);
        $logroRit = collect($logros)->firstWhere('nombre', 'Reglamento 100% aceptado');

        $this->assertTrue($logroRit['completado']);
        $this->assertSame(100, $logroRit['progreso_porcentaje']);
        $this->assertSame('1 de 1 trabajadores aceptaron el Reglamento Interno', $logroRit['progreso_texto']);
        $this->assertNotNull($logroRit['fecha_obtenido']);
    }

    public function test_marca_completado_el_nivel_de_descargos_ya_alcanzado(): void
    {
        $empresa = $this->crearEmpresa();
        app(LogroDescargosService::class)->registrarPlazoCumplido($empresa);
        $empresa->unsetRelation('allAchievements');

        $logros = app(LogrosVitrinaService::class)->paraEmpresa($empresa);
        $primerNivel = collect($logros)->firstWhere('nombre', 'Primer plazo cumplido');

        $this->assertTrue($primerNivel['completado']);
        $this->assertNotNull($primerNivel['fecha_obtenido']);
    }
}
