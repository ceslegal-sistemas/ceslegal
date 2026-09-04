<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MembreteEnCitacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
        Storage::fake('local');

        // asegurarClasificacionIncidente() (fix reciente) llamaría a la IA
        // real - este test es sobre el membrete, no sobre la clasificación,
        // así que se mockea fail-open (sin conducta) para no depender de
        // una llamada real a Gemini.
        $this->mock(\App\Services\IADescargoService::class, function ($mock) {
            $mock->shouldReceive('clasificarIncidente')->andReturn(['conducta_rit_aplicable' => '']);
        });
    }

    private function invocarGenerarHTML(ProcesoDisciplinario $proceso): string
    {
        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'generarHTMLCitacionDescargos');
        $metodo->setAccessible(true);

        return $metodo->invoke($service, $proceso);
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

    public function test_la_citacion_incluye_el_membrete_si_la_empresa_tiene_logo(): void
    {
        Storage::disk('local')->put('logos/1/logo.png', 'contenido-de-prueba-png');
        $empresa = Empresa::factory()->create(['logo_path' => 'logos/1/logo.png', 'logo_color_acento' => '#E46350']);
        $trabajador = $this->crearTrabajador($empresa);

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-MEMBRETE-1',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba para el membrete de la citación.',
        ]);

        $html = $this->invocarGenerarHTML($proceso);

        $this->assertStringContainsString('membrete-pie', $html);
    }

    public function test_no_incluye_el_membrete_si_la_empresa_no_tiene_logo(): void
    {
        $empresa = Empresa::factory()->create(['logo_path' => null]);
        $trabajador = $this->crearTrabajador($empresa);

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-MEMBRETE-2',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba sin logo.',
        ]);

        $html = $this->invocarGenerarHTML($proceso);

        $this->assertStringNotContainsString('membrete-pie', $html);
    }
}
