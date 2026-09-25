<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario (2026-09-25): la foto de referencia del
 * trabajador (subida por admin o tomada en la socialización del RIT, ver
 * SocializacionRit::validarFotoConIA()) se guarda correctamente en el disco
 * 'local' (privado), pero el enlace "abrir"/"descargar" de Filament
 * apuntaba a una URL /storage/... rota - ese disco no tiene raíz pública
 * (storage/app/private, no storage/app/public). Esta ruta autenticada sirve
 * el archivo real en su lugar.
 */
class FotoReferenciaTrabajadorRutaTest extends TestCase
{
    use RefreshDatabase;

    private function crearTrabajador(?string $fotoPath = null): Trabajador
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        return Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '111222333',
            'genero' => 'masculino', 'nombres' => 'Foto', 'apellidos' => 'Prueba', 'cargo' => 'Op',
            'active' => true, 'foto_referencia_path' => $fotoPath,
        ]);
    }

    public function test_sirve_la_foto_real_a_un_usuario_autenticado(): void
    {
        Storage::fake('local');
        $ruta = 'fotos_referencia/1_test.jpg';
        Storage::disk('local')->put($ruta, 'contenido-de-prueba-jpg');
        $trabajador = $this->crearTrabajador($ruta);

        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        $response = $this->actingAs($user)->get(route('admin.foto-referencia-trabajador', $trabajador));

        $response->assertOk();
        $response->assertSee('contenido-de-prueba-jpg', false);
    }

    public function test_devuelve_404_si_el_trabajador_no_tiene_foto(): void
    {
        $trabajador = $this->crearTrabajador(null);
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        $this->actingAs($user)
            ->get(route('admin.foto-referencia-trabajador', $trabajador))
            ->assertNotFound();
    }

    public function test_devuelve_404_si_el_archivo_no_existe_en_disco(): void
    {
        Storage::fake('local');
        $trabajador = $this->crearTrabajador('fotos_referencia/no-existe.jpg');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        $this->actingAs($user)
            ->get(route('admin.foto-referencia-trabajador', $trabajador))
            ->assertNotFound();
    }

    public function test_requiere_autenticacion(): void
    {
        $trabajador = $this->crearTrabajador('fotos_referencia/1_test.jpg');

        $this->get(route('admin.foto-referencia-trabajador', $trabajador))
            ->assertRedirect();
    }
}
