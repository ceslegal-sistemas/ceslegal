<?php

namespace Tests\Feature\Bufete;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiasLaboralesRitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_rit_usa_dias_laborales_de_la_empresa(): void
    {
        $e = Empresa::factory()->create(['dias_laborales' => 'lunes_sabado']);

        $this->assertSame('lunes_sabado', $e->diasLaboralesEfectivos());
        $this->assertTrue($e->trabajaSabados());
    }

    public function test_el_rit_activo_manda_sobre_la_empresa(): void
    {
        $e = Empresa::factory()->create(['dias_laborales' => 'lunes_viernes']);
        ReglamentoInterno::create([
            'empresa_id' => $e->id,
            'nombre' => 'RIT',
            'texto_completo' => '',
            'activo' => true,
            'fuente' => 'subido',
            'dias_laborales' => 'lunes_sabado',
        ]);

        $this->assertSame('lunes_sabado', $e->fresh()->diasLaboralesEfectivos());
        $this->assertTrue($e->fresh()->trabajaSabados());
    }

    public function test_rit_sin_dias_cae_al_respaldo_de_la_empresa(): void
    {
        $e = Empresa::factory()->create(['dias_laborales' => 'lunes_viernes']);
        ReglamentoInterno::create([
            'empresa_id' => $e->id,
            'nombre' => 'RIT',
            'texto_completo' => '',
            'activo' => true,
            'fuente' => 'subido',
            'dias_laborales' => null,
        ]);

        $this->assertSame('lunes_viernes', $e->fresh()->diasLaboralesEfectivos());
    }
}
