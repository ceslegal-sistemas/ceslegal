<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ModificacionContractual;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Hallazgo real (2026-09-02): el link "Descargar Preaviso" usaba
 * Storage::disk('local')->url(...), que depende de la ruta global
 * `storage/{path}` de Laravel - SIN autenticación, sirviendo TODO el disco
 * privado (contratos, sanciones, RIT, selfies) a cualquiera que adivine la
 * ruta del archivo. Estas rutas replican el patrón ya seguro de
 * SolicitudContratoDescargaController::contrato() (auth + route-model-binding
 * respetando el scope multi-tenant).
 */
class DescargaSeguraPreavisoYOtrosiTest extends TestCase
{
    use RefreshDatabase;

    private function crearEmpresaYSolicitud(array $overrides = []): SolicitudContrato
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        return SolicitudContrato::create(array_merge([
            'empresa_id' => $empresa->id,
            'estado' => 'aprobado',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2026-01-01',
            'fecha_inicio_periodo_actual' => '2026-01-01',
        ], $overrides));
    }

    public function test_descarga_el_preaviso_cuando_existe(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($user);

        $solicitud = $this->crearEmpresaYSolicitud([
            'ruta_preaviso' => 'solicitudes-contrato/preavisos/prueba.pdf',
            'decision_no_renovacion_en' => now(),
        ]);
        Storage::disk('local')->put($solicitud->ruta_preaviso, '%PDF-1.4 contenido de prueba');

        $this->get(route('solicitud-contrato.descargar-preaviso', $solicitud))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_404_si_el_contrato_no_tiene_preaviso(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($user);

        $solicitud = $this->crearEmpresaYSolicitud();

        $this->get(route('solicitud-contrato.descargar-preaviso', $solicitud))
            ->assertNotFound();
    }

    public function test_descarga_el_otrosi_cuando_existe(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($user);

        $solicitud = $this->crearEmpresaYSolicitud();
        $modificacion = ModificacionContractual::create([
            'solicitud_contrato_id' => $solicitud->id,
            'tipo_modificacion' => 'salario',
            'valor_anterior' => '2000000',
            'valor_nuevo' => '2500000',
            'fecha_efectiva' => now(),
            'ruta_otrosi' => 'solicitudes-contrato/otrosies/prueba.pdf',
            'estado' => 'otrosi_generado',
        ]);
        Storage::disk('local')->put($modificacion->ruta_otrosi, '%PDF-1.4 contenido de prueba');

        $this->get(route('modificacion-contractual.descargar', $modificacion))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_404_si_el_otrosi_no_tiene_documento_generado(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($user);

        $solicitud = $this->crearEmpresaYSolicitud();
        $modificacion = ModificacionContractual::create([
            'solicitud_contrato_id' => $solicitud->id,
            'tipo_modificacion' => 'salario',
            'valor_anterior' => '2000000',
            'valor_nuevo' => '2500000',
            'fecha_efectiva' => now(),
            'estado' => 'borrador',
        ]);

        $this->get(route('modificacion-contractual.descargar', $modificacion))
            ->assertNotFound();
    }
}
