<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MiReglamentoInternoBotonCompartirTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El contenido del banner se extrajo a un partial reutilizable (ver
     * rit-compartir-banner.blade.php, usado también en la tarjeta del
     * Dashboard) - estas 2 aserciones ahora verifican el partial, no la
     * página que lo incluye.
     */
    public function test_incluye_el_banner_de_compartir_con_los_3_canales(): void
    {
        $fuente = file_get_contents(resource_path('views/filament/components/rit-compartir-banner.blade.php'));

        $this->assertStringContainsString('urlSocializacionRit', $fuente);
        $this->assertStringContainsString('navigator.clipboard.writeText', $fuente);
        $this->assertStringContainsString('wa.me', $fuente);
        $this->assertStringContainsString('mailto:', $fuente);
    }

    /**
     * Compartir nativo (pedido del usuario, 2026-10-03): un botón "Compartir"
     * usando la Web Share API (navigator.share) en vez de botones separados
     * de WhatsApp/Correo - con esos dos como respaldo SOLO si el navegador
     * no soporta navigator.share (ej. Firefox de escritorio).
     */
    public function test_incluye_compartir_nativo_con_respaldo_a_whatsapp_y_correo(): void
    {
        $fuente = file_get_contents(resource_path('views/filament/components/rit-compartir-banner.blade.php'));

        $this->assertStringContainsString('navigator.share', $fuente);
        $this->assertStringContainsString('soportaCompartir', $fuente);
        $this->assertStringContainsString('x-show="soportaCompartir"', $fuente);
        $this->assertStringContainsString('x-show="!soportaCompartir"', $fuente);
    }

    public function test_incluye_el_boton_de_poster_qr(): void
    {
        $fuente = file_get_contents(resource_path('views/filament/components/rit-compartir-banner.blade.php'));

        $this->assertStringContainsString('posterUrl', $fuente);
        // "Poster QR" se renombró a "Imprimir cartel con QR" (pedido del
        // usuario, 2026-10-03: el nombre técnico no explicaba para qué servía).
        $this->assertStringContainsString('Imprimir cartel con QR', $fuente);
    }

    /**
     * Este estilo vive en el <style> de la propia página (no se movió al
     * partial), así que sigue verificándose sobre mi-reglamento-interno.blade.php.
     */
    public function test_el_input_del_link_tiene_estilos_de_contraste_para_ambos_modos(): void
    {
        $fuente = file_get_contents(resource_path('views/filament/pages/mi-reglamento-interno.blade.php'));

        $this->assertStringContainsString('rit-link-input', $fuente);
        $this->assertMatchesRegularExpression('/\.rit-link-input\{[^}]*background:rgba\(255,255,255,\.06\)/', $fuente);
        $this->assertMatchesRegularExpression('/html:not\(\.dark\) \.rit-link-input\{[^}]*background:#fff/', $fuente);
    }

    /**
     * Bug real reportado por el usuario (2026-09-24): el campo del link se
     * veía sin estilo (angosto, sin diseño) en la tarjeta del Dashboard,
     * porque .rit-link-input solo existía en el <style> de esta página y
     * nunca se movió al partial compartido lupe-hero-styles.blade.php que
     * usa la tarjeta del Dashboard.
     */
    public function test_el_estilo_del_link_tambien_esta_en_el_partial_compartido(): void
    {
        $fuente = file_get_contents(resource_path('views/filament/components/lupe-hero-styles.blade.php'));

        $this->assertStringContainsString('.rit-link-input', $fuente);
    }

    /**
     * Pedido del usuario (2026-09-24): la barra de progreso de aceptación
     * ("X de Y trabajadores...") que ya existía en la tarjeta del Dashboard
     * también debe verse aquí, para que ambos banners luzcan consistentes.
     */
    public function test_muestra_la_barra_de_progreso_de_aceptacion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'numero_empleados' => null]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
        ]);
        Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '111222333',
            'genero' => 'masculino', 'nombres' => 'Sin', 'apellidos' => 'Aceptar', 'cargo' => 'Op', 'active' => true,
        ]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        Livewire::actingAs($user)->test(MiReglamentoInterno::class)
            ->assertSee('0 de 1 trabajadores han aceptado el Reglamento Interno vigente');
    }
}
