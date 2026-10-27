<?php

namespace Tests\Feature;

use App\Models\EmailTracking;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mismo pedido del usuario (2026-10-26) ya cubierto para citaciones en
 * ReenvioCitacionCanalOverrideTest, extendido a Emitir Sanción/Re-generar
 * Sanción/Confirmar Días de Suspensión: $canalesOverride permite elegir el
 * canal PUNTUALMENTE para ese envío, sin romper el comportamiento histórico
 * cuando no se pasa (null).
 */
class SancionCanalOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function trabajador(Empresa $empresa, array $overrides = []): Trabajador
    {
        return Trabajador::create(array_merge([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '1007568730',
            'genero' => 'masculino',
            'nombres' => 'Ana',
            'apellidos' => 'Gomez',
            'cargo' => 'Operaria',
            'email' => 'ana@test.com',
            'telefono' => '3009876543',
            'active' => true,
        ], $overrides));
    }

    private function documentoDummy(): string
    {
        $ruta = sys_get_temp_dir() . '/sancion-test-' . uniqid() . '.pdf';
        file_put_contents($ruta, '%PDF-1.4 contenido de prueba');

        return $ruta;
    }

    public function test_sancion_sin_override_envia_correo_y_whatsapp_como_antes(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->trabajador($empresa);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-SANC-0001',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        app(DocumentGeneratorService::class)->enviarSancionPorEmail(
            $proceso,
            $this->documentoDummy(),
            'llamado_atencion',
        );

        $tracking = EmailTracking::where('proceso_id', $proceso->id)->first();
        $this->assertNotNull($tracking);
        // Histórico: correo siempre + WhatsApp porque hay enlace de impugnación
        // (sin mirar notificar_por_whatsapp) - comportamiento sin cambios.
        $this->assertSame(['email', 'whatsapp'], $tracking->canales);
    }

    public function test_sancion_override_solo_whatsapp_omite_el_correo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->trabajador($empresa);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-SANC-0002',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        app(DocumentGeneratorService::class)->enviarSancionPorEmail(
            $proceso,
            $this->documentoDummy(),
            'suspension',
            ['email' => false, 'whatsapp' => true],
        );

        $tracking = EmailTracking::where('proceso_id', $proceso->id)->first();
        $this->assertNotNull($tracking);
        $this->assertSame(['whatsapp'], $tracking->canales);
        $this->assertSame('Enviado por WhatsApp', $tracking->getEstadoLectura());
    }

    public function test_constancia_no_sancion_override_solo_correo_no_envia_whatsapp(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->trabajador($empresa, ['notificar_por_whatsapp' => true]);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-SANC-0003',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        app(DocumentGeneratorService::class)->enviarConstanciaNoSancionPorEmail(
            $proceso,
            $this->documentoDummy(),
            ['email' => true, 'whatsapp' => false],
        );

        $tracking = EmailTracking::where('proceso_id', $proceso->id)->first();
        $this->assertNotNull($tracking);
        $this->assertSame(['email'], $tracking->canales);
    }
}
