<?php

namespace Tests\Feature;

use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\Notificacion;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use App\Models\User;
use App\Services\RitActualizacionAutomaticaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario (2026-09-07): al aprobar o rechazar una
 * sugerencia de actualización del RIT, la tarjeta "Cambios sugeridos"
 * desaparecía correctamente de "Mi Reglamento Interno", pero la
 * notificación de la campana seguía diciendo "¿Desea actualizarlo?" sobre
 * algo ya resuelto - quedaba huérfana porque Notificacion (sistema propio)
 * y SugerenciaActualizacionRit.estado nunca estaban vinculados en tiempo
 * de ejecución.
 */
class RitSugerenciaMarcaNotificacionLeidaTest extends TestCase
{
    use RefreshDatabase;

    private function crearSugerenciaConNotificacion(): array
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $cliente = User::factory()->create(['role' => 'cliente', 'active' => true, 'empresa_id' => $empresa->id]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Artículo único.',
        ]);
        $documento = DocumentoLegal::create(['titulo' => 'Ley de prueba', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => true]);
        $sugerencia = SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 0,
            'tipo_cambio' => 'modificar',
            'texto_anterior' => 'Artículo único.',
            'texto_propuesto' => 'Artículo único modificado.',
            'justificacion_ia' => 'Prueba.',
            'estado' => 'pendiente',
        ]);
        $notificacion = Notificacion::create([
            'user_id' => $cliente->id,
            'tipo' => 'sugerencia_actualizacion_rit',
            'titulo' => 'Hay una actualización legal que aplica a su Reglamento Interno',
            'mensaje' => '¿Desea actualizarlo?',
            'relacionado_tipo' => SugerenciaActualizacionRit::class,
            'relacionado_id' => $sugerencia->id,
            'leida' => false,
            'prioridad' => 'urgente',
        ]);

        return [$sugerencia, $notificacion, $cliente];
    }

    public function test_al_aprobar_la_sugerencia_se_marca_la_notificacion_como_leida(): void
    {
        [$sugerencia, $notificacion, $cliente] = $this->crearSugerenciaConNotificacion();
        $resolutor = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        app(RitActualizacionAutomaticaService::class)->aplicarSugerencia($sugerencia, $resolutor);

        $this->assertTrue($notificacion->fresh()->leida);
        $this->assertNotNull($notificacion->fresh()->fecha_lectura);
    }

    public function test_al_rechazar_la_sugerencia_se_marca_la_notificacion_como_leida(): void
    {
        [$sugerencia, $notificacion, $cliente] = $this->crearSugerenciaConNotificacion();
        $resolutor = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        app(RitActualizacionAutomaticaService::class)->rechazarSugerencia($sugerencia, $resolutor);

        $this->assertTrue($notificacion->fresh()->leida);
    }

    public function test_una_sugerencia_sin_notificaciones_no_lanza_error(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Artículo único.',
        ]);
        $documento = DocumentoLegal::create(['titulo' => 'Ley sin notificacion', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => true]);
        $sugerencia = SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 0,
            'tipo_cambio' => 'modificar',
            'texto_anterior' => 'Artículo único.',
            'texto_propuesto' => 'Artículo único modificado.',
            'justificacion_ia' => 'Prueba.',
            'estado' => 'pendiente',
        ]);
        $resolutor = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        // No debe lanzar excepción aunque no haya ninguna notificación para esta sugerencia
        app(RitActualizacionAutomaticaService::class)->rechazarSugerencia($sugerencia, $resolutor);

        $this->assertSame('rechazada', $sugerencia->fresh()->estado);
    }
}
