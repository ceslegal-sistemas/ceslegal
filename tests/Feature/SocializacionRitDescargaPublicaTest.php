<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ruta pública de descarga del RIT (pedido de Andrés Sarmiento en la reunión,
 * 2026-09-29: el "Ver el Reglamento completo" expandible no basta, el
 * trabajador debe poder bajar el archivo) - misma autorización que
 * mostrar()/video(): poseer el token, no la sesión.
 */
class SocializacionRitDescargaPublicaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_token_invalido_da_404(): void
    {
        $response = $this->get('/rit/socializar/deadbeef/descargar');

        $response->assertNotFound();
    }

    public function test_sin_rit_activo_da_404(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $token = $empresa->tokenSocializacionRit();

        $response = $this->get("/rit/socializar/{$token}/descargar");

        $response->assertNotFound();
    }

    public function test_sirve_el_pdf_ya_generado_si_existe(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        Storage::disk('local')->put('rit-pdfs/1/rit.pdf', '%PDF-fake-bytes');
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'ruta_pdf' => 'rit-pdfs/1/rit.pdf',
        ]);
        $token = $empresa->tokenSocializacionRit();

        $response = $this->get("/rit/socializar/{$token}/descargar");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_genera_el_pdf_al_vuelo_si_no_existe_uno_guardado(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'Contenido del RIT',
        ]);
        $token = $empresa->tokenSocializacionRit();

        $response = $this->get("/rit/socializar/{$token}/descargar");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Bug real ya corregido una vez (mostrar()/video()) - una sesión autenticada
     * de OTRO rol/empresa abierta en el mismo navegador no debe interferir con
     * la resolución del token.
     */
    public function test_resuelve_la_empresa_correcta_aunque_haya_otra_sesion_autenticada(): void
    {
        $empresaDelToken = Empresa::factory()->create(['active' => true]);
        Storage::disk('local')->put('rit-pdfs/1/rit.pdf', '%PDF-fake-bytes');
        ReglamentoInterno::create([
            'empresa_id' => $empresaDelToken->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'ruta_pdf' => 'rit-pdfs/1/rit.pdf',
        ]);
        $token = $empresaDelToken->tokenSocializacionRit();

        $otraEmpresa = Empresa::factory()->create(['active' => true]);
        $usuarioDeOtraEmpresa = \App\Models\User::factory()->create([
            'role' => 'cliente', 'empresa_id' => $otraEmpresa->id, 'active' => true,
        ]);
        $this->actingAs($usuarioDeOtraEmpresa);

        $response = $this->get("/rit/socializar/{$token}/descargar");

        $response->assertOk();
    }
}
