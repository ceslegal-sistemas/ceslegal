<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocializacionRitPublicoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_invalido_da_404(): void
    {
        $response = $this->get('/rit/socializar/token-que-no-existe');

        $response->assertNotFound();
    }

    public function test_token_valido_muestra_la_pagina(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $token = $empresa->tokenSocializacionRit();

        $response = $this->get('/rit/socializar/' . $token);

        $response->assertOk();
        $response->assertViewIs('rit.socializacion');
    }

    /**
     * Bug real ya corregido una vez en este proyecto (DescargoPublicoController):
     * una sesion autenticada de OTRO rol/empresa abierta en el mismo navegador
     * no debe interferir con la resolucion del token - el global scope
     * ScopedToBufeteOrEmpresa filtra por la empresa/bufete del usuario logueado
     * si no se excluye explicitamente.
     */
    public function test_resuelve_la_empresa_correcta_aunque_haya_otra_sesion_autenticada(): void
    {
        $empresaDelToken = Empresa::factory()->create(['active' => true]);
        $token = $empresaDelToken->tokenSocializacionRit();

        $otraEmpresa = Empresa::factory()->create(['active' => true]);
        $usuarioDeOtraEmpresa = \App\Models\User::factory()->create([
            'role' => 'cliente',
            'empresa_id' => $otraEmpresa->id,
            'active' => true,
        ]);
        $this->actingAs($usuarioDeOtraEmpresa);

        $response = $this->get('/rit/socializar/' . $token);

        $response->assertOk();
        $response->assertViewHas('empresa', fn ($empresa) => $empresa->id === $empresaDelToken->id);
    }
}
