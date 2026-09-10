<?php

namespace Tests\Feature;

use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\SolicitudContrato;
use App\Models\SugerenciaActualizacionRit;
use App\Models\User;
use App\Services\AsistentePanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AsistentePanelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');

        // Los observers de ProcesoDisciplinario/SolicitudContrato registran
        // en 'timeline' con Auth::id() ?? 1 - sin sesión activa necesitan que
        // exista un usuario con id=1 para la FK (mismo patrón ya usado en
        // DashboardContratosPorVencerNoticeTest.php:26-29).
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function crearSolicitud(Empresa $empresa, array $overrides = []): SolicitudContrato
    {
        return SolicitudContrato::create(array_merge([
            'empresa_id' => $empresa->id,
            'tipo_contrato' => 'Contrato a Término Fijo',
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123456',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2026-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
        ], $overrides));
    }

    public function test_cuenta_contratos_por_vencer_solo_con_el_permiso(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->crearSolicitud($empresa, ['fecha_fin_contrato' => now()->addDays(30)->toDateString()]);

        $conPermiso = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $conPermiso->givePermissionTo('view_any_solicitud::contrato');

        $sinPermiso = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $servicio = app(AsistentePanelService::class);

        $contextoConPermiso = $servicio->resolverContexto($conPermiso, $empresa);
        $contextoSinPermiso = $servicio->resolverContexto($sinPermiso, $empresa);

        $this->assertSame(1, $contextoConPermiso['contratos_por_vencer']);
        $this->assertNull($contextoSinPermiso['contratos_por_vencer']);
    }

    public function test_no_cuenta_contratos_fuera_de_la_ventana_de_45_dias(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $this->crearSolicitud($empresa, ['fecha_fin_contrato' => now()->addDays(200)->toDateString()]);

        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $user->givePermissionTo('view_any_solicitud::contrato');

        $contexto = app(AsistentePanelService::class)->resolverContexto($user, $empresa);

        $this->assertSame(0, $contexto['contratos_por_vencer']);
    }

    public function test_devuelve_array_vacio_si_el_usuario_no_tiene_empresa(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'active' => true]);

        $contexto = app(AsistentePanelService::class)->resolverContexto($user, null);

        $this->assertSame([], $contexto);
    }

    public function test_resuelve_contexto_del_rit_con_sugerencias_pendientes(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Artículo único.',
            'nombre' => 'RIT de prueba',
        ]);
        $documentoActivo = DocumentoLegal::create(['titulo' => 'Ley activa', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => true]);
        $documentoInactivo = DocumentoLegal::create(['titulo' => 'Ley inactiva', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => false]);

        SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id, 'reglamento_interno_id' => $rit->id, 'documento_legal_id' => $documentoActivo->id,
            'bloque_indice' => 0, 'tipo_cambio' => 'modificar', 'texto_anterior' => 'x', 'texto_propuesto' => 'y',
            'justificacion_ia' => 'prueba', 'estado' => 'pendiente',
        ]);
        // No debe contar: documento origen desactivado.
        SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id, 'reglamento_interno_id' => $rit->id, 'documento_legal_id' => $documentoInactivo->id,
            'bloque_indice' => 0, 'tipo_cambio' => 'modificar', 'texto_anterior' => 'x', 'texto_propuesto' => 'y',
            'justificacion_ia' => 'prueba', 'estado' => 'pendiente',
        ]);

        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $contexto = app(AsistentePanelService::class)->resolverContexto($user, $empresa);

        $this->assertTrue($contexto['rit']['tiene_rit']);
        $this->assertSame('RIT de prueba', $contexto['rit']['nombre']);
        $this->assertSame(1, $contexto['rit']['sugerencias_pendientes']);
    }

    public function test_resuelve_contexto_sin_rit(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $contexto = app(AsistentePanelService::class)->resolverContexto($user, $empresa);

        $this->assertFalse($contexto['rit']['tiene_rit']);
    }

    public function test_cuenta_procesos_disciplinarios_abiertos_excluyendo_cerrados_y_archivados(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = \App\Models\Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '999',
            'nombres' => 'Ana', 'apellidos' => 'Gómez', 'cargo' => 'Analista', 'area' => 'Operaciones',
            'fecha_ingreso' => '2026-01-01', 'active' => true,
        ]);

        \App\Models\ProcesoDisciplinario::create(['codigo' => 'PD-TEST-A1', 'empresa_id' => $empresa->id, 'trabajador_id' => $trabajador->id, 'hechos' => 'x', 'estado' => 'descargos_pendientes']);
        \App\Models\ProcesoDisciplinario::create(['codigo' => 'PD-TEST-A2', 'empresa_id' => $empresa->id, 'trabajador_id' => $trabajador->id, 'hechos' => 'x', 'estado' => 'cerrado']);
        \App\Models\ProcesoDisciplinario::create(['codigo' => 'PD-TEST-A3', 'empresa_id' => $empresa->id, 'trabajador_id' => $trabajador->id, 'hechos' => 'x', 'estado' => 'archivado']);

        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $contexto = app(AsistentePanelService::class)->resolverContexto($user, $empresa);

        $this->assertSame(1, $contexto['procesos_disciplinarios_abiertos']);
    }

    public function test_responder_arma_el_payload_correcto_y_devuelve_el_reply(): void
    {
        config(['services.asistente_panel.webhook_url' => 'https://n8n.example.test/webhook/asistente_panel']);
        config(['services.asistente_panel.secret' => 'secreto-de-prueba']);

        Http::fake([
            'n8n.example.test/*' => Http::response(['reply' => 'Tu RIT está al día.', 'conversation_id' => 'abc-123'], 200),
        ]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $respuesta = app(AsistentePanelService::class)->responder($user, 'abc-123', '¿Cómo está mi RIT?');

        $this->assertSame('Tu RIT está al día.', $respuesta);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://n8n.example.test/webhook/asistente_panel'
                && $request->hasHeader('X-Internal-Secret', 'secreto-de-prueba')
                && $request['conversation_id'] === 'abc-123'
                && $request['message'] === '¿Cómo está mi RIT?'
                && array_key_exists('contexto', $request->data());
        });
    }

    public function test_responder_devuelve_mensaje_generico_si_n8n_responde_con_error(): void
    {
        config(['services.asistente_panel.webhook_url' => 'https://n8n.example.test/webhook/asistente_panel']);
        Http::fake(['n8n.example.test/*' => Http::response(null, 500)]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $respuesta = app(AsistentePanelService::class)->responder($user, 'abc-123', 'hola');

        $this->assertSame('No pude responder en este momento. Intenta de nuevo en unos minutos.', $respuesta);
    }

    public function test_responder_devuelve_mensaje_generico_si_no_hay_conexion(): void
    {
        config(['services.asistente_panel.webhook_url' => 'https://n8n.example.test/webhook/asistente_panel']);
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timeout de prueba');
        });

        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $respuesta = app(AsistentePanelService::class)->responder($user, 'abc-123', 'hola');

        $this->assertSame('No pude responder en este momento. Intenta de nuevo en unos minutos.', $respuesta);
    }
}
