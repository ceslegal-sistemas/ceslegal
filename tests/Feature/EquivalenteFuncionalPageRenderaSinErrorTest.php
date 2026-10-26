<?php
namespace Tests\Feature;

use App\Filament\Admin\Pages\EquivalenteFuncional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EquivalenteFuncionalPageRenderaSinErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_carga_sin_error_500(): void
    {
        Permission::findOrCreate('page_EquivalenteFuncional', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo('page_EquivalenteFuncional');
        $this->actingAs($user);

        $response = $this->get(EquivalenteFuncional::getUrl());

        $response->assertOk();
    }
}
