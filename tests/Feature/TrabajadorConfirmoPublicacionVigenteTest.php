<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\PublicacionReglamentoInterno;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrabajadorConfirmoPublicacionVigenteTest extends TestCase
{
    use RefreshDatabase;

    private function crearTrabajador(Empresa $empresa): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '222000222',
            'genero' => 'masculino', 'nombres' => 'Carlos', 'apellidos' => 'Publico', 'cargo' => 'Op', 'active' => true,
        ]);
    }

    public function test_false_si_nunca_confirmo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = $this->crearTrabajador($empresa);

        $this->assertFalse($trabajador->confirmoPublicacionVigente());
    }

    public function test_true_si_confirmo_con_el_hash_vigente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = $this->crearTrabajador($empresa);

        PublicacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', $rit->texto_completo),
            'confirmado_en' => now(),
        ]);

        $this->assertTrue($trabajador->confirmoPublicacionVigente());
    }

    /**
     * Mismo criterio de staleness que aceptoRitVigente(): si el texto del
     * RIT mutó (Plan B quirúrgico, mismo id) desde que confirmó, el hash ya
     * no coincide - debe volver a contar como "no confirmado".
     */
    public function test_false_si_el_rit_cambio_de_contenido_desde_que_confirmo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = $this->crearTrabajador($empresa);

        PublicacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', 'texto viejo, ya no coincide'),
            'confirmado_en' => now(),
        ]);

        $this->assertFalse($trabajador->confirmoPublicacionVigente());
    }
}
