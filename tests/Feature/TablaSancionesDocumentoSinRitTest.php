<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mismo bug de CitacionDescargosHechosYNormasTest (PD-2026-0119, CES LEGAL
 * S.A.S. sin RIT) pero en la tabla de sanciones del documento de SANCIÓN
 * (construirTablaSancionesDeterministica()), que tenía la misma atribución
 * fabricada a "este Reglamento" cuando la empresa no tiene uno.
 */
class TablaSancionesDocumentoSinRitTest extends TestCase
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
            'numero_documento' => '111222333',
            'genero' => 'femenino',
            'nombres' => 'Trabajadora',
            'apellidos' => 'De Prueba',
            'cargo' => 'Auxiliar Administrativa',
            'email' => 'trabajadora.prueba@test.com',
            'telefono' => '3000000000',
            'direccion' => 'Calle Ficticia 1',
            'active' => true,
        ]);
    }

    public function test_no_dice_este_reglamento_en_la_tabla_de_sancion_cuando_la_empresa_no_tiene_rit(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);

        $conductaGenerica = 'Faltar un día al trabajo sin justa causa ni aviso';

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0119-SANCION',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'La trabajadora no se presentó a su puesto de trabajo a la hora establecida.',
            'sanciones_laborales_ids' => [$conductaGenerica],
        ]);

        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'construirTablaSancionesDeterministica');
        $metodo->setAccessible(true);

        $html = $metodo->invoke($service, $proceso, $empresa);

        $this->assertStringContainsString($conductaGenerica, $html);
        $this->assertStringNotContainsString('establecido en este Reglamento', $html);
        $this->assertStringContainsString('no tiene un Reglamento Interno de Trabajo registrado', $html);
    }
}
