<?php

namespace Tests\Feature\Bufete;

use App\Models\Bufete;
use App\Models\Empresa;
use App\Models\User;
use App\Services\GuiaProcesoService;
use App\Support\EmpresaActiva;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuiaProcesoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // getUrl() de los resources necesita un panel actual.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        EmpresaActiva::clear();
    }

    private function svc(): GuiaProcesoService
    {
        return new GuiaProcesoService();
    }

    public function test_staff_interno_oculto(): void
    {
        $u = User::factory()->create(['role' => 'super_admin']);
        $this->assertSame('oculto', $this->svc()->para($u)['estado']);
    }

    public function test_bufete_sin_empresas_pide_crear_empresa(): void
    {
        $b = Bufete::factory()->create();
        $u = User::factory()->create(['role' => 'bufete', 'bufete_id' => $b->id]);

        $r = $this->svc()->para($u);

        $this->assertSame('sin_empresa', $r['estado']);
        $this->assertStringContainsString('empresa', strtolower($r['accion']['label']));
    }

    public function test_bufete_con_empresa_sin_seleccion(): void
    {
        $b = Bufete::factory()->create();
        Empresa::factory()->create(['bufete_id' => $b->id]);
        $u = User::factory()->create(['role' => 'bufete', 'bufete_id' => $b->id]);

        $this->assertSame('sin_seleccion', $this->svc()->para($u)['estado']);
    }

    public function test_cliente_sin_rit_paso_actual_es_rit(): void
    {
        $e = Empresa::factory()->create();
        $u = User::factory()->create(['role' => 'cliente', 'empresa_id' => $e->id]);

        $r = $this->svc()->para($u);

        $this->assertSame('ok', $r['estado']);
        $rit = collect($r['pasos'])->firstWhere('clave', 'rit');
        $this->assertSame('current', $rit['estado']);
        $this->assertStringContainsString('reglamento', strtolower($r['accion']['label']));
    }
}
