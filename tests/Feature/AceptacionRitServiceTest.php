<?php

namespace Tests\Feature;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Services\AceptacionRitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * AceptacionRitService (2026-09-28) - evidencia jurídica real de la
 * socialización del RIT: snapshot+hash inmutable del texto, quiz completo
 * persistido, foto de la aceptación puntual, y un acta PDF generada. Cada
 * llamada crea un registro histórico NUEVO - nunca sobreescribe uno
 * anterior (a diferencia del updateOrCreate() de antes).
 */
class AceptacionRitServiceTest extends TestCase
{
    use RefreshDatabase;

    private function crearTrabajador(Empresa $empresa): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '111222333',
            'genero' => 'masculino',
            'nombres' => 'Carlos',
            'apellidos' => 'Gómez',
            'cargo' => 'Operario',
            'active' => true,
        ]);
    }

    private function fotoBase64DePrueba(): string
    {
        // JPEG 1x1 real (no importa el contenido para el test, solo que sea
        // un data URI válido con contenido decodificable).
        return 'data:image/jpeg;base64,' . base64_encode(random_bytes(50));
    }

    public function test_registrar_guarda_el_snapshot_y_el_hash_del_texto_exacto(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'subido', 'texto_completo' => 'Texto del RIT vigente.',
        ]);
        $trabajador = $this->crearTrabajador($empresa);

        $aceptacion = app(AceptacionRitService::class)->registrar(
            $trabajador,
            $rit,
            [],
            $this->fotoBase64DePrueba(),
            '190.10.20.30',
            'Mozilla/5.0 test',
        );

        $this->assertSame('Texto del RIT vigente.', $aceptacion->texto_rit_snapshot);
        $this->assertSame(hash('sha256', 'Texto del RIT vigente.'), $aceptacion->texto_rit_hash);
        $this->assertNotNull($aceptacion->foto_aceptacion_path);
        $this->assertTrue(Storage::disk('local')->exists($aceptacion->foto_aceptacion_path));
    }

    public function test_registrar_persiste_el_resultado_completo_del_quiz(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'subido', 'texto_completo' => 'v1',
        ]);
        $trabajador = $this->crearTrabajador($empresa);

        $quiz = [
            [
                'pregunta' => '¿La jornada máxima es de 8 horas diarias?',
                'respuesta_correcta' => true,
                'intentos' => [
                    ['respuesta_dada' => false, 'correcta' => false, 'respondido_en' => now()->toISOString()],
                    ['respuesta_dada' => true, 'correcta' => true, 'respondido_en' => now()->toISOString()],
                ],
            ],
        ];

        $aceptacion = app(AceptacionRitService::class)->registrar($trabajador, $rit, $quiz, null, '127.0.0.1', 'test');

        $this->assertCount(1, $aceptacion->quiz_resultado);
        $this->assertCount(2, $aceptacion->quiz_resultado[0]['intentos']);
        $this->assertFalse($aceptacion->quiz_resultado[0]['intentos'][0]['correcta']);
        $this->assertTrue($aceptacion->quiz_resultado[0]['intentos'][1]['correcta']);
    }

    public function test_registrar_genera_el_acta_pdf(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'subido', 'texto_completo' => 'v1',
        ]);
        $trabajador = $this->crearTrabajador($empresa);

        $aceptacion = app(AceptacionRitService::class)->registrar($trabajador, $rit, [], null, '127.0.0.1', 'test');

        $this->assertNotNull($aceptacion->ruta_acta);
        $this->assertNotNull($aceptacion->fecha_generacion_acta);
        $this->assertTrue(Storage::disk('local')->exists($aceptacion->ruta_acta));
    }

    /**
     * Nunca sobreescribe una aceptación anterior - cada llamada es un
     * registro histórico nuevo, incluso para el mismo trabajador+RIT.
     */
    public function test_dos_aceptaciones_del_mismo_trabajador_quedan_como_2_registros(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'subido', 'texto_completo' => 'v1',
        ]);
        $trabajador = $this->crearTrabajador($empresa);
        $service = app(AceptacionRitService::class);

        $service->registrar($trabajador, $rit, [], null, '127.0.0.1', 'test');
        $rit->update(['texto_completo' => 'v2']);
        $service->registrar($trabajador, $rit->fresh(), [], null, '127.0.0.1', 'test');

        $this->assertSame(2, AceptacionReglamentoInterno::where('trabajador_id', $trabajador->id)->count());
    }
}
