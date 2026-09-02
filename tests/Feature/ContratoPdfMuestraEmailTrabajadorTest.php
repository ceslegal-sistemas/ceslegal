<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pedido explícito del usuario: el borrador del contrato debe mostrar el
 * correo electrónico del trabajador, igual que ya mostraba teléfono y
 * dirección. Verifica las 3 plantillas reales (las únicas con documento
 * base provisto por el usuario - ver memoria solicitud-contrato-plantilla-
 * real-vs-generada.md).
 */
class ContratoPdfMuestraEmailTrabajadorTest extends TestCase
{
    private function datosBase(): array
    {
        return [
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

    public function test_termino_fijo_muestra_el_email_del_trabajador(): void
    {
        $html = view('pdfs.contratos.termino-fijo', $this->datosBase())->render();

        $this->assertStringContainsString('CORREO ELECTRÓNICO DEL TRABAJADOR', $html);
        $this->assertStringContainsString('juan.perez@empresa.com', $html);
    }

    public function test_termino_indefinido_muestra_el_email_del_trabajador(): void
    {
        $html = view('pdfs.contratos.termino-indefinido', $this->datosBase())->render();

        $this->assertStringContainsString('CORREO ELECTRÓNICO DEL TRABAJADOR', $html);
        $this->assertStringContainsString('juan.perez@empresa.com', $html);
    }

    public function test_obra_labor_muestra_el_email_del_trabajador(): void
    {
        $html = view('pdfs.contratos.obra-labor', $this->datosBase())->render();

        $this->assertStringContainsString('CORREO ELECTRÓNICO DEL TRABAJADOR', $html);
        $this->assertStringContainsString('juan.perez@empresa.com', $html);
    }
}
