<?php

namespace Tests\Feature\Bufete;

use App\Models\Bufete;
use App\Models\Empresa;
use App\Models\User;
use App\Support\EmpresaActiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopeAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_por_rol(): void
    {
        $bufete = Bufete::factory()->create();
        $e1 = Empresa::factory()->create(['bufete_id' => $bufete->id]);
        $e2 = Empresa::factory()->create(['bufete_id' => $bufete->id]);
        $ajena = Empresa::factory()->create();

        $cliente = User::factory()->create(['role' => 'cliente', 'empresa_id' => $e1->id]);
        $abogado = User::factory()->create(['role' => 'abogado', 'bufete_id' => $bufete->id]);
        $super   = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($cliente);
        $this->assertEqualsCanonicalizing([$e1->id], Empresa::pluck('id')->all());

        $this->actingAs($abogado);
        $this->assertEqualsCanonicalizing([$e1->id, $e2->id], Empresa::pluck('id')->all());

        EmpresaActiva::set($e2->id);
        $this->assertEqualsCanonicalizing([$e2->id], Empresa::pluck('id')->all());
        EmpresaActiva::clear();

        $this->actingAs($super);
        $this->assertEqualsCanonicalizing([$e1->id, $e2->id, $ajena->id], Empresa::pluck('id')->all());
    }

    public function test_abogado_de_bufete_helper(): void
    {
        $bufete = Bufete::factory()->create();
        $abogado = User::factory()->create(['role' => 'abogado', 'bufete_id' => $bufete->id]);
        Empresa::factory()->count(3)->create(['bufete_id' => $bufete->id]);
        Empresa::factory()->create();
        $this->assertTrue($abogado->esAbogadoDeBufete());
        $this->actingAs($abogado);
        $this->assertCount(3, $abogado->empresasGestionadas()->get());
    }
}
