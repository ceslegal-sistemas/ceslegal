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
 * Bug real reportado por el usuario (2026-10-14, caso PD-2026-0004): escribió
 * el hecho en el Paso 2 ("El trabajador no vino a trabajar el dia de hoy") y
 * dio "Continuar" sin presionar el botón manual "Generar redacción con IA".
 * El proceso se creó sin ningún error visible, pero la columna `hechos`
 * quedó vacía y el PDF de citación salió con el texto de respaldo "No se
 * registraron hechos para este proceso."
 *
 * Causa raíz: Filament\Resources\Pages\CreateRecord::create() llama a
 * mutateFormDataBeforeCreate($data) y SOLO DESPUÉS dispara el hook
 * beforeCreate(). La generación automática de "hechos" (red de seguridad
 * para cuando el usuario nunca presiona el botón manual) vivía en
 * beforeCreate() - corría, pero mutaba $this->data/$this->datosExtraidos
 * demasiado tarde para afectar el array $data ya congelado por
 * mutateFormDataBeforeCreate(). El fix mueve ese bloque a
 * mutateFormDataBeforeCreate(), antes de que se calcule $data['hechos'].
 */
class CreateProcesoDisciplinarioAutoGeneracionHechosTest extends TestCase
{
    use RefreshDatabase;

    private function mutate(CreateProcesoDisciplinario $page, array $data): array
    {
        // En producción, Filament\CreateRecord::create() obtiene $data desde
        // $this->form->getState() - en la práctica, el mismo contenido que
        // $this->data en ese instante. Se replica aquí para que
        // generarHechos() (que lee $this->data) vea los mismos valores que
        // recibe mutateFormDataBeforeCreate() como argumento.
        $page->data = $data;

        $m = new ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $m->setAccessible(true);

        return $m->invoke($page, $data);
    }

    public function test_genera_hechos_automaticamente_si_el_usuario_nunca_presiono_el_boton_manual(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '1007568729',
            'genero' => 'masculino', 'nombres' => 'Juan Pablo', 'apellidos' => 'Rendon', 'cargo' => 'Desarrollador', 'active' => true,
        ]);

        $this->mock(EvaluacionHechosService::class, function ($mock) {
            $mock->shouldReceive('generarHechosDesdeFormulario')
                ->once()
                ->andReturn([
                    'hechos' => 'Texto legal generado automáticamente por la IA.',
                    'fecha_ocurrencia' => null,
                    'resumen' => 'resumen',
                ]);
        });

        $page = new CreateProcesoDisciplinario();

        $data = $this->mutate($page, [
            'foto_citante_base64' => 'data:image/png;base64,AAAA',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'descripcion_hecho' => 'El trabajador no vino a trabajar el dia de hoy',
            'fecha_hecho' => now()->toDateString(),
            // 'hechos_ia' deliberadamente ausente: el usuario nunca presionó
            // "Generar redacción con IA" - este es el caso real reportado.
        ]);

        $this->assertSame('Texto legal generado automáticamente por la IA.', $data['hechos']);
    }

    public function test_usa_la_redaccion_ya_generada_manualmente_sin_volver_a_llamar_la_ia(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '1111111111',
            'genero' => 'masculino', 'nombres' => 'Otro', 'apellidos' => 'Trabajador', 'cargo' => 'Operario', 'active' => true,
        ]);

        $this->mock(EvaluacionHechosService::class, function ($mock) {
            $mock->shouldNotReceive('generarHechosDesdeFormulario');
        });

        $page = new CreateProcesoDisciplinario();

        $data = $this->mutate($page, [
            'foto_citante_base64' => 'data:image/png;base64,AAAA',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'descripcion_hecho' => 'El trabajador no vino a trabajar el dia de hoy',
            'fecha_hecho' => now()->toDateString(),
            'hechos_ia' => 'Redacción ya generada por el botón manual.',
        ]);

        $this->assertSame('Redacción ya generada por el botón manual.', $data['hechos']);
    }
}
