<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Bug real en producción (2026-09-18, error 500 reportado por el usuario en
 * GET /admin/proceso-disciplinarios): la columna nueva
 * `emision_sancion_estado` usaba `->visible(fn (?string $state) => ...)` -
 * pedir `$state` en el `->visible()` de una COLUMNA (no de una celda) fuerza
 * a Filament a calcular el estado contra un $record, pero ->visible() de
 * columna también se evalúa al construir el menú de "columnas visibles"
 * (Filament\Tables\Concerns\CanToggleColumns), donde NO hay ningún $record
 * en contexto - `Column::hasRelationship()` recibía null y explotaba
 * (TypeError, ver HasCellState.php). Fix: sin ->visible() en esa columna,
 * `formatStateUsing()` devuelve null para que el badge simplemente no se
 * renderice cuando no hay estado.
 *
 * A diferencia de otros tests de este recurso que verifican por inspección
 * de fuente (Livewire::test() de esta página falla con "Invalid Livewire
 * snapshot structure", limitación pre-existente documentada en
 * ListProcesoDisciplinariosDecisionSancionListenerTest), un GET HTTP normal
 * SÍ renderiza la página completa igual que en producción - es la única
 * forma real de atrapar esta clase de bug (crash al montar la tabla, no en
 * el render de una fila).
 */
class ListProcesoDisciplinariosRenderaSinErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_lista_de_procesos_disciplinarios_carga_sin_error_500(): void
    {
        Permission::findOrCreate('view_any_proceso::disciplinario', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo('view_any_proceso::disciplinario');
        $this->actingAs($user);

        $response = $this->get(ProcesoDisciplinarioResource::getUrl('index'));

        $response->assertOk();
    }
}
