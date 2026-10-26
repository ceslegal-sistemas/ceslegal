<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource\Pages\CreateProcesoDisciplinario;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Services\EvaluacionHechosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-10-26): trazabilidad probatoria para
 * una eventual audiencia - la citación a descargos debe registrar la IP de
 * quien la generó, no solo nombre/cargo de texto libre. Mismo patrón ya
 * usado en exoneracion_ip/disclaimer_datos_autorizador_ip: capturada
 * automáticamente con request()->ip(), nunca escrita a mano por el usuario.
 */
class CreateProcesoDisciplinarioCitanteIpTest extends TestCase
{
    use RefreshDatabase;

    private function mutate(CreateProcesoDisciplinario $page, array $data): array
    {
        $page->data = $data;

        $m = new ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $m->setAccessible(true);

        return $m->invoke($page, $data);
    }

    public function test_captura_la_ip_de_quien_genera_la_citacion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '1007568729',
            'genero' => 'masculino', 'nombres' => 'Juan Pablo', 'apellidos' => 'Rendon', 'cargo' => 'Desarrollador', 'active' => true,
        ]);

        $this->mock(EvaluacionHechosService::class, function ($mock) {
            $mock->shouldReceive('generarHechosDesdeFormulario')
                ->andReturn(['hechos' => 'Texto generado por la IA.', 'fecha_ocurrencia' => null, 'resumen' => 'resumen']);
        });

        $page = new CreateProcesoDisciplinario();

        $data = $this->mutate($page, [
            'foto_citante_base64' => 'data:image/png;base64,AAAA',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'citante_nombre' => 'Ana Prueba',
            'citante_cargo' => 'Jefe de RRHH',
            'descripcion_hecho' => 'El trabajador no vino a trabajar el dia de hoy',
            'fecha_hecho' => now()->toDateString(),
        ]);

        $this->assertNotEmpty($data['citante_ip']);
        $this->assertSame(request()->ip(), $data['citante_ip']);
    }
}
