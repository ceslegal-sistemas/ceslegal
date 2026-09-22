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
            ->call('buscarTrabajador')
            ->set('nombres', 'Juan')
            ->set('apellidos', 'Torres')
            ->set('genero', 'masculino')
            ->set('cargo', 'Vendedor')
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
            ->call('buscarTrabajador')
            ->assertSet('nombres', 'Carla')
            ->call('guardarDatos')
            ->assertSet('etapa', 'foto');

        $this->assertSame(1, Trabajador::where('numero_documento', '444555666')->count());
    }
}
