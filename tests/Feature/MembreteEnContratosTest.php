<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MembreteEnContratosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * El partial del membrete comprueba is_file() sobre la ruta real del
     * logo - no basta con poner un logo_path cualquiera en la BD, hay que
     * escribir un archivo real en el disco fake para que el membrete
     * efectivamente se renderice.
     */
    private function crearEmpresaConLogo(): Empresa
    {
        Storage::disk('local')->put('logos/1/logo.png', 'contenido-de-prueba-png');

        return Empresa::factory()->create(['logo_path' => 'logos/1/logo.png', 'logo_color_acento' => '#E46350']);
    }

    private function datosBase(Empresa $empresa): array
    {
        return [
            'empresa' => $empresa,
            'nombreEmpresa' => 'EMPRESA ABC S.A.S',
            'nit' => '900123456-7',
            'direccionEmpresa' => 'Calle 1 # 2-3',
            'telefonoEmpresa' => '6011234567',
            'representanteLegal' => 'Carlos Ruiz',
            'representanteLegalCedula' => '123456',
            'nombreTrabajador' => 'Juan Pérez',
            'tipoDocumentoLabel' => 'cédula de ciudadanía',
            'numeroDocumento' => '1234567890',
            'direccionTrabajador' => 'Calle 4 # 5-6',
            'telefonoTrabajador' => '3001234567',
            'emailTrabajador' => 'juan.perez@empresa.com',
            'cargo' => 'Analista',
            'salarioFormateado' => '2.000.000',
            'salarioEnLetras' => 'DOS MILLONES DE PESOS',
            'periodoPagoLabel' => 'QUINCENAL',
            'periodoPagoFrase' => 'quince (15) días',
            'lugarLabores' => 'Bogotá',
            'lugarContratacion' => 'Bogotá',
            'fechaInicio' => '01/01/2026',
            'fechaFin' => '01/07/2026',
            'duracionTexto' => '6 meses',
            'fechaFirma' => '1 de enero de 2026',
            'objetoJuridico' => '',
            'diaDescansoObligatorio' => 'domingo',
            'descripcionObraLabor' => 'No especificada',
            'duracionTerminacionRedactada' => '',
            'faltasGravesOrigen' => 'cst',
            'faltasGravesGrave' => [],
            'faltasGravesGravisima' => [],
        ];
    }

    public function test_termino_fijo_incluye_el_membrete_si_la_empresa_tiene_logo(): void
    {
        $empresa = $this->crearEmpresaConLogo();

        $html = view('pdfs.contratos.termino-fijo', $this->datosBase($empresa))->render();

        $this->assertStringContainsString('membrete-pie', $html);
    }

    public function test_termino_indefinido_incluye_el_membrete_si_la_empresa_tiene_logo(): void
    {
        $empresa = $this->crearEmpresaConLogo();

        $html = view('pdfs.contratos.termino-indefinido', $this->datosBase($empresa))->render();

        $this->assertStringContainsString('membrete-pie', $html);
    }

    public function test_obra_labor_incluye_el_membrete_si_la_empresa_tiene_logo(): void
    {
        $empresa = $this->crearEmpresaConLogo();

        $html = view('pdfs.contratos.obra-labor', $this->datosBase($empresa))->render();

        $this->assertStringContainsString('membrete-pie', $html);
    }

    public function test_no_incluye_el_membrete_si_la_empresa_no_tiene_logo(): void
    {
        $empresa = Empresa::factory()->create(['logo_path' => null]);

        $html = view('pdfs.contratos.termino-fijo', $this->datosBase($empresa))->render();

        $this->assertStringNotContainsString('membrete-pie', $html);
    }
}
