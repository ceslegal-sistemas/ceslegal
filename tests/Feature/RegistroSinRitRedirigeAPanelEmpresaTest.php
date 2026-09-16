<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Auth\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario (2026-09-15/16): un cliente que se
 * registra sin subir ni construir el RIT (empresa no obligada por Art. 105
 * CST) recibía un error al terminar el registro, porque quedaba redirigido a
 * /admin en vez de /empresa - un usuario 'cliente' no puede acceder a /admin.
 *
 * Causa raíz confirmada: Register::crearCuentaEmpresa() solo fijaba
 * $this->redirectUrl en las ramas $ritOpcion==='tiene' y
 * $ritOpcion==='construir'. Cuando $ritOpcion quedaba en 'despues' (caso no
 * obligado), $redirectUrl nunca se fijaba, y getRedirectUrl() caía al
 * comportamiento por defecto de Filament: redirigir al panel desde donde se
 * envió el formulario (siempre 'admin', ver comentario en el código).
 */
class RegistroSinRitRedirigeAPanelEmpresaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
    }

    private function datosMinimosSinRit(): array
    {
        return [
            'razon_social'        => 'Empresa de Prueba S.A.S.',
            'nit'                 => '900123456-1',
            'representante_legal' => 'Ana Prueba',
            'name'                => 'Ana Prueba',
            'email'               => 'ana@empresaprueba.co',
            'password'            => 'secret123',
            // Sin numero_empleados/actividad_economica_id: ObligacionRit::requiere()
            // devuelve null (no obligada), rit_opcion se queda en 'despues'.
        ];
    }

    public function test_registro_sin_rit_redirige_al_panel_empresa_no_admin(): void
    {
        $page = new Register();
        $page->crearCuentaEmpresa($this->datosMinimosSinRit());

        $url = $page->getRedirectUrl();

        $this->assertStringContainsString('/empresa', $url);
        $this->assertStringNotContainsString('/admin', $url);
    }
}
