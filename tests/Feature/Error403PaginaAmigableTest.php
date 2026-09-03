<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hallazgo real del usuario (2026-09-02): un cliente que llegaba con sesión
 * vencida a una URL vieja se quedaba con la página 403 genérica y fea de
 * Laravel, sin ninguna salida. resources/views/errors/403.blade.php la
 * reemplaza con una página propia (mismo estilo .rit-hero del reskin de
 * contratos) que siempre ofrece "Volver al inicio", según el rol.
 */
class Error403PaginaAmigableTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_la_pagina_propia_y_manda_al_cliente_a_empresa(): void
    {
        $user = User::factory()->create(['role' => 'cliente', 'active' => true]);
        $this->actingAs($user);

        // create_modificacion::contractual no existe/no está concedido - la
        // acción real de un 403 real (Resource::canCreate() falla).
        $this->get(ModificacionContractualResource::getUrl('create'))
            ->assertForbidden()
            ->assertSee('No tiene permiso para ver esta página')
            ->assertSee('Volver al inicio')
            ->assertSee(url('/empresa'), false);
    }

    public function test_manda_a_otros_roles_a_admin(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($user);

        $this->get(ModificacionContractualResource::getUrl('create'))
            ->assertForbidden()
            ->assertSee('Volver al inicio')
            ->assertSee(url('/admin'), false);
    }
}
