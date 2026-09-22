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

    /**
     * Bug real reportado por el usuario (2026-09-22): la interfaz del
     * trabajador se veía sin estilo (botones invisibles) porque este host
     * page no tenía el tailwind.config con la paleta 'primary' que sí tiene
     * descargos/formulario.blade.php - sin ese config, clases como
     * bg-primary-600 no generan ningún estilo con el build CDN de Tailwind.
     */
    public function test_incluye_el_tailwind_config_con_la_paleta_primary(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $token = $empresa->tokenSocializacionRit();

        $response = $this->get('/rit/socializar/' . $token);

        $response->assertOk();
        $response->assertSee('tailwind.config', false);
        $response->assertSee("primary:", false);
        $response->assertSee('#e11d48', false);
    }

    /**
     * Hipótesis diagnosticada (2026-09-22): esta página lleva un token CSRF
     * y un snapshot de Livewire propios de cada visita - si un proxy/CDN
     * delante del hosting (ya documentado cacheando assets estáticos en
     * este mismo dominio) la cachea como una página normal, todos los
     * visitantes reciben el MISMO token CSRF congelado y el submit del
     * formulario falla siempre con 419 sin ningún aviso visible.
     */
    public function test_no_es_cacheable_por_un_proxy_o_cdn(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $token = $empresa->tokenSocializacionRit();

        $response = $this->get('/rit/socializar/' . $token);

        $response->assertOk();
        // Symfony normaliza el header (agrega max-age=0 y reordena), por eso
        // se verifica que contenga las directivas clave, no el string exacto.
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }
}
