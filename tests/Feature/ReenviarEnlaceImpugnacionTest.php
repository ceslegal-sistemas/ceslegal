<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pedido del usuario (2026-10-27): agregar el canal WhatsApp también al
 * botón "Enlace de impugnación" (históricamente solo correo), dejándolo
 * visible pero deshabilitado hasta que exista la plantilla
 * whatsapp_plantilla_enlace_impugnacion_nombre (pendiente de aprobación en
 * Meta) - una vez configurada, debe funcionar sin más cambios de código.
 */
class ReenviarEnlaceImpugnacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function proceso(array $trabajadorOverrides = []): ProcesoDisciplinario
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create(array_merge([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '1007568733',
            'genero' => 'masculino',
            'nombres' => 'Pedro',
            'apellidos' => 'Lopez',
            'cargo' => 'Operario',
            'email' => 'pedro@test.com',
            'telefono' => '3005554433',
            'active' => true,
        ], $trabajadorOverrides));

        return ProcesoDisciplinario::create([
            'codigo' => 'PD-ENL-' . uniqid(),
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);
    }

    public function test_sin_override_envia_solo_por_correo_como_antes(): void
    {
        Mail::fake();

        $proceso = $this->proceso();

        app(DocumentGeneratorService::class)->reenviarEnlaceImpugnacion($proceso);

        Mail::assertSent(\App\Mail\ImpugnacionEnlace::class);
    }

    public function test_override_whatsapp_sin_plantilla_configurada_no_truena_y_no_envia_nada_por_whatsapp(): void
    {
        Mail::fake();
        Http::fake();

        $proceso = $this->proceso();

        app(DocumentGeneratorService::class)->reenviarEnlaceImpugnacion(
            $proceso,
            ['email' => true, 'whatsapp' => true],
        );

        Mail::assertSent(\App\Mail\ImpugnacionEnlace::class);
        Http::assertNothingSent();
    }

    public function test_override_whatsapp_con_plantilla_configurada_la_envia(): void
    {
        Mail::fake();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        Configuracion::updateOrCreate(['clave' => 'whatsapp_habilitado'], ['valor' => '1', 'tipo' => 'boolean', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_phone_number_id'], ['valor' => '123456', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_access_token'], ['valor' => 'token-de-prueba', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(
            ['clave' => 'whatsapp_plantilla_enlace_impugnacion_nombre'],
            ['valor' => 'enlace_impugnacion', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true],
        );

        $proceso = $this->proceso();

        app(DocumentGeneratorService::class)->reenviarEnlaceImpugnacion(
            $proceso,
            ['email' => false, 'whatsapp' => true],
        );

        Mail::assertNothingSent();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }
}
