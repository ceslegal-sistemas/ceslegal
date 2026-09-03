<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\TerminoLegal;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LevelUp\Experience\Models\Achievement;
use Tests\TestCase;

/**
 * Verifica que cerrar un proceso disciplinario DENTRO del plazo (nunca si
 * ya estaba 'vencido') dispara LogroDescargosService::registrarPlazoCumplido()
 * vía ProcesoDisciplinarioObserver::cerrarTerminosLegalesActivos() - el
 * punto de enganche real en producción, no solo la lógica del servicio en
 * aislamiento (ya cubierta en LogroDescargosServiceTest).
 */
class LogroDescargosHookCierreProcesoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
        $this->seed(\Database\Seeders\LogrosSeeder::class);
    }

    private function crearTrabajador(Empresa $empresa): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '123456',
            'genero' => 'masculino',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'cargo' => 'Analista',
            'email' => 'juan@test.com',
            'telefono' => '3001234567',
            'direccion' => 'Calle 1',
            'active' => true,
        ]);
    }

    public function test_cerrar_un_proceso_a_tiempo_incrementa_el_logro(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($user);

        $proceso = ProcesoDisciplinario::create([
            'empresa_id' => $empresa->id,
            'trabajador_id' => $this->crearTrabajador($empresa)->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        TerminoLegal::create([
            'proceso_tipo' => 'proceso_disciplinario',
            'proceso_id' => $proceso->id,
            'termino_tipo' => 'traslado_descargos',
            'fecha_inicio' => now(),
            'dias_habiles' => 5,
            'fecha_vencimiento' => now()->addDays(10),
            'dias_transcurridos' => 0,
            'estado' => 'activo',
        ]);

        $proceso->estado = 'cerrado';
        $proceso->save();

        $logro = Achievement::where('name', 'Primer plazo cumplido')->first();
        $progreso = $empresa->allAchievements()->find($logro->id)?->pivot->progress;

        $this->assertSame(100, $progreso);
    }
}
