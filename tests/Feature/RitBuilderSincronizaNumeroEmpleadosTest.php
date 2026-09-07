<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ReglamentoInternoResource\Pages\CreateReglamentoInterno;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario (2026-09-07): el cliente ya había
 * elegido el número de trabajadores al construir el RIT con IA, pero ese
 * dato nunca aparecía en "Mi empresa" (paso 4) - se guardaba solo dentro
 * de respuestas_cuestionario del RIT, nunca de vuelta en
 * Empresa.numero_empleados. Esto también causaba el bug #3 reportado
 * ("Pendiente de determinar" en el paso 5 pese a ya tener RIT construido)
 * - ObligacionRit::avisoHtml() depende de numero_empleados.
 *
 * handleRecordCreation() es un método protegido con mucha lógica de wizard
 * que no depende de la sincronización nueva - se invoca directo por
 * reflexión con el $data mínimo, en vez de simular las 7 páginas del
 * wizard completo, siguiendo el mismo criterio pragmático que ya usa
 * ReglamentoInternoUnicoActivoTest para esta misma clase.
 */
class RitBuilderSincronizaNumeroEmpleadosTest extends TestCase
{
    use RefreshDatabase;

    private function invocarHandleRecordCreation(User $user, array $data): void
    {
        $this->actingAs($user);
        Queue::fake();

        $page = new CreateReglamentoInterno();
        $metodo = new \ReflectionMethod(CreateReglamentoInterno::class, 'handleRecordCreation');
        $metodo->setAccessible(true);
        $metodo->invoke($page, $data);
    }

    public function test_num_trabajadores_del_wizard_se_sincroniza_a_la_empresa(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        $cliente = User::factory()->create(['role' => 'cliente', 'active' => true, 'empresa_id' => $empresa->id]);

        $this->invocarHandleRecordCreation($cliente, ['num_trabajadores' => 25]);

        $this->assertSame(25, $empresa->fresh()->numero_empleados);
    }

    public function test_no_pisa_el_numero_si_el_wizard_no_trae_dato(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => 40]);
        $cliente = User::factory()->create(['role' => 'cliente', 'active' => true, 'empresa_id' => $empresa->id]);

        $this->invocarHandleRecordCreation($cliente, []);

        $this->assertSame(40, $empresa->fresh()->numero_empleados);
    }

    public function test_actualiza_el_numero_si_cambio_respecto_al_guardado(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => 10]);
        $cliente = User::factory()->create(['role' => 'cliente', 'active' => true, 'empresa_id' => $empresa->id]);

        $this->invocarHandleRecordCreation($cliente, ['num_trabajadores' => 15]);

        $this->assertSame(15, $empresa->fresh()->numero_empleados);
    }
}
