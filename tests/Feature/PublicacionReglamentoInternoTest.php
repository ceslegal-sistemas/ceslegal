<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\PublicacionReglamentoInterno;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicacionReglamentoInternoTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_puede_crear_y_relacionar_con_trabajador_y_rit(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '111000111',
            'genero' => 'masculino', 'nombres' => 'Ana', 'apellidos' => 'Publica', 'cargo' => 'Op', 'active' => true,
        ]);

        $publicacion = PublicacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', $rit->texto_completo),
            'confirmado_en' => now(),
            'ip' => '127.0.0.1',
            'user_agent' => 'PestTest',
        ]);

        $this->assertTrue($publicacion->trabajador->is($trabajador));
        $this->assertTrue($publicacion->reglamentoInterno->is($rit));
    }
}
