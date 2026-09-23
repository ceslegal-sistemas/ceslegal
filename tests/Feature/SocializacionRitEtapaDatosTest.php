<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Livewire\SocializacionRit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocializacionRitEtapaDatosTest extends TestCase
{
    use RefreshDatabase;

    public function test_trabajador_nuevo_crea_el_registro_con_todos_los_datos(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '333444555')
            ->set('numeroDocumentoConfirmacion', '333444555')
            ->call('buscarTrabajador')
            ->set('nombres', 'Juan')
            ->set('apellidos', 'Torres')
            ->set('genero', 'masculino')
            ->set('cargo', 'Vendedor')
            ->set('email', 'juan.torres@example.com')
            ->set('emailConfirmacion', 'juan.torres@example.com')
            ->set('telefono', '3001234567')
            ->call('guardarDatos')
            ->assertSet('etapa', 'foto');

        $this->assertDatabaseHas('trabajadores', [
            'empresa_id' => $empresa->id,
            'numero_documento' => '333444555',
            'nombres' => 'Juan',
            'apellidos' => 'Torres',
        ]);
    }

    public function test_trabajador_existente_precarga_sus_datos_y_no_duplica(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '444555666',
            'genero' => 'femenino', 'nombres' => 'Carla', 'apellidos' => 'Ríos', 'cargo' => 'Contadora', 'active' => true,
        ]);

        $componente = Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '444555666')
            ->set('numeroDocumentoConfirmacion', '444555666')
            ->call('buscarTrabajador')
            ->assertSet('nombres', 'Carla')
            ->set('email', 'carla.rios@example.com')
            ->set('emailConfirmacion', 'carla.rios@example.com')
            ->set('telefono', '3007654321')
            ->call('guardarDatos')
            ->assertSet('etapa', 'foto');

        $this->assertSame(1, Trabajador::where('numero_documento', '444555666')->count());
    }

    /**
     * Pedido explícito del usuario (2026-09-22): correo y teléfono son
     * obligatorios (antes eran opcionales).
     */
    public function test_correo_y_telefono_son_obligatorios(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '777888999')
            ->set('numeroDocumentoConfirmacion', '777888999')
            ->call('buscarTrabajador')
            ->set('nombres', 'Sin')
            ->set('apellidos', 'Contacto')
            ->set('genero', 'masculino')
            ->set('cargo', 'Operario')
            ->call('guardarDatos')
            ->assertHasErrors(['email', 'telefono']);
    }

    /**
     * Pedido explícito del usuario (2026-09-22): en cargos deberían salir
     * los cargos que hay en el RIT, igual que en Solicitud de Contrato.
     */
    public function test_carga_los_cargos_del_organigrama_del_rit(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        // El organigrama se genera como una actualizacion SEPARADA de la
        // creacion del RIT (ver MiReglamentoInterno::generarOrganigramaAction())
        // - si se pusiera en el mismo create(), ReglamentoInternoObserver lo
        // borraria de inmediato (isDirty('texto_completo') dispara la
        // invalidacion de cache de organigrama/sanciones/conductas).
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $rit->update([
            'organigrama' => [
                ['nombre_cargo' => 'Jefe de Bodega', 'instancia_sancionatoria' => 'ninguna'],
                ['nombre_cargo' => 'Auxiliar de Bodega', 'instancia_sancionatoria' => 'ninguna'],
            ],
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '787878787')
            ->set('numeroDocumentoConfirmacion', '787878787')
            ->call('buscarTrabajador')
            ->assertSet('cargosDisponibles', ['Jefe de Bodega' => 'Jefe de Bodega', 'Auxiliar de Bodega' => 'Auxiliar de Bodega']);
    }

    public function test_usa_catalogo_generico_si_el_rit_no_tiene_organigrama(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '898989898')
            ->set('numeroDocumentoConfirmacion', '898989898')
            ->call('buscarTrabajador')
            ->assertSet('cargosDisponibles', fn ($cargos) => array_key_exists('Vendedor', $cargos));
    }

    /**
     * Pedido explícito del usuario (2026-09-22): confirmar el correo
     * escribiéndolo de nuevo (no basta con pegarlo) antes de guardar.
     */
    public function test_rechaza_si_la_confirmacion_de_correo_no_coincide(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '111222333')
            ->set('numeroDocumentoConfirmacion', '111222333')
            ->call('buscarTrabajador')
            ->set('nombres', 'Correo')
            ->set('apellidos', 'Distinto')
            ->set('genero', 'masculino')
            ->set('cargo', 'Vendedor')
            ->set('email', 'correcto@example.com')
            ->set('emailConfirmacion', 'incorrecto@example.com')
            ->set('telefono', '3001112222')
            ->call('guardarDatos')
            ->assertHasErrors(['emailConfirmacion'])
            ->assertSet('etapa', 'datos');
    }

    public function test_permite_cargo_personalizado_con_otro(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '909090909')
            ->set('numeroDocumentoConfirmacion', '909090909')
            ->call('buscarTrabajador')
            ->set('nombres', 'Cargo')
            ->set('apellidos', 'Personalizado')
            ->set('genero', 'masculino')
            ->set('cargo', '__otro__')
            ->set('cargoPersonalizado', 'Especialista en Drones')
            ->set('email', 'personalizado@example.com')
            ->set('emailConfirmacion', 'personalizado@example.com')
            ->set('telefono', '3009998888')
            ->call('guardarDatos')
            ->assertSet('etapa', 'foto');

        $this->assertDatabaseHas('trabajadores', [
            'numero_documento' => '909090909',
            'cargo' => 'Especialista en Drones',
        ]);
    }
}
