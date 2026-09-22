<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\TrabajadorResource;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Verificación extra (no exigida por el plan, agregada por precaución): el
 * mismo tipo de bug real ocurrido esta sesión en ProcesoDisciplinarioResource
 * (un ->visible() de columna evaluado sin $record durante la construcción
 * del menú de "columnas visibles" tumbaba la página con 500) pudo haber
 * pasado aquí también con la columna nueva "RIT vigente" - un GET real a la
 * página completa es la única forma de atraparlo, no basta con probar la
 * lógica de la columna en aislado (ver los tests de
 * TrabajadorResourceColumnaRitVigenteTest, que SÍ pasan y aun así no
 * hubieran detectado ese bug si existiera).
 */
class ListTrabajadoresRenderaSinErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_lista_de_trabajadores_carga_sin_error_500(): void
    {
        Permission::findOrCreate('view_any_trabajador', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo('view_any_trabajador');
        $this->actingAs($user);

        // Al menos una fila con la columna "RIT vigente" en ambos estados
        // (pendiente y aceptado) para que el render ejercite las dos ramas.
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '999000111',
            'genero' => 'masculino', 'nombres' => 'Render', 'apellidos' => 'Prueba', 'cargo' => 'X', 'active' => true,
        ]);

        $response = $this->get(TrabajadorResource::getUrl('index'));

        $response->assertOk();
    }
}
