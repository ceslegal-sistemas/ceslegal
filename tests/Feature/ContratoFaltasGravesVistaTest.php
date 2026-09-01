<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContratoFaltasGravesVistaTest extends TestCase
{
    private function datosBase(): array
    {
        return [
            'nombreEmpresa' => 'EDIFIC S.A.',
            'nit' => '123456',
            'direccionEmpresa' => 'cra 1',
            'telefonoEmpresa' => '3000000000',
            'representanteLegal' => 'Pepito Pérez',
            'representanteLegalCedula' => '123',
            'nombreTrabajador' => 'Stiven Hernández',
            'tipoDocumentoLabel' => 'cédula de ciudadanía',
            'numeroDocumento' => '123456789',
            'direccionTrabajador' => 'cra 89',
            'telefonoTrabajador' => '3000000001',
            'cargo' => 'Bodeguero',
            'salarioFormateado' => '2.000.000',
            'salarioEnLetras' => 'dos millones de pesos',
            'periodoPagoLabel' => 'QUINCENAL',
            'periodoPagoFrase' => 'cada quince (15) días',
            'lugarLabores' => 'Medellín',
            'lugarContratacion' => 'Medellín',
            'fechaInicio' => '01/09/2026',
            'fechaFirma' => '1 de septiembre de 2026',
            'diaDescansoObligatorio' => 'domingo',
            'objetoJuridico' => '',
            'faltasGravesOrigen' => 'sin_rit',
            'faltasGravesGrave' => [],
            'faltasGravesGravisima' => [],
        ];
    }

    public function test_no_agrega_nada_cuando_el_origen_no_es_rit(): void
    {
        $html = view('pdfs.contratos.termino-indefinido', $this->datosBase())->render();

        $this->assertStringNotContainsString('se consideran también', $html);
    }

    public function test_agrega_las_conductas_del_rit_cuando_el_origen_es_rit(): void
    {
        $html = view('pdfs.contratos.termino-indefinido', array_merge($this->datosBase(), [
            'faltasGravesOrigen' => 'rit',
            'faltasGravesGrave' => ['Llegar tarde de forma reiterada'],
            'faltasGravesGravisima' => ['Agredir físicamente a un compañero'],
        ]))->render();

        $this->assertStringContainsString('se consideran también', $html);
        $this->assertStringContainsString('Llegar tarde de forma reiterada', $html);
        $this->assertStringContainsString('Agredir físicamente a un compañero', $html);
    }
}
