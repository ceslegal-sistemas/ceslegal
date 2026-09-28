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
            'texto_rit_hash' => hash('sha256', 'v1'),
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
            'texto_rit_hash' => hash('sha256', 'v1'),
            'aceptado_en' => now()->subDays(30),
        ]);
        // Nueva version activa, distinta a la que acepto (podria haber varias de por medio)
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'mejora_ia', 'texto_completo' => 'v2']);

        $this->assertFalse($trabajador->fresh()->aceptoRitVigente());
    }

    /**
     * Bug real de integridad probatoria corregido 2026-09-28: antes se
     * comparaba solo por reglamento_interno_id, así que una mutación EN EL
     * MISMO registro (Plan B actualiza texto_completo in place, sin crear
     * una fila nueva) dejaba a todos los que ya habían aceptado marcados
     * como "al día" para siempre, aunque el texto real ya fuera otro.
     */
    public function test_false_si_el_mismo_registro_de_rit_cambio_de_texto_en_el_sitio(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'subido', 'texto_completo' => 'v1']);
        $trabajador = $this->crearTrabajador($empresa);
        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', 'v1'),
            'aceptado_en' => now()->subDays(10),
        ]);

        // Plan B: mismo registro, texto mutado in place (mismo id, mismo
        // reglamento_interno_id que ya estaba aceptado).
        $rit->update(['texto_completo' => 'v2 (actualizado por Plan B)']);

        $this->assertFalse($trabajador->fresh()->aceptoRitVigente());
    }
}
