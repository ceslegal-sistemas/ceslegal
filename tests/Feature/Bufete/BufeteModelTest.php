<?php

namespace Tests\Feature\Bufete;

use App\Models\Bufete;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BufeteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_bufete_tiene_muchas_empresas(): void
    {
        $bufete = Bufete::factory()->create();
        Empresa::factory()->count(2)->create(['bufete_id' => $bufete->id]);
        $this->assertCount(2, $bufete->fresh()->empresas);
    }

    public function test_empresa_pertenece_a_bufete(): void
    {
        $bufete = Bufete::factory()->create();
        $empresa = Empresa::factory()->create(['bufete_id' => $bufete->id]);
        $this->assertTrue($empresa->bufete->is($bufete));
    }
}
