<?php

namespace Tests\Feature;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrabajadorAceptoRitVigenteTest extends TestCase
{
    use RefreshDatabase;

    private function crearTrabajador(Empresa $empresa): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '999888777',
            'genero' => 'femenino',
            'nombres' => 'Laura',
            'apellidos' => 'Pérez',
            'cargo' => 'Analista',
            'active' => true,
        ]);
    }

    public function test_false_si_nunca_ha_aceptado_nada(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = $this->crearTrabajador($empresa);

        $this->assertFalse($trabajador->aceptoRitVigente());
    }

    public function test_true_si_acepto_la_version_activa_actual(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = $this->crearTrabajador($empresa);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'aceptado_en' => now(),
        ]);

        $this->assertTrue($trabajador->aceptoRitVigente());
    }

    public function test_false_si_acepto_una_version_vieja_y_el_rit_ya_cambio(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritViejo = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => false, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = $this->crearTrabajador($empresa);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $ritViejo->id,
            'aceptado_en' => now()->subDays(30),
        ]);
        // Nueva version activa, distinta a la que acepto (podria haber varias de por medio)
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'mejora_ia', 'texto_completo' => 'v2']);

        $this->assertFalse($trabajador->fresh()->aceptoRitVigente());
    }
}
