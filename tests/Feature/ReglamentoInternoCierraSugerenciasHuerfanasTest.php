<?php

namespace Tests\Feature;

use App\Models\AuditoriaRIT;
use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use App\Services\AceptacionMejoraRITService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario con captura de pantalla de producción
 * (empresa RENBEL): el Dashboard mostraba el banner "hay una actualización
 * legal que aplica a su Reglamento" (cuenta SugerenciaActualizacionRit
 * pendientes por empresa_id, sin filtrar por RIT), pero "Mi Reglamento
 * Interno" no mostraba nada para aprobar/rechazar (filtra por el RIT
 * VIGENTE - ver MiReglamentoInterno::cargarSugerenciasPendientes()).
 *
 * Causa raíz confirmada leyendo el código: cuando se adopta un RIT
 * mejorado desde una auditoría (o se construye uno nuevo, o se sube uno
 * manual), el RIT original se desactiva - pero una SugerenciaActualizacionRit
 * pendiente sigue apuntando (reglamento_interno_id) a ese RIT ya inactivo.
 * Además, aprobar esa sugerencia NO tendría ningún efecto visible:
 * RitActualizacionAutomaticaService::aplicarSugerencia() modifica el texto
 * del RIT al que la sugerencia apunta (ya inactivo), no el RIT activo
 * actual - un "Aprobar" que aparenta funcionar pero no cambia nada que el
 * cliente vea. Se encontraron 4 sitios distintos en el código haciendo el
 * mismo `ReglamentoInterno::where('empresa_id',...)->update(['activo' =>
 * false])` sin este cierre - se centralizó en un único método del modelo.
 */
class ReglamentoInternoCierraSugerenciasHuerfanasTest extends TestCase
{
    use RefreshDatabase;

    public function test_desactivar_activos_de_cierra_sugerencias_pendientes_huerfanas(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritViejo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Artículo 1. Texto.',
        ]);
        $documento = DocumentoLegal::create([
            'titulo' => 'Ley de prueba', 'tipo' => 'ley',
            'estado' => 'procesado', 'activo' => true,
        ]);
        $sugerencia = SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $ritViejo->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 0,
            'tipo_cambio' => 'modificar',
            'texto_anterior' => 'Artículo 1. Texto.',
            'texto_propuesto' => 'Artículo 1. Texto nuevo.',
            'justificacion_ia' => 'La ley X modifica Y.',
            'estado' => 'pendiente',
        ]);

        ReglamentoInterno::desactivarActivosDe($empresa->id);

        $this->assertFalse($ritViejo->fresh()->activo);
        $sugerencia->refresh();
        $this->assertSame('rechazada', $sugerencia->estado);
        $this->assertNull($sugerencia->resuelto_por);
        $this->assertNotNull($sugerencia->resuelto_en);
    }

    public function test_adoptar_rit_mejorado_ya_no_deja_sugerencias_huerfanas_contando_en_el_dashboard(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $ritOriginal = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => 'Artículo 1. Texto original.',
        ]);
        $ritMejorado = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => false,
            'fuente' => 'mejora_ia',
            'reglamento_origen_id' => $ritOriginal->id,
            'texto_completo' => 'Artículo 1. Texto mejorado.',
            'version' => 2,
        ]);
        $auditoria = AuditoriaRIT::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $ritOriginal->id,
            'estado' => 'completado',
            'estado_mejora' => 'completado',
            'reglamento_mejorado_id' => $ritMejorado->id,
            'secciones' => [['titulo' => 'x', 'score' => 60]],
        ]);
        $documento = DocumentoLegal::create([
            'titulo' => 'Ley de prueba', 'tipo' => 'ley',
            'estado' => 'procesado', 'activo' => true,
        ]);
        // Sugerencia de Plan B sobre el RIT original, generada ANTES de adoptar la mejora.
        SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $ritOriginal->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 0,
            'tipo_cambio' => 'modificar',
            'texto_anterior' => 'Artículo 1. Texto original.',
            'texto_propuesto' => 'Artículo 1. Texto propuesto.',
            'justificacion_ia' => 'La ley X modifica Y.',
            'estado' => 'pendiente',
        ]);

        app(AceptacionMejoraRITService::class)->adoptar($auditoria);

        // Antes del fix: este conteo (el mismo que usa el banner del Dashboard)
        // seguía en 1 aunque "Mi Reglamento Interno" ya no mostrara nada.
        $this->assertSame(0, SugerenciaActualizacionRit::where('estado', 'pendiente')->count());
        $this->assertTrue($ritMejorado->fresh()->activo);
        $this->assertFalse($ritOriginal->fresh()->activo);
    }
}
