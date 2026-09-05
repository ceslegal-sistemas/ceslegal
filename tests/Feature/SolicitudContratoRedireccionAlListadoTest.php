<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\CreateSolicitudContrato;
use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\ListSolicitudContratos;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hallazgo real del jefe (2026-09-04): tras crear una solicitud de contrato,
 * Filament redirigía por defecto a la página "Ver" del contrato, donde el
 * único botón de cabecera visible es "Editar" (Aprobar/Rechazar solo existen
 * como Table Actions en el listado "Historial de Contratos", invisibles
 * desde ahí) - el cliente quedaba sin ninguna pista de qué hacer. Ahora se
 * redirige directo al listado y se resalta la fila nueva.
 */
class SolicitudContratoRedireccionAlListadoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'active' => true]);
    }

    private function solicitud(): SolicitudContrato
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        return SolicitudContrato::create([
            'empresa_id' => $empresa->id,
            'codigo' => 'SC-TEST-' . uniqid(),
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123456789',
            'trabajador_telefono' => '3001234567',
            'trabajador_direccion' => 'Calle 1 # 2-3',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => 'Responsabilidades de prueba.',
            'objeto_comercial' => 'Objeto comercial de prueba.',
            'manual_funciones' => 'Manual de funciones de prueba.',
            'salario_propuesto' => 2000000,
            'periodo_pago' => 'mensual',
            'fecha_inicio_propuesta' => now()->toDateString(),
            'fecha_fin_contrato' => now()->addMonths(6)->toDateString(),
            'duracion_cantidad' => 6,
            'duracion_unidad' => 'mes',
            'lugar_labores' => 'Bogotá',
            'lugar_contratacion' => 'Bogotá',
            'jornada_laboral' => 'diurna',
            'estado' => 'borrador',
        ]);
    }

    public function test_getRedirectUrl_apunta_al_listado_y_deja_el_id_en_sesion_para_resaltar_la_fila(): void
    {
        $this->actingAs($this->usuario());
        $solicitud = $this->solicitud();

        $page = new CreateSolicitudContrato();
        $page->record = $solicitud;

        $reflexion = new \ReflectionMethod($page, 'getRedirectUrl');
        $reflexion->setAccessible(true);
        $url = $reflexion->invoke($page);

        $this->assertSame(SolicitudContratoResource::getUrl('index'), $url);
        $this->assertSame($solicitud->id, session('solicitud_contrato_recien_creada'));
    }

    public function test_recordClasses_resalta_solo_la_fila_marcada_como_recien_creada(): void
    {
        $this->actingAs($this->usuario());
        $solicitudNueva = $this->solicitud();
        $solicitudVieja = $this->solicitud();

        session()->flash('solicitud_contrato_recien_creada', $solicitudNueva->id);

        $table = SolicitudContratoResource::table(Table::make(new ListSolicitudContratos()));

        $clasesNueva = $table->getRecordClasses($solicitudNueva);
        $clasesVieja = $table->getRecordClasses($solicitudVieja);

        $this->assertNotEmpty($clasesNueva, 'La fila recién creada debe tener clases de resaltado.');
        $this->assertEmpty($clasesVieja, 'Una fila distinta a la recién creada no debe resaltarse.');
    }
}
