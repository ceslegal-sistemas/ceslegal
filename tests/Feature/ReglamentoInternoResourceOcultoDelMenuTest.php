<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ReglamentoInternoResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Construir RIT" oculto del menú a pedido explícito del usuario (2026-09-28):
 * su propio getNavigationUrl() redirige a "Mi Reglamento Interno" (que ya
 * tiene el botón "Construir Reglamento Interno con IA"), así que el ítem del
 * menú era redundante - un clic que aterrizaba en otra pantalla del menú.
 * Las páginas del Resource (create/edit) siguen existiendo y en uso real:
 * mi-reglamento-interno.blade.php enlaza directo a
 * ReglamentoInternoResource::getUrl('create').
 */
class ReglamentoInternoResourceOcultoDelMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_nunca_se_registra_en_el_menu_para_ningun_rol(): void
    {
        $cliente = User::factory()->create(['role' => 'cliente', 'active' => true]);
        $this->actingAs($cliente);
        $this->assertFalse(ReglamentoInternoResource::shouldRegisterNavigation());

        $superAdmin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($superAdmin);
        $this->assertFalse(ReglamentoInternoResource::shouldRegisterNavigation());

        $bufete = User::factory()->create(['role' => 'bufete', 'active' => true]);
        $this->actingAs($bufete);
        $this->assertFalse(ReglamentoInternoResource::shouldRegisterNavigation());
    }

    public function test_la_pagina_de_crear_sigue_existiendo_para_el_boton_de_mi_reglamento_interno(): void
    {
        $this->assertNotEmpty(ReglamentoInternoResource::getUrl('create'));
    }
}
