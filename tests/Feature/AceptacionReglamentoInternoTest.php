<?php

namespace Tests\Feature;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AceptacionReglamentoInternoTest extends TestCase
{
    use RefreshDatabase;

    private function crearTrabajadorYRit(): array
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '111222333',
            'genero' => 'masculino',
            'nombres' => 'Ana',
            'apellidos' => 'Gómez',
            'cargo' => 'Auxiliar',
            'active' => true,
        ]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'Texto de prueba.',
        ]);

        return [$empresa, $trabajador, $rit];
    }

    public function test_crea_una_aceptacion_con_ip_y_user_agent(): void
    {
        [, $trabajador, $rit] = $this->crearTrabajadorYRit();

        $aceptacion = AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'aceptado_en' => now(),
            'ip_aceptacion' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->assertDatabaseHas('aceptaciones_reglamento_interno', [
            'id' => $aceptacion->id,
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
        ]);
    }

    public function test_no_permite_dos_filas_para_el_mismo_trabajador_y_version(): void
    {
        [, $trabajador, $rit] = $this->crearTrabajadorYRit();

        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'aceptado_en' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'aceptado_en' => now(),
        ]);
    }

    public function test_update_or_create_no_duplica_actualiza_la_fecha(): void
    {
        [, $trabajador, $rit] = $this->crearTrabajadorYRit();

        AceptacionReglamentoInterno::updateOrCreate(
            ['trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id],
            ['aceptado_en' => now()->subDay()]
        );

        AceptacionReglamentoInterno::updateOrCreate(
            ['trabajador_id' => $trabajador->id, 'reglamento_interno_id' => $rit->id],
            ['aceptado_en' => now()]
        );

        $this->assertSame(
            1,
            AceptacionReglamentoInterno::where('trabajador_id', $trabajador->id)->count()
        );
    }
}
