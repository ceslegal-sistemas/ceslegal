<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\TrabajadorResource;
use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrabajadorResourceColumnaRitVigenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_consulta_eager_carga_relaciones_del_rit(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($user);

        $query = TrabajadorResource::getEloquentQuery();

        $this->assertArrayHasKey('empresa.reglamentoInterno', $query->getEagerLoads());
        $this->assertArrayHasKey('aceptacionesReglamentoInterno', $query->getEagerLoads());
    }

    public function test_muestra_aceptado_o_pendiente_segun_corresponda(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);

        $aceptado = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '565656565',
            'genero' => 'masculino', 'nombres' => 'Al', 'apellidos' => 'Dia', 'cargo' => 'X', 'active' => true,
        ]);
        AceptacionReglamentoInterno::create(['trabajador_id' => $aceptado->id, 'reglamento_interno_id' => $rit->id, 'aceptado_en' => now()]);

        $pendiente = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '676767676',
            'genero' => 'femenino', 'nombres' => 'Sin', 'apellidos' => 'Aceptar', 'cargo' => 'X', 'active' => true,
        ]);

        $this->assertTrue($aceptado->aceptoRitVigente());
        $this->assertFalse($pendiente->aceptoRitVigente());
    }
}
