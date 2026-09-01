<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SolicitudContratoDescargaTest extends TestCase
{
    use RefreshDatabase;

    private function crearSolicitudConArchivo(): SolicitudContrato
    {
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
            'ruta_contrato' => "solicitudes-contrato/{$empresa->id}/contrato_prueba.pdf",
        ]);

        Storage::disk('local')->put($solicitud->ruta_contrato, '%PDF-1.4 contenido de prueba');

        return $solicitud;
    }

    /**
     * Bug real reportado por el usuario: regeneraba el PDF (confirmado por
     * fecha_generacion_contrato y el mtime del archivo en disco), pero al
     * abrir "Ver Contrato" seguía viendo la versión vieja - el navegador
     * cacheaba la respuesta porque la URL de descarga es siempre la misma
     * (/solicitud-contrato/{id}/descargar) y el controlador no enviaba
     * ningún header anti-caché.
     */
    public function test_la_descarga_no_permite_que_el_navegador_la_cachee(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($user);

        $solicitud = $this->crearSolicitudConArchivo();

        $this->get(route('solicitud-contrato.descargar', $solicitud))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');

        // Symfony normaliza y reordena las directivas de Cache-Control (y
        // agrega "public" por su cuenta) - lo que de verdad importa para
        // que el navegador no cachee es que "no-store" esté presente, sin
        // importar el orden exacto de las demás directivas.
        $this->assertStringContainsString(
            'no-store',
            $this->get(route('solicitud-contrato.descargar', $solicitud))->headers->get('Cache-Control')
        );
    }

    public function test_devuelve_404_si_la_solicitud_no_tiene_contrato_generado(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($user);

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
            ->assertNotFound();
    }
}
