<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReglamentoInternoFaseSocializacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_publicacion_si_no_hay_fecha_declarada(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);

        $this->assertSame('publicacion', $rit->faseSocializacionActual());
    }

    public function test_publicacion_mientras_no_pasan_los_15_dias_habiles(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'dias_habiles' => [1, 2, 3, 4, 5]]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->toDateString(),
        ]);

        $this->assertSame('publicacion', $rit->faseSocializacionActual());
    }

    public function test_socializacion_una_vez_pasan_los_15_dias_habiles(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'dias_habiles' => [1, 2, 3, 4, 5]]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => now()->subDays(40)->toDateString(),
        ]);

        $this->assertSame('socializacion', $rit->faseSocializacionActual());
    }
}
