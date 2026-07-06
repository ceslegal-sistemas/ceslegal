<?php

namespace Tests\Feature\Bufete;

use App\Livewire\SelectorEmpresa;
use App\Models\Bufete;
use App\Models\Empresa;
use App\Models\User;
use App\Support\EmpresaActiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SelectorEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_selector_solo_acepta_empresas_del_bufete(): void
    {
        $bufete = Bufete::factory()->create();
        $e = Empresa::factory()->create(['bufete_id' => $bufete->id]);
        $ajena = Empresa::factory()->create();
        $abogado = User::factory()->create(['role' => 'abogado', 'bufete_id' => $bufete->id]);
        $this->actingAs($abogado);

        Livewire::test(SelectorEmpresa::class)->call('seleccionar', $e->id);
        $this->assertSame($e->id, EmpresaActiva::id());

        // una empresa ajena al bufete no cambia la selección
        Livewire::test(SelectorEmpresa::class)->call('seleccionar', $ajena->id);
        $this->assertSame($e->id, EmpresaActiva::id());

        Livewire::test(SelectorEmpresa::class)->call('todas');
        $this->assertNull(EmpresaActiva::id());
    }
}
