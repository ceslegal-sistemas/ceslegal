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
     * Bug real reportado por el usuario (2026-09-22), "resuelto" en ese
     * momento con un <script src="https://cdn.tailwindcss.com"> + config
     * inline con la paleta 'primary'. Ese "arreglo" resultó ser la causa
     * raíz de un bug MÁS grave reportado después (2026-09-28): si ese script
     * de un dominio externo no carga en el navegador del visitante
     * (adblocker, VPN, red corporativa), la página entera pierde TODO el
     * estilo - el Tailwind CDN genera el 100% del CSS en el cliente, nada
     * queda si el script falla. La solución real: compilar la paleta con
     * Vite (resources/css/app.css, @theme) y servirla como CSS estático del
     * propio dominio - nunca depender de un script de terceros para el
     * estilo de una página pública.
     */
    public function test_usa_el_css_compilado_de_vite_y_no_el_cdn_fragil_de_tailwind(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $token = $empresa->tokenSocializacionRit();

        $response = $this->get('/rit/socializar/' . $token);

        $response->assertOk();
        $response->assertDontSee('cdn.tailwindcss.com', false);
        $response->assertDontSee('tailwind.config', false);
        $response->assertSee('/build/assets/app-', false);
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
