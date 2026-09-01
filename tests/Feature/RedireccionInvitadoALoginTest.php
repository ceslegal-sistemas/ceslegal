<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedireccionInvitadoALoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug real reportado por el usuario: un visitante sin sesión que caía en
     * una ruta protegida fuera de los paneles de Filament (middleware 'auth'
     * a secas, sin panel asociado) veía un error 500
     * (RouteNotFoundException: Route [login] not defined) en vez de que lo
     * mandaran a iniciar sesión - Filament no registra ninguna ruta llamada
     * 'login', solo una por panel (filament.admin.auth.login).
     */
    public function test_invitado_sin_sesion_es_redirigido_al_login_de_filament_en_vez_de_un_error_500(): void
    {
        // SolicitudContratoObserver registra la creación en el timeline con
        // Auth::id() ?? 1 ("usuario del sistema") - sin sesión activa en
        // este test necesita que exista un usuario con id=1 para la FK.
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $solicitud = SolicitudContrato::create([
            'empresa_id' => $empresa->id,
            'estado' => 'borrador',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123',
            'trabajador_email' => 'juan@test.com',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
        ]);

        $this->get(route('solicitud-contrato.descargar', $solicitud))
            ->assertRedirect('/admin/login');
    }
}
