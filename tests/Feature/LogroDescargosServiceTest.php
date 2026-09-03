<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use App\Services\LogroDescargosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LevelUp\Experience\Models\Achievement;
use Tests\TestCase;

/**
 * Logros de "Plazos de Descargos Cumplidos" - cumplimiento proactivo, no
 * volumen de sanciones (pedido explícito del usuario: premiar volumen
 * "sonaría macabro"). El logro le pertenece a la EMPRESA
 * (cjmellor/level-up configurado con Empresa como "user").
 */
class LogroDescargosServiceTest extends TestCase
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

    public function test_el_primer_plazo_cumplido_desbloquea_el_primer_logro(): void
    {
        $empresa = $this->crearEmpresa();

        app(LogroDescargosService::class)->registrarPlazoCumplido($empresa);

        $logro = Achievement::where('name', 'Primer plazo cumplido')->first();
        $progreso = $empresa->allAchievements()->find($logro->id)->pivot->progress;

        $this->assertSame(100, $progreso);
    }

    public function test_gestor_puntual_necesita_5_plazos_cumplidos(): void
    {
        $empresa = $this->crearEmpresa();
        $service = app(LogroDescargosService::class);

        for ($i = 0; $i < 4; $i++) {
            $service->registrarPlazoCumplido($empresa);
        }

        $logro = Achievement::where('name', 'Gestor puntual')->first();
        $progresoA4 = $empresa->allAchievements()->find($logro->id)->pivot->progress;
        $this->assertSame(80, $progresoA4);

        $service->registrarPlazoCumplido($empresa);

        $empresa->unsetRelation('allAchievements');
        $progresoA5 = $empresa->allAchievements()->find($logro->id)->pivot->progress;
        $this->assertSame(100, $progresoA5);
    }

    /**
     * NotificacionService::crear() usa el sistema nativo de notificaciones
     * de Filament (FilamentNotification::sendToDatabase()), que escribe en
     * la tabla estándar de Laravel `notifications` (via el trait
     * Notifiable) - no en el modelo App\Models\Notificacion (confirmado sin
     * ningún uso real en el código, tabla legacy).
     */
    public function test_notifica_y_marca_confeti_solo_al_desbloquear_por_primera_vez(): void
    {
        $empresa = $this->crearEmpresa();
        $cliente = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        app(LogroDescargosService::class)->registrarPlazoCumplido($empresa);

        $this->assertTrue(
            $cliente->notifications()->get()->contains(
                fn ($n) => ($n->data['title'] ?? null) === '¡Nuevo logro desbloqueado!'
            )
        );
        $this->assertSame('Primer plazo cumplido', session('celebrar_logro'));
    }

    public function test_no_vuelve_a_notificar_un_logro_ya_desbloqueado(): void
    {
        $empresa = $this->crearEmpresa();
        $cliente = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $service = app(LogroDescargosService::class);

        $service->registrarPlazoCumplido($empresa);
        session()->forget('celebrar_logro');

        $service->registrarPlazoCumplido($empresa);

        // "Primer plazo cumplido" (meta 1) ya estaba en 100% - no debe
        // volver a generar notificación ni bandera de confeti para ese
        // logro puntual en la segunda llamada.
        $totalDeEseLogro = $cliente->notifications()->get()->filter(
            fn ($n) => str_contains($n->data['body'] ?? '', 'Primer plazo cumplido')
        )->count();

        $this->assertSame(1, $totalDeEseLogro);
    }

    public function test_estado_dashboard_reporta_el_primer_logro_no_completado(): void
    {
        $empresa = $this->crearEmpresa();
        $service = app(LogroDescargosService::class);

        $service->registrarPlazoCumplido($empresa);
        $empresa->unsetRelation('allAchievements');

        $estado = $service->estadoDashboard($empresa);

        $this->assertSame(1, $estado['completados']);
        $this->assertSame(3, $estado['total']);
        $this->assertSame('Gestor puntual', $estado['actual']['nombre']);
        $this->assertSame(1, $estado['actual']['count']);
        $this->assertSame(5, $estado['actual']['meta']);
    }

    public function test_estado_dashboard_actual_es_null_cuando_los_3_logros_estan_completos(): void
    {
        $empresa = $this->crearEmpresa();
        $service = app(LogroDescargosService::class);

        for ($i = 0; $i < 10; $i++) {
            $service->registrarPlazoCumplido($empresa);
            $empresa->unsetRelation('allAchievements');
        }

        $estado = $service->estadoDashboard($empresa);

        $this->assertSame(3, $estado['completados']);
        $this->assertNull($estado['actual']);
    }
}
