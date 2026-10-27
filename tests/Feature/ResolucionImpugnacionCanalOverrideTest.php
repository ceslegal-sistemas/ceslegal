<?php

namespace Tests\Feature;

use App\Models\EmailTracking;
use App\Models\Empresa;
use App\Models\Impugnacion;
use App\Models\ProcesoDisciplinario;
use App\Models\Sancion;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mismo pedido del usuario (2026-10-26) ya cubierto para citaciones/sanciones:
 * $canalesOverride en enviarResolucionImpugnacionPorEmail() permite elegir el
 * canal puntualmente al resolver una impugnación ("Resolver Impugnación" /
 * "Confirmar Días - Resolución"), sin romper el comportamiento histórico.
 */
class ResolucionImpugnacionCanalOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function procesoConImpugnacion(array $trabajadorOverrides = []): ProcesoDisciplinario
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create(array_merge([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '1007568731',
            'genero' => 'femenino',
            'nombres' => 'Laura',
            'apellidos' => 'Diaz',
            'cargo' => 'Auxiliar',
            'email' => 'laura@test.com',
            'telefono' => '3007654321',
            'active' => true,
        ], $trabajadorOverrides));

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-IMP-' . uniqid(),
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        $sancion = Sancion::create([
            'proceso_id' => $proceso->id,
            'tipo_sancion' => 'llamado_atencion',
            'motivo_sancion' => 'Motivo de prueba.',
            'fundamento_legal' => 'Articulo de prueba.',
        ]);

        Impugnacion::create([
            'proceso_id' => $proceso->id,
            'sancion_id' => $sancion->id,
            'fecha_impugnacion' => now(),
            'motivos_impugnacion' => 'Motivos de prueba.',
            'decision_final' => 'confirma_sancion',
            'fundamento_decision' => 'Fundamento de prueba.',
        ]);

        return $proceso->fresh();
    }

    private function documentoDummy(): string
    {
        $ruta = sys_get_temp_dir() . '/resolucion-test-' . uniqid() . '.pdf';
        file_put_contents($ruta, '%PDF-1.4 contenido de prueba');

        return $ruta;
    }

    public function test_sin_override_envia_correo_y_whatsapp_como_antes(): void
    {
        $proceso = $this->procesoConImpugnacion();

        app(DocumentGeneratorService::class)->enviarResolucionImpugnacionPorEmail(
            $proceso,
            $this->documentoDummy(),
            'confirma_sancion',
        );

        $tracking = EmailTracking::where('proceso_id', $proceso->id)->first();
        $this->assertNotNull($tracking);
        $this->assertSame(['email', 'whatsapp'], $tracking->canales);
    }

    public function test_override_solo_whatsapp_omite_el_correo(): void
    {
        $proceso = $this->procesoConImpugnacion();

        app(DocumentGeneratorService::class)->enviarResolucionImpugnacionPorEmail(
            $proceso,
            $this->documentoDummy(),
            'confirma_sancion',
            ['email' => false, 'whatsapp' => true],
        );

        $tracking = EmailTracking::where('proceso_id', $proceso->id)->first();
        $this->assertNotNull($tracking);
        $this->assertSame(['whatsapp'], $tracking->canales);
        $this->assertSame('Enviado por WhatsApp', $tracking->getEstadoLectura());
    }
}
