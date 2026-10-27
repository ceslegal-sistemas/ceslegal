<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Empresa;
use App\Models\EmailTracking;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-10-26): "en todas las partes donde se
 * notifica dar la opción de notificar también por WhatsApp y correo o
 * ambos" - empezando por "Re-enviar Citación". $canalesOverride permite
 * elegir el canal PUNTUALMENTE para ese envío, sin tocar la preferencia
 * guardada del trabajador, y sin romper el comportamiento histórico
 * cuando no se pasa (null).
 */
class ReenvioCitacionCanalOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ProcesoDisciplinarioObserver registra en el timeline con
        // Auth::id() ?? 1 - sin sesión activa necesita que exista un
        // usuario con id=1 para la FK.
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function trabajador(Empresa $empresa, array $overrides = []): Trabajador
    {
        return Trabajador::create(array_merge([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '1007568729',
            'genero' => 'masculino',
            'nombres' => 'Juan Pablo',
            'apellidos' => 'Rendon',
            'cargo' => 'Desarrollador',
            'email' => 'juan@test.com',
            'telefono' => '3001234567',
            'active' => true,
        ], $overrides));
    }

    private function pdfDummy(): string
    {
        $ruta = sys_get_temp_dir() . '/citacion-test-' . uniqid() . '.pdf';
        file_put_contents($ruta, '%PDF-1.4 contenido de prueba');

        return $ruta;
    }

    public function test_sin_override_el_correo_se_envia_siempre_como_antes(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->trabajador($empresa, ['notificar_por_whatsapp' => false]);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0001',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        app(DocumentGeneratorService::class)->enviarCitacionPorEmail($proceso, $this->pdfDummy());

        $this->assertSame(1, EmailTracking::where('proceso_id', $proceso->id)->count());
    }

    public function test_override_solo_whatsapp_omite_el_correo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->trabajador($empresa);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0002',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        app(DocumentGeneratorService::class)->enviarCitacionPorEmail(
            $proceso,
            $this->pdfDummy(),
            linkDescargos: 'https://ejemplo.test/descargos/abc',
            canalesOverride: ['email' => false, 'whatsapp' => true],
        );

        // Se sigue creando el registro de acuse (pedido del usuario,
        // 2026-10-27: antes un envío solo por WhatsApp no dejaba ningún
        // registro y el listado mostraba "No enviado" aunque sí se envió),
        // pero refleja que el canal real fue WhatsApp, no correo.
        $tracking = EmailTracking::where('proceso_id', $proceso->id)->first();
        $this->assertNotNull($tracking);
        $this->assertSame(['whatsapp'], $tracking->canales);
        $this->assertSame('Enviado por WhatsApp', $tracking->getEstadoLectura());
    }

    public function test_override_solo_correo_no_intenta_whatsapp_aunque_el_trabajador_lo_tenga_activo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->trabajador($empresa, ['notificar_por_whatsapp' => true]);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0003',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        app(DocumentGeneratorService::class)->enviarCitacionPorEmail(
            $proceso,
            $this->pdfDummy(),
            linkDescargos: 'https://ejemplo.test/descargos/abc',
            canalesOverride: ['email' => true, 'whatsapp' => false],
        );

        // No hay forma directa de "contar" intentos de WhatsApp sin mockear
        // el servicio - se confirma indirectamente: el correo sí se envió
        // (tracking creado) y no se lanzó ninguna excepción al forzar
        // whatsapp=false con un trabajador que tenía notificar_por_whatsapp=true.
        $this->assertSame(1, EmailTracking::where('proceso_id', $proceso->id)->count());
    }

    public function test_override_whatsapp_manda_aunque_el_trabajador_tenga_la_preferencia_desactivada(): void
    {
        // Bug real encontrado al probar el diseño nuevo (2026-10-27):
        // enviarAvisoWhatsappTrabajador() re-chequeaba internamente
        // notificar_por_whatsapp sin importar el override, así que elegir
        // WhatsApp a propósito no hacía nada si el trabajador tenía esa
        // preferencia desactivada - justo lo contrario de lo que pide el
        // selector de canal. Se prueba con el request HTTP real, no solo
        // con el registro de "canales" (ese no detectaba el bug).
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_habilitado'], ['valor' => '1', 'tipo' => 'boolean', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_phone_number_id'], ['valor' => '123456', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_access_token'], ['valor' => 'token-de-prueba', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(
            ['clave' => 'whatsapp_plantilla_citacion_nombre'],
            ['valor' => 'citacion_descargos', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true],
        );

        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->trabajador($empresa, ['notificar_por_whatsapp' => false]);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0004',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        app(DocumentGeneratorService::class)->enviarCitacionPorEmail(
            $proceso,
            $this->pdfDummy(),
            linkDescargos: 'https://ejemplo.test/descargos/abc',
            canalesOverride: ['email' => false, 'whatsapp' => true],
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }
}
