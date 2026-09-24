<?php

namespace Tests\Feature;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Services\LogroSocializacionRitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LevelUp\Experience\Models\Achievement;
use Tests\TestCase;

/**
 * Logro "100% aceptó el RIT" (mejora "excéntrica" pedida por el usuario,
 * 2026-09-23). El denominador usa el MAYOR entre numero_empleados
 * declarado y los trabajadores activos registrados - evita un falso 100%
 * cuando hay muchos mas empleados reales que trabajadores cargados en el
 * sistema (decision explicita del usuario tras su propia pregunta durante
 * el brainstorming). El logro, una vez otorgado, queda para siempre.
 */
class LogroSocializacionRitServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\LogrosSeeder::class);
    }

    private function crearRitActivo(Empresa $empresa): ReglamentoInterno
    {
        return ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
    }

    private function crearTrabajadorQueAcepto(Empresa $empresa, ReglamentoInterno $rit): Trabajador
    {
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => (string) random_int(100000000, 999999999),
            'genero' => 'masculino', 'nombres' => 'T', 'apellidos' => 'P', 'cargo' => 'Op', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now(),
        ]);

        return $trabajador;
    }

    public function test_no_otorga_el_logro_si_numero_empleados_es_mayor_que_los_aceptados(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => 5]);
        $rit = $this->crearRitActivo($empresa);
        $this->crearTrabajadorQueAcepto($empresa, $rit);

        app(LogroSocializacionRitService::class)->revisarYOtorgar($empresa);

        $logro = Achievement::where('name', 'Reglamento 100% aceptado')->first();
        $this->assertNull($empresa->allAchievements()->find($logro->id));
    }

    public function test_otorga_el_logro_cuando_todos_los_trabajadores_activos_aceptaron_y_no_hay_numero_empleados_declarado(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        $rit = $this->crearRitActivo($empresa);
        $this->crearTrabajadorQueAcepto($empresa, $rit);
        $this->crearTrabajadorQueAcepto($empresa, $rit);

        app(LogroSocializacionRitService::class)->revisarYOtorgar($empresa);

        $logro = Achievement::where('name', 'Reglamento 100% aceptado')->first();
        $this->assertNotNull($empresa->allAchievements()->find($logro->id));
    }

    public function test_otorga_el_logro_usando_numero_empleados_como_denominador_si_coincide(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => 2]);
        $rit = $this->crearRitActivo($empresa);
        $this->crearTrabajadorQueAcepto($empresa, $rit);
        $this->crearTrabajadorQueAcepto($empresa, $rit);

        app(LogroSocializacionRitService::class)->revisarYOtorgar($empresa);

        $logro = Achievement::where('name', 'Reglamento 100% aceptado')->first();
        $this->assertNotNull($empresa->allAchievements()->find($logro->id));
    }

    public function test_no_otorga_el_logro_sin_ningun_trabajador_registrado(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        $this->crearRitActivo($empresa);

        app(LogroSocializacionRitService::class)->revisarYOtorgar($empresa);

        $logro = Achievement::where('name', 'Reglamento 100% aceptado')->first();
        $this->assertNull($empresa->allAchievements()->find($logro->id));
    }

    public function test_marca_la_celebracion_pendiente_al_otorgar_el_logro(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        $rit = $this->crearRitActivo($empresa);
        $this->crearTrabajadorQueAcepto($empresa, $rit);

        app(LogroSocializacionRitService::class)->revisarYOtorgar($empresa);

        $this->assertNotNull($empresa->fresh()->logro_socializacion_rit_pendiente_celebrar);
    }

    public function test_el_logro_no_se_revoca_si_luego_baja_del_100_por_ciento(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        $rit = $this->crearRitActivo($empresa);
        $this->crearTrabajadorQueAcepto($empresa, $rit);

        app(LogroSocializacionRitService::class)->revisarYOtorgar($empresa);

        // Nuevo trabajador que todavia no acepta - baja del 100%.
        Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '999999999',
            'genero' => 'masculino', 'nombres' => 'Nuevo', 'apellidos' => 'Sin Aceptar', 'cargo' => 'Op', 'active' => true,
        ]);

        app(LogroSocializacionRitService::class)->revisarYOtorgar($empresa);

        $logro = Achievement::where('name', 'Reglamento 100% aceptado')->first();
        $this->assertNotNull($empresa->allAchievements()->find($logro->id));
    }
}
