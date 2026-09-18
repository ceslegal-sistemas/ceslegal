<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages\ListProcesoDisciplinarios;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hallazgo real del usuario (2026-09-18): tras dispatchar
 * GenerarYEnviarSancionJob, el usuario vuelve al listado sin ninguna pista de
 * qué pasó ni dónde mirar - "si yo entro por primera vez... quedo perdido".
 * Mismo patrón ya probado en SolicitudContratoResource (ver
 * SolicitudContratoRedireccionAlListadoTest): se resalta la fila vía
 * ->recordClasses() + un flash de sesión, y la notificación apunta
 * explícitamente a esa fila resaltada.
 */
class ProcesoDisciplinarioResaltaFilaGenerandoSancionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function crearProceso(): ProcesoDisciplinario
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => uniqid(),
            'genero' => 'masculino',
            'nombres' => 'Carlos',
            'apellidos' => 'Ruiz',
            'cargo' => 'Vendedor',
            'email' => 'carlos@test.com',
            'telefono' => '3002223333',
            'direccion' => 'Calle 3',
            'active' => true,
        ]);

        return ProcesoDisciplinario::create([
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);
    }

    public function test_recordClasses_resalta_solo_la_fila_marcada_como_generando_sancion(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'active' => true]));

        $procesoGenerando = $this->crearProceso();
        $procesoNormal = $this->crearProceso();

        session()->flash('proceso_disciplinario_generando_sancion', $procesoGenerando->id);

        $table = ProcesoDisciplinarioResource::table(Table::make(new ListProcesoDisciplinarios()));

        $clasesGenerando = $table->getRecordClasses($procesoGenerando);
        $clasesNormal = $table->getRecordClasses($procesoNormal);

        $this->assertNotEmpty($clasesGenerando, 'La fila con sanción en proceso debe resaltarse.');
        $this->assertEmpty($clasesNormal, 'Una fila distinta no debe resaltarse.');
    }

    public function test_los_4_puntos_dejan_el_flash_de_sesion_para_resaltar_la_fila(): void
    {
        $fuente = file_get_contents(app_path('Filament/Admin/Resources/ProcesoDisciplinarioResource.php'));

        $this->assertSame(
            4,
            substr_count($fuente, "session()->flash('proceso_disciplinario_generando_sancion', \$record->id);"),
            'Los 4 puntos que despachan el job deben dejar el flash para resaltar la fila.'
        );
    }
}
