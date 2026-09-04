<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Tests\TestCase;

class MembreteEmpresaPartialTest extends TestCase
{
    public function test_no_renderiza_nada_si_la_empresa_no_tiene_logo(): void
    {
        $empresa = Empresa::factory()->make(['logo_path' => null]);

        $html = view('pdfs.components.membrete-empresa', ['empresa' => $empresa])->render();

        $this->assertSame('', trim($html));
    }

    public function test_omite_el_pie_de_pagina_si_no_hay_datos_de_contacto(): void
    {
        $empresa = Empresa::factory()->make([
            'logo_path' => 'logos/1/logo.png',
            'logo_color_acento' => '#E46350',
            'direccion' => null,
            'telefono' => null,
            'email_contacto' => null,
        ]);

        $html = view('pdfs.components.membrete-empresa', ['empresa' => $empresa])->render();

        $this->assertStringNotContainsString('membrete-pie', $html);
    }
}
