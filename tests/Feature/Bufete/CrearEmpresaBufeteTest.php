<?php

namespace Tests\Feature\Bufete;

use App\Filament\Admin\Resources\EmpresaResource\Pages\CreateEmpresa;
use App\Models\Bufete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class CrearEmpresaBufeteTest extends TestCase
{
    use RefreshDatabase;

    private function mutate(CreateEmpresa $page, array $data): array
    {
        $m = new ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $m->setAccessible(true);

        return $m->invoke($page, $data);
    }

    public function test_abogado_de_bufete_asigna_bufete_id(): void
    {
        $bufete = Bufete::factory()->create();
        $abogado = User::factory()->create(['role' => 'bufete', 'bufete_id' => $bufete->id]);
        $this->actingAs($abogado);

        $data = $this->mutate(new CreateEmpresa(), ['razon_social' => 'Empresa X']);

        $this->assertSame($bufete->id, $data['bufete_id']);
    }

    public function test_super_admin_no_fuerza_bufete_id(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($super);

        $data = $this->mutate(new CreateEmpresa(), ['razon_social' => 'Empresa Y']);

        $this->assertArrayNotHasKey('bufete_id', $data);
    }
}
