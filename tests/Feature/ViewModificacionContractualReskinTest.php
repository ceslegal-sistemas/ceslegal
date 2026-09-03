<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ModificacionContractualResource;
use App\Models\Empresa;
use App\Models\ModificacionContractual;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ViewModificacionContractualReskinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('view_any_modificacion::contractual', 'web');
        Permission::findOrCreate('view_modificacion::contractual', 'web');
    }

    private function crearSolicitud(): SolicitudContrato
    {
        // El setter de razonSocial() en Empresa quita cualquier tipo
        // societario escrito al final (ver Empresa::TIPO_SOCIETARIO_PATRON) -
        // 'razon_social' guarda siempre el nombre "pelado", mismo
        // comportamiento que se ve en view-solicitud-contrato.blade.php y en
        // todo el resto del proyecto (emails, PDFs, etc.).
        $empresa = Empresa::factory()->create(['active' => true, 'razon_social' => 'RENBEL']);

        return SolicitudContrato::create([
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
        ]);
    }

    public function test_muestra_las_tarjetas_con_los_datos_del_cambio(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_any_modificacion::contractual', 'view_modificacion::contractual']);
        $this->actingAs($user);

        $solicitud = $this->crearSolicitud();
        $modificacion = ModificacionContractual::create([
            'solicitud_contrato_id' => $solicitud->id,
            'tipo_modificacion' => 'salario',
            'valor_anterior' => '2000000',
            'valor_nuevo' => '2500000',
            'justificacion' => 'Ajuste anual de desempeño.',
            'fecha_efectiva' => '2026-10-01',
            'estado' => 'borrador',
        ]);

        $this->get(ModificacionContractualResource::getUrl('view', ['record' => $modificacion]))
            ->assertSuccessful()
            ->assertSee($solicitud->codigo)
            ->assertSee('Juan Pérez')
            ->assertSee('RENBEL')
            ->assertSee('Salario')
            ->assertSee('2000000')
            ->assertSee('2500000')
            ->assertSee('Ajuste anual de desempeño.')
            ->assertSee('Aún sin redactar');
    }

    public function test_muestra_el_boton_de_descarga_cuando_el_otrosi_ya_se_genero(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_any_modificacion::contractual', 'view_modificacion::contractual']);
        $this->actingAs($user);

        $solicitud = $this->crearSolicitud();
        $modificacion = ModificacionContractual::create([
            'solicitud_contrato_id' => $solicitud->id,
            'tipo_modificacion' => 'salario',
            'valor_anterior' => '2000000',
            'valor_nuevo' => '2500000',
            'fecha_efectiva' => '2026-10-01',
            'ruta_otrosi' => 'solicitudes-contrato/otrosies/prueba.pdf',
            'texto_otrosi_redactado' => '<p>Texto del otrosí</p>',
            'estado' => 'otrosi_generado',
        ]);
        Storage::disk('local')->put($modificacion->ruta_otrosi, '%PDF-1.4 contenido de prueba');

        $this->get(ModificacionContractualResource::getUrl('view', ['record' => $modificacion]))
            ->assertSuccessful()
            ->assertSee('Descargar Otrosí')
            ->assertSee('Texto del otrosí', false)
            ->assertDontSee('Aún sin redactar');
    }
}
