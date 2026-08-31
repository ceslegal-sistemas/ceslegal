<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\CrearSolicitudContrato;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CrearSolicitudContratoTest extends TestCase
{
    use RefreshDatabase;

    protected function usuarioConPermiso(): User
    {
        Permission::findOrCreate('create_solicitud::contrato', 'web');

        $user = User::factory()->create(['role' => 'super_admin']);
        $user->givePermissionTo('create_solicitud::contrato');
        $this->actingAs($user);
        return $user;
    }

    public function test_usuario_sin_permiso_no_puede_acceder(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($user);

        $this->assertFalse(CrearSolicitudContrato::canAccess());
    }

    public function test_usuario_con_permiso_puede_acceder(): void
    {
        $this->usuarioConPermiso();

        $this->assertTrue(CrearSolicitudContrato::canAccess());
    }

    public function test_no_se_registra_en_el_menu_de_navegacion(): void
    {
        $this->assertFalse(CrearSolicitudContrato::shouldRegisterNavigation());
    }

    public function test_monta_en_el_paso_1(): void
    {
        $this->usuarioConPermiso();

        Livewire::test(CrearSolicitudContrato::class)
            ->assertSet('paso', 1);
    }

    public function test_buscar_empresas_filtra_por_razon_social(): void
    {
        $this->usuarioConPermiso();
        $empresa = Empresa::factory()->create(['razon_social' => 'GRUPO EMPRESARIAL ANDINO', 'active' => true]);
        Empresa::factory()->create(['razon_social' => 'OTRA COSA', 'active' => true]);

        Livewire::test(CrearSolicitudContrato::class)
            ->set('empresaBusqueda', 'ANDINO')
            ->call('buscarEmpresas')
            ->assertSet('empresaResultados.0.id', $empresa->id);
    }

    public function test_no_avanza_de_paso_1_sin_empresa_ni_tipo_contrato(): void
    {
        $this->usuarioConPermiso();

        Livewire::test(CrearSolicitudContrato::class)
            ->call('avanzarPaso')
            ->assertSet('paso', 1)
            ->assertHasErrors(['empresa_id', 'tipo_contrato']);
    }

    public function test_avanza_de_paso_1_a_2_con_datos_validos(): void
    {
        $this->usuarioConPermiso();
        $empresa = Empresa::factory()->create(['active' => true]);

        Livewire::test(CrearSolicitudContrato::class)
            ->set('empresa_id', $empresa->id)
            ->set('tipo_contrato', 'Contrato a Término Fijo')
            ->set('fecha_solicitud', now()->format('Y-m-d\TH:i'))
            ->call('avanzarPaso')
            ->assertSet('paso', 2);
    }

    public function test_seleccionar_trabajador_existente_autocompleta_campos(): void
    {
        $this->usuarioConPermiso();
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC', 'numero_documento' => '123',
            'nombres' => 'Juan', 'apellidos' => 'Pérez',
            'cargo' => 'Analista',
            'email' => 'juan@test.com', 'telefono' => '3001234567', 'direccion' => 'Calle 1',
        ]);

        Livewire::test(CrearSolicitudContrato::class)
            ->set('usarTrabajadorExistente', true)
            ->call('seleccionarTrabajador', $trabajador->id)
            ->assertSet('trabajador_nombres', 'Juan')
            ->assertSet('trabajador_apellidos', 'Pérez')
            ->assertSet('trabajador_documento_numero', '123');
    }

    public function test_no_avanza_de_paso_2_sin_datos_del_trabajador_nuevo(): void
    {
        $this->usuarioConPermiso();
        $empresa = Empresa::factory()->create(['active' => true]);

        Livewire::test(CrearSolicitudContrato::class)
            ->set('empresa_id', $empresa->id)
            ->set('tipo_contrato', 'Contrato a Término Fijo')
            ->set('fecha_solicitud', now()->format('Y-m-d\TH:i'))
            ->call('avanzarPaso') // a paso 2
            ->call('avanzarPaso') // intenta ir a 3 sin llenar trabajador
            ->assertSet('paso', 2)
            ->assertHasErrors(['trabajador_nombres', 'trabajador_apellidos']);
    }

    public function test_completar_con_ia_llena_los_3_campos(): void
    {
        $this->usuarioConPermiso();
        $empresa = Empresa::factory()->create(['active' => true]);

        $this->mock(\App\Services\SolicitudContratoIAService::class, function ($mock) {
            $mock->shouldReceive('completarDetallesCargo')->once()->andReturn([
                'responsabilidades' => '<p>Resp IA</p>',
                'objeto_comercial' => '<p>Objeto IA</p>',
                'manual_funciones' => '<p>Manual IA</p>',
            ]);
        });

        Livewire::test(CrearSolicitudContrato::class)
            ->set('empresa_id', $empresa->id)
            ->set('cargo_contrato', 'Analista')
            ->call('completarConIA')
            ->assertSet('responsabilidades', '<p>Resp IA</p>')
            ->assertSet('objeto_comercial', '<p>Objeto IA</p>')
            ->assertSet('manual_funciones', '<p>Manual IA</p>');
    }

    public function test_fecha_fin_ocasional_no_puede_superar_30_dias(): void
    {
        $this->usuarioConPermiso();
        $empresa = Empresa::factory()->create(['active' => true]);

        Livewire::test(CrearSolicitudContrato::class)
            ->set('paso', 3)
            ->set('empresa_id', $empresa->id)
            ->set('tipo_contrato', 'Contrato Ocasional o Transitorio')
            ->set('cargo_contrato', 'Analista')
            ->set('responsabilidades', '<p>x</p>')
            ->set('objeto_comercial', '<p>x</p>')
            ->set('manual_funciones', '<p>x</p>')
            ->set('fecha_inicio_propuesta', '2026-01-01')
            ->set('fecha_fin_contrato', '2026-03-01') // más de 30 días
            ->set('departamento', 'Antioquia')
            ->set('ciudad', 'Medellín')
            ->call('avanzarPaso')
            ->assertHasErrors(['fecha_fin_contrato']);
    }

    public function test_sube_manual_de_funciones(): void
    {
        $this->usuarioConPermiso();
        Storage::fake('public');

        Livewire::test(CrearSolicitudContrato::class)
            ->set('ruta_manual_funciones', UploadedFile::fake()->create('manual.pdf', 500, 'application/pdf'))
            ->assertHasNoErrors('ruta_manual_funciones');
    }

    public function test_guardar_crea_la_solicitud_con_los_mismos_campos_que_filament(): void
    {
        $this->usuarioConPermiso();
        Storage::fake('public');
        $empresa = Empresa::factory()->create(['active' => true]);

        $this->mock(\App\Services\SolicitudContratoIAService::class, function ($mock) {
            $mock->shouldReceive('redactarObjetoJuridico')->once()->andReturn('Objeto redactado');
            $mock->shouldReceive('generarContratoPDF')->once();
        });

        Livewire::test(CrearSolicitudContrato::class)
            ->set('paso', 4)
            ->set('empresa_id', $empresa->id)
            ->set('tipo_contrato', 'Contrato a Término Fijo')
            ->set('fecha_solicitud', now()->format('Y-m-d\TH:i'))
            ->set('trabajador_nombres', 'Juan')
            ->set('trabajador_apellidos', 'Pérez')
            ->set('trabajador_documento_tipo', 'CC')
            ->set('trabajador_documento_numero', '123')
            ->set('trabajador_email', 'juan@test.com')
            ->set('cargo_contrato', 'Analista')
            ->set('responsabilidades', '<p>x</p>')
            ->set('objeto_comercial', '<p>x</p>')
            ->set('manual_funciones', '<p>x</p>')
            ->set('departamento', 'Antioquia')
            ->set('ciudad', 'Medellín')
            ->call('guardar');

        $this->assertDatabaseHas('solicitudes_contrato', [
            'empresa_id' => $empresa->id,
            'tipo_contrato' => 'Contrato a Término Fijo',
            'trabajador_nombres' => 'Juan',
            'cargo_contrato' => 'Analista',
            'lugar_labores' => 'Medellín, Antioquia',
            'objeto_juridico_redactado' => 'Objeto redactado',
        ]);
    }

    public function test_guardar_deja_estado_borrador_si_falla_la_generacion_ia(): void
    {
        $this->usuarioConPermiso();
        Storage::fake('public');
        $empresa = Empresa::factory()->create(['active' => true]);

        $this->mock(\App\Services\SolicitudContratoIAService::class, function ($mock) {
            $mock->shouldReceive('redactarObjetoJuridico')->andThrow(new \Exception('falla IA'));
        });

        Livewire::test(CrearSolicitudContrato::class)
            ->set('paso', 4)
            ->set('empresa_id', $empresa->id)
            ->set('tipo_contrato', 'Contrato a Término Fijo')
            ->set('fecha_solicitud', now()->format('Y-m-d\TH:i'))
            ->set('trabajador_nombres', 'Juan')
            ->set('trabajador_apellidos', 'Pérez')
            ->set('trabajador_documento_tipo', 'CC')
            ->set('trabajador_documento_numero', '123')
            ->set('trabajador_email', 'juan@test.com')
            ->set('cargo_contrato', 'Analista')
            ->set('responsabilidades', '<p>x</p>')
            ->set('objeto_comercial', '<p>x</p>')
            ->set('manual_funciones', '<p>x</p>')
            ->set('departamento', 'Antioquia')
            ->set('ciudad', 'Medellín')
            ->call('guardar');

        $this->assertDatabaseHas('solicitudes_contrato', ['estado' => 'borrador', 'trabajador_nombres' => 'Juan']);
    }
}
