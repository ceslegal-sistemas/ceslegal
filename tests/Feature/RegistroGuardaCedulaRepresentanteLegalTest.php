<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Auth\Register;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-09-25): la cédula del representante
 * legal nunca se pedía en el registro, y quedaba inalcanzable para el
 * cliente (el campo está bloqueado en "Mi Empresa" tras el registro) - ver
 * backlog-cedula-representante-legal-inalcanzable. Se agrega al registro
 * como campo opcional.
 */
class RegistroGuardaCedulaRepresentanteLegalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
    }

    private function datosMinimos(array $overrides = []): array
    {
        return array_merge([
            'razon_social'        => 'Empresa de Prueba S.A.S.',
            'nit'                 => '900123456-1',
            'representante_legal' => 'Ana Prueba',
            'name'                => 'Ana Prueba',
            'email'               => 'ana@empresaprueba.co',
            'password'            => 'secret123',
        ], $overrides);
    }

    public function test_guarda_la_cedula_del_representante_legal_si_se_diligencio(): void
    {
        $page = new Register();
        $page->crearCuentaEmpresa($this->datosMinimos(['representante_legal_cedula' => '1234567890']));

        $empresa = Empresa::where('nit', '900123456-1')->first();

        $this->assertSame('1234567890', $empresa->representante_legal_cedula);
    }

    public function test_no_falla_si_la_cedula_no_se_diligencio(): void
    {
        $page = new Register();
        $page->crearCuentaEmpresa($this->datosMinimos());

        $empresa = Empresa::where('nit', '900123456-1')->first();

        $this->assertNull($empresa->representante_legal_cedula);
    }
}
