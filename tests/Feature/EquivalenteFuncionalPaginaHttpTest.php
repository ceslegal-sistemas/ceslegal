<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\EquivalenteFuncional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test HTTP del reporte "Equivalente Funcional" (item 1, reunión con
 * Andrés Sarmiento 2026-10-05) - verifica que la página renderiza sin error
 * 500 en cada una de sus 6 secciones. Se usa una visita HTTP real en vez de
 * Livewire::test() porque esa ruta de testing no se pudo hacer funcionar de
 * forma confiable con esta página (HasTable + InteractsWithTable sin mount
 * explícito) - ver memoria del proyecto.
 */
class EquivalenteFuncionalPaginaHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ninguna ruta de panel está exenta por Gate::before - super_admin
        // y cliente necesitan el permiso real page_EquivalenteFuncional.
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_la_pagina_carga_sin_error_para_super_admin(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($user);

        $this->get(EquivalenteFuncional::getUrl())
            ->assertOk()
            ->assertSee('Equivalente Funcional');
    }

    public function test_la_pagina_carga_sin_error_para_cliente(): void
    {
        $empresa = \App\Models\Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($user);

        // Cliente opera en el panel 'empresa', no 'admin' - EquivalenteFuncional
        // se auto-descubre en ambos (EmpresaPanelProvider::discoverPages() lee
        // el mismo directorio Filament/Admin/Pages), pero getUrl() necesita el
        // panel explícito o resuelve al primero registrado ('admin'), al que
        // un cliente no tiene acceso (mismo patrón ya usado en
        // GenerarTextoRITJob/GenerarVideoDidacticoRITJob).
        $this->get(EquivalenteFuncional::getUrl(panel: 'empresa'))->assertOk();
    }
}
