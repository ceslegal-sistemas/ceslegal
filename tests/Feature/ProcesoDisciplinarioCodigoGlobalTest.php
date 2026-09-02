<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mismo bug que SolicitudContratoCodigoGlobalTest, encontrado por
 * inspección preventiva tras confirmar el patrón en SolicitudContratoObserver:
 * ProcesoDisciplinarioObserver::generarCodigoUnico() (códigos PD-{año}-NNNN)
 * tenía el mismo problema - sin excluir ScopedToBufeteOrEmpresa, la primera
 * citación de una empresa nueva volvería a calcular "0001" aunque otra
 * empresa ya lo hubiera usado (`codigo` es único en TODA la tabla). No
 * llegó a reportarse en producción, pero era cuestión de tiempo.
 */
class ProcesoDisciplinarioCodigoGlobalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
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

    public function test_la_primera_citacion_de_una_empresa_nueva_no_choca_con_el_codigo_de_otra_empresa(): void
    {
        $empresaA = Empresa::factory()->create(['active' => true]);
        $empresaB = Empresa::factory()->create(['active' => true]);

        $userA = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresaA->id, 'active' => true]);
        $this->actingAs($userA);
        $procesoA = ProcesoDisciplinario::create([
            'empresa_id' => $empresaA->id,
            'trabajador_id' => $this->crearTrabajador($empresaA)->id,
            'hechos' => 'Hechos de prueba A.',
        ]);

        $anio = now()->year;
        $this->assertSame("PD-{$anio}-0001", $procesoA->codigo);

        $userB = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresaB->id, 'active' => true]);
        $this->actingAs($userB);
        $procesoB = ProcesoDisciplinario::create([
            'empresa_id' => $empresaB->id,
            'trabajador_id' => $this->crearTrabajador($empresaB)->id,
            'hechos' => 'Hechos de prueba B.',
        ]);

        $this->assertSame("PD-{$anio}-0002", $procesoB->codigo);
        $this->assertNotSame($procesoA->codigo, $procesoB->codigo);
    }
}
