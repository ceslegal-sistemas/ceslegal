<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Bug real encontrado (2026-10-22): en la ficha (infolist) de un Proceso
 * Disciplinario, el formateador de 'estado' tenía claves inventadas
 * ('abierto', 'citado', 'impugnado') que NUNCA coinciden con los valores
 * reales que escribe EstadoProcesoService::TRANSICIONES_VALIDAS ('apertura',
 * 'impugnacion_realizada' - 'citado' no existe) - cualquier proceso en
 * 'impugnacion_realizada' caía al default => ucfirst($state) y se mostraba
 * literalmente "Impugnacion_realizada" en pantalla.
 */
class ProcesoDisciplinarioEstadoInfolistSinGuionBajoTest extends TestCase
{
    use RefreshDatabase;

    public function test_estado_impugnacion_realizada_se_muestra_sin_guion_bajo(): void
    {
        Permission::findOrCreate('view_any_proceso::disciplinario', 'web');
        Permission::findOrCreate('view_proceso::disciplinario', 'web');
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $admin->givePermissionTo(['view_any_proceso::disciplinario', 'view_proceso::disciplinario']);
        $this->actingAs($admin);

        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
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
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0001',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
            'estado' => 'impugnacion_realizada',
        ]);

        $response = $this->get(ProcesoDisciplinarioResource::getUrl('view', ['record' => $proceso]));

        $response->assertOk();
        $response->assertSee('Impugnación Realizada');
        $response->assertDontSee('Impugnacion_realizada');
        $response->assertDontSee('impugnacion_realizada');
    }
}
