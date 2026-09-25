<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario (2026-09-25): foto_referencia_path del
 * trabajador (subida por admin o tomada en la socialización del RIT, ver
 * SocializacionRit::validarFotoConIA()) se guarda correctamente en el disco
 * 'local' (privado), pero el enlace "abrir"/"descargar" de Filament
 * generaba una URL /storage/... rota - ese disco no tiene raíz pública
 * (storage/app/private, no storage/app/public).
 *
 * Fix genérico (no solo para este campo): Storage::disk('local')
 * ->buildTemporaryUrlsUsing() en AppServiceProvider::boot() habilita
 * temporaryUrl() para el driver local vía una ruta FIRMADA - es el mismo
 * mecanismo que Filament ya usa internamente para cualquier FileUpload con
 * ->disk('local')->visibility('private') en todo el panel.
 */
class FotoReferenciaTrabajadorRutaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Storage::fake() reemplaza la instancia del disco por una nueva, que
     * pierde el callback registrado por AppServiceProvider::boot() en el
     * arranque real de la app (buildTemporaryUrlsUsing vive en la instancia,
     * no en la config) - se vuelve a registrar aquí para simular el mismo
     * estado que tiene la app real.
     */
    private function fakearDiscoLocalConTemporaryUrl(): void
    {
        Storage::fake('local');
        Storage::disk('local')->buildTemporaryUrlsUsing(
            fn (string $path, \DateTimeInterface $expiration, array $options) => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'storage.local.temporary',
                $expiration,
                array_merge($options, ['path' => $path]),
            )
        );
    }

    public function test_storage_disk_local_genera_una_url_temporal_firmada(): void
    {
        $this->fakearDiscoLocalConTemporaryUrl();
        Storage::disk('local')->put('fotos_referencia/1_test.jpg', 'contenido-de-prueba');

        $url = Storage::disk('local')->temporaryUrl('fotos_referencia/1_test.jpg', now()->addMinutes(5));

        $this->assertStringContainsString('/storage/local/fotos_referencia/1_test.jpg', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_sirve_el_archivo_real_con_una_url_firmada_y_usuario_autenticado(): void
    {
        $this->fakearDiscoLocalConTemporaryUrl();
        Storage::disk('local')->put('fotos_referencia/1_test.jpg', 'contenido-de-prueba');
        $url = Storage::disk('local')->temporaryUrl('fotos_referencia/1_test.jpg', now()->addMinutes(5));

        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        $response = $this->actingAs($user)->get($url);

        $response->assertOk();
        $response->assertSee('contenido-de-prueba', false);
    }

    public function test_rechaza_una_url_sin_firma_valida(): void
    {
        $this->fakearDiscoLocalConTemporaryUrl();
        Storage::disk('local')->put('fotos_referencia/1_test.jpg', 'contenido-de-prueba');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        $this->actingAs($user)
            ->get('/storage/local/fotos_referencia/1_test.jpg')
            ->assertForbidden();
    }

    public function test_devuelve_404_si_el_archivo_no_existe(): void
    {
        $this->fakearDiscoLocalConTemporaryUrl();
        $url = Storage::disk('local')->temporaryUrl('fotos_referencia/no-existe.jpg', now()->addMinutes(5));
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        $this->actingAs($user)->get($url)->assertNotFound();
    }

    public function test_requiere_autenticacion(): void
    {
        $this->fakearDiscoLocalConTemporaryUrl();
        Storage::disk('local')->put('fotos_referencia/1_test.jpg', 'contenido-de-prueba');
        $url = Storage::disk('local')->temporaryUrl('fotos_referencia/1_test.jpg', now()->addMinutes(5));

        $this->get($url)->assertRedirect();
    }
}
