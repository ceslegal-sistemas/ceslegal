<?php

namespace Tests\Feature;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Services\ReglamentoInternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las conductas con las que se sanciona salen del Reglamento que el TRABAJADOR aceptó
 * (la versión anterior si no aceptó la actualización), no del vigente de la empresa.
 */
class ConductasDelRitAceptadoPorElTrabajadorTest extends TestCase
{
    use RefreshDatabase;

    private function conductas(string $nombre): array
    {
        return ['leve' => [], 'grave' => [], 'gravisima' => [
            ['conducta' => $nombre, 'medida' => 'Terminación con justa causa', 'tipo' => 'terminacion', 'dias_suspension' => null],
        ]];
    }

    private function crearRit(Empresa $empresa, string $texto, string $conducta, bool $activo): ReglamentoInterno
    {
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'nombre' => 'RIT', 'texto_completo' => $texto, 'activo' => $activo,
        ]);
        // Se guarda en una segunda escritura: el observer invalida conductas al cambiar el texto.
        $rit->conductas_sancionables = $this->conductas($conducta);
        $rit->saveQuietly();

        return $rit;
    }

    public function test_usa_el_catalogo_de_la_version_que_el_trabajador_acepto(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '1007568729',
            'nombres' => 'Juan', 'apellidos' => 'Prueba', 'cargo' => 'Desarrollador',
            'area' => 'TI', 'fecha_ingreso' => '2026-01-01', 'active' => true,
        ]);

        $ritViejo = $this->crearRit($empresa, 'Texto de la versión 1', 'Conducta SOLO de la versión anterior', false);
        $this->crearRit($empresa, 'Texto de la versión 2', 'Conducta SOLO de la versión vigente', true);

        AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $ritViejo->id,
            'texto_rit_hash' => hash('sha256', 'Texto de la versión 1'),
            'aceptado_en' => now()->subDays(30),
        ]);

        $servicio = app(ReglamentoInternoService::class);

        $paraElTrabajador = $servicio->conductasSancionablesDeEmpresa($empresa->id, $trabajador->id);
        $this->assertSame('Conducta SOLO de la versión anterior', $paraElTrabajador['gravisima'][0]['conducta']);

        // Sin trabajador conocido se mantiene el comportamiento de siempre: el vigente.
        $vigente = $servicio->conductasSancionablesDeEmpresa($empresa->id);
        $this->assertSame('Conducta SOLO de la versión vigente', $vigente['gravisima'][0]['conducta']);
    }
}
