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
 * Bug real encontrado investigando el reporte del usuario ("el acuse de
 * whatsapp dice esperando confirmacion de entrega aunque ya lo lei"):
 * enviarNotificacionEstadoDescargos() creaba el EmailTracking pero nunca le
 * pasaba `tracking:` a enviarAvisoWhatsappTrabajador(), ni marcaba 'whatsapp'
 * en canales - a diferencia de citacion/sancion/resolucion_impugnacion que
 * si lo hacen. El wamid nunca se guardaba, asi que el webhook de Meta
 * jamas podia correlacionar el acuse para este tipo de notificacion.
 */
class EstadoDescargosAcuseWhatsappTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    public function test_estado_descargos_guarda_whatsapp_message_id_cuando_el_trabajador_tiene_whatsapp_activo(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.estado123']]], 200)]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_habilitado'], ['valor' => '1', 'tipo' => 'boolean', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_phone_number_id'], ['valor' => '123456', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(['clave' => 'whatsapp_access_token'], ['valor' => 'token-de-prueba', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true]);
        Configuracion::updateOrCreate(
            ['clave' => 'whatsapp_plantilla_estado_descargos_nombre'],
            ['valor' => 'estado_descargos', 'tipo' => 'text', 'categoria' => 'whatsapp', 'editable' => true],
        );

        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '1007568730',
            'genero' => 'masculino',
            'nombres' => 'Luis',
            'apellidos' => 'Gomez',
            'cargo' => 'Operario',
            'email' => 'luis@test.com',
            'telefono' => '3009998877',
            'notificar_por_whatsapp' => true,
            'active' => true,
        ]);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-ESTADO-' . uniqid(),
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        app(DocumentGeneratorService::class)->enviarNotificacionEstadoDescargos($proceso, 'descargos_realizados');

        $tracking = EmailTracking::where('proceso_id', $proceso->id)->first();

        $this->assertNotNull($tracking);
        $this->assertContains('whatsapp', $tracking->canales);
        $this->assertSame('wamid.estado123', $tracking->whatsapp_message_id);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_estado_descargos_no_marca_whatsapp_en_canales_si_el_trabajador_no_lo_tiene_activo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '1007568731',
            'genero' => 'masculino',
            'nombres' => 'Carlos',
            'apellidos' => 'Diaz',
            'cargo' => 'Operario',
            'email' => 'carlos@test.com',
            'telefono' => '3009998878',
            'notificar_por_whatsapp' => false,
            'active' => true,
        ]);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-ESTADO-' . uniqid(),
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        app(DocumentGeneratorService::class)->enviarNotificacionEstadoDescargos($proceso, 'descargos_no_realizados');

        $tracking = EmailTracking::where('proceso_id', $proceso->id)->first();

        $this->assertNotNull($tracking);
        $this->assertSame(['email'], $tracking->canales);
        $this->assertNull($tracking->whatsapp_message_id);
    }
}
