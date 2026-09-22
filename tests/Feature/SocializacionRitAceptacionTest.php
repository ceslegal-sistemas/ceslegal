<?php

namespace Tests\Feature;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Livewire\SocializacionRit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocializacionRitAceptacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_aceptar_crea_el_registro_con_ip_y_pasa_a_completado(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '343434343',
            'genero' => 'masculino', 'nombres' => 'Acepta', 'apellidos' => 'Ya', 'cargo' => 'X', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->set('declaracionAceptada', true)
            ->call('aceptarReglamento')
            ->assertSet('etapa', 'completado');

        $this->assertDatabaseHas('aceptaciones_reglamento_interno', [
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
        ]);
    }

    public function test_no_permite_aceptar_sin_marcar_la_declaracion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '454545454',
            'genero' => 'femenino', 'nombres' => 'No', 'apellidos' => 'Acepta', 'cargo' => 'X', 'active' => true,
        ]);

        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('etapa', 'aceptacion')
            ->set('declaracionAceptada', false)
            ->call('aceptarReglamento')
            ->assertHasErrors('declaracionAceptada')
            ->assertSet('etapa', 'aceptacion');

        $this->assertDatabaseCount('aceptaciones_reglamento_interno', 0);
    }
}
