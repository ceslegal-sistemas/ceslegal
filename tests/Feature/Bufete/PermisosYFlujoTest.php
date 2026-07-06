<?php

namespace Tests\Feature\Bufete;

use App\Filament\Admin\Pages\Auth\Register;
use App\Models\Bufete;
use App\Models\Empresa;
use App\Models\User;
use App\Support\EmpresaActiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermisosYFlujoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'bufete', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
    }

    public function test_puede_gestionar_rit_segun_rol_y_bufete(): void
    {
        $bufete = Bufete::factory()->create();
        $empresaLibre = Empresa::factory()->create(['bufete_id' => null]);
        $empresaConBufete = Empresa::factory()->create(['bufete_id' => $bufete->id]);

        $super = User::factory()->create(['role' => 'super_admin']);
        $abogado = User::factory()->create(['role' => 'bufete', 'bufete_id' => $bufete->id]);
        $clienteLibre = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresaLibre->id]);
        $clienteConBufete = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresaConBufete->id]);

        $this->assertTrue($super->puedeGestionarRit());
        $this->assertTrue($abogado->puedeGestionarRit());
        $this->assertTrue($clienteLibre->puedeGestionarRit(), 'cliente sin bufete gestiona su RIT');
        $this->assertFalse($clienteConBufete->puedeGestionarRit(), 'cliente gestionado por bufete NO audita RIT');
    }

    public function test_flujo_completo_bufete_multi_empresa(): void
    {
        // Registro de bufete → abogado dueño
        $abogado = (new Register())->crearCuentaBufete([
            'tipo_cuenta' => 'bufete',
            'bufete_nombre' => 'Firma Legal SAS',
            'name' => 'Ana', 'email' => 'ana@firma.co', 'password' => 'secret123',
        ]);
        $bufete = $abogado->bufete;
        $this->assertNotNull($bufete);

        // Dos empresas bajo el bufete + una ajena
        $e1 = Empresa::factory()->create(['bufete_id' => $bufete->id]);
        $e2 = Empresa::factory()->create(['bufete_id' => $bufete->id]);
        Empresa::factory()->create(); // ajena

        $cliente = User::factory()->create(['role' => 'cliente', 'empresa_id' => $e1->id]);

        // El abogado ve solo las empresas de su bufete; el selector acota
        $this->actingAs($abogado);
        $this->assertEqualsCanonicalizing([$e1->id, $e2->id], Empresa::pluck('id')->all());
        EmpresaActiva::set($e2->id);
        $this->assertEqualsCanonicalizing([$e2->id], Empresa::pluck('id')->all());
        EmpresaActiva::clear();

        // El cliente de e1 solo ve su empresa
        $this->actingAs($cliente);
        $this->assertEqualsCanonicalizing([$e1->id], Empresa::pluck('id')->all());

        // Permisos RIT: cliente gestionado por bufete NO; abogado sí
        $this->assertFalse($cliente->puedeGestionarRit());
        $this->assertTrue($abogado->puedeGestionarRit());
    }
}
