<?php

namespace Tests\Feature;

use App\Jobs\GenerarTextoRITJob;
use App\Models\AuditoriaRIT;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\User;
use App\Services\AceptacionMejoraRITService;
use App\Services\RITGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReglamentoInternoUnicoActivoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug real encontrado en auditoría: GenerarTextoRITJob (única vía de
     * creación/activación de RIT sin este candado) marcaba activo=true al
     * RIT recién generado por el wizard SIN desactivar los demás de la
     * empresa - si ya había un RIT subido/mejorado activo, la empresa
     * quedaba con DOS filas activo=true a la vez.
     */
    public function test_generar_texto_rit_job_desactiva_los_demas_rit_de_la_empresa(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritViejo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'RIT viejo subido por el cliente.',
        ]);
        $ritNuevo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => false,
            'fuente' => 'construido_ia',
            'texto_completo' => '',
            'respuestas_cuestionario' => [],
        ]);
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        $mockService = $this->createMock(RITGeneratorService::class);
        $mockService->modeloUsado = 'gemini-2.5-flash';
        $mockService->esFallbackLite = false;
        $mockService->method('generarCapitulosRIT')->willReturn('Texto generado por la IA.');
        $mockService->method('guardarDocxPublico')->willReturn(null);

        (new GenerarTextoRITJob($ritNuevo, $user->id))->handle($mockService);

        $this->assertFalse($ritViejo->fresh()->activo);
        $this->assertTrue($ritNuevo->fresh()->activo);
        $this->assertSame(
            1,
            ReglamentoInterno::where('empresa_id', $empresa->id)->where('activo', true)->count()
        );
    }

    /**
     * Bug real encontrado en auditoría: AceptacionMejoraRITService::adoptar()
     * hacía 2 UPDATE sueltos sin transacción. No podemos simular fácilmente
     * "el proceso muere a mitad de camino" en un test, pero sí confirmamos
     * que el resultado final sigue siendo correcto (único activo) después
     * del fix con DB::transaction().
     */
    public function test_adoptar_deja_exactamente_un_rit_activo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritViejo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'RIT viejo.',
        ]);
        $ritMejorado = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => false,
            'fuente' => 'mejora_ia',
            'reglamento_origen_id' => $ritViejo->id,
            'texto_completo' => 'RIT mejorado.',
        ]);
        $auditoria = AuditoriaRIT::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $ritViejo->id,
            'estado' => 'completado',
            'estado_mejora' => 'completado',
            'reglamento_mejorado_id' => $ritMejorado->id,
            'decision_mejora' => 'pendiente',
        ]);

        app(AceptacionMejoraRITService::class)->adoptar($auditoria);

        $this->assertFalse($ritViejo->fresh()->activo);
        $this->assertTrue($ritMejorado->fresh()->activo);
        $this->assertSame('adoptado', $auditoria->fresh()->decision_mejora);
        $this->assertSame(
            1,
            ReglamentoInterno::where('empresa_id', $empresa->id)->where('activo', true)->count()
        );
    }

    /**
     * Bug real encontrado en auditoría: CreateReglamentoInterno emparejaba
     * el updateOrCreate() del wizard SOLO por empresa_id, sin filtrar por
     * fuente/estado - si la empresa ya tenía un RIT subido o mejorado,
     * podía pisar ESE registro en vez de crear uno propio del wizard. Este
     * test valida directamente la semántica de la consulta ya corregida
     * (mismos criterios de match que usa el código real).
     */
    public function test_wizard_construir_rit_no_pisa_un_rit_subido_existente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritSubido = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'RIT real subido por el cliente, no debe tocarse.',
        ]);

        // Mismos criterios de match que CreateReglamentoInterno.php tras el fix.
        $record = ReglamentoInterno::updateOrCreate(
            ['empresa_id' => $empresa->id, 'fuente' => 'construido_ia', 'estado_generacion' => 'generando'],
            [
                'nombre' => 'Reglamento Interno - prueba',
                'texto_completo' => '',
                'activo' => false,
                'fuente' => 'construido_ia',
                'estado_generacion' => 'generando',
            ]
        );

        $this->assertNotSame($ritSubido->id, $record->id);
        $this->assertSame('RIT real subido por el cliente, no debe tocarse.', $ritSubido->fresh()->texto_completo);
        $this->assertSame('subido', $ritSubido->fresh()->fuente);
    }
}
