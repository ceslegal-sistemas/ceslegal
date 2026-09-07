<?php

namespace Tests\Feature;

use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\FragmentoDocumento;
use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use App\Models\User;
use App\Services\RitActualizacionAutomaticaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feedback real de un abogado externo (2026-09-07): subió una política de
 * prevención de acoso sexual que declara ser "parte integral del Reglamento
 * Interno de Trabajo" - el motor (RitActualizacionAutomaticaService) solo
 * sabía hacer un ajuste quirúrgico de un párrafo, así que terminó resumiendo
 * la política entera en un parágrafo, en vez de incorporarla completa. Estos
 * tests cubren la segunda receta agregada: DocumentoLegal::incorporar_completo.
 */
class RitAnexoCompletoTest extends TestCase
{
    use RefreshDatabase;

    private function crearRitYDocumento(bool $incorporarCompleto): array
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => "Artículo 9. Principio de no discriminación.\nArtículo 135. Régimen disciplinario general.",
        ]);
        $documento = DocumentoLegal::create([
            'titulo' => 'Política de Prevención de Acoso Sexual y Discriminación',
            'tipo' => 'otro',
            'incorporar_completo' => $incorporarCompleto,
            'estado' => 'procesado',
            'activo' => true,
        ]);
        FragmentoDocumento::create([
            'documento_legal_id' => $documento->id,
            'orden' => 1,
            'contenido' => 'ARTÍCULO 1. Objeto. La presente política tiene por objeto prevenir el acoso sexual.',
        ]);
        FragmentoDocumento::create([
            'documento_legal_id' => $documento->id,
            'orden' => 2,
            'contenido' => 'ARTÍCULO 2. Ámbito de aplicación. Aplica a todos los trabajadores de la empresa.',
        ]);

        return [$rit, $documento];
    }

    public function test_documento_marcado_como_incorporar_completo_usa_el_prompt_de_anexo(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'titulo_anexo' => 'Política de Prevención de Acoso Sexual y Discriminación',
                        'bloque_indice' => 1,
                        'texto_referencia' => 'Ver Anexo para el detalle de conductas y sanciones por acoso sexual.',
                        'justificacion' => 'La política declara ser parte integral del RIT.',
                        'parece_ser_puntual' => false,
                    ])]]],
                ]],
            ], 200),
        ]);

        [$rit, $documento] = $this->crearRitYDocumento(incorporarCompleto: true);

        app(RitActualizacionAutomaticaService::class)->evaluarCambio($rit, $documento);

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($prompt, 'incorporarse COMPLETO y LITERAL')
                && str_contains($prompt, 'PROHIBICIÓN ABSOLUTA: no reproduzcas ni resumas')
                && ! str_contains($prompt, 'REGLA CENTRAL: solo puedes señalar UN bloque');
        });
    }

    public function test_documento_sin_marcar_sigue_usando_el_prompt_quirurgico(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(['cambio_necesario' => false])]]],
                ]],
            ], 200),
        ]);

        [$rit, $documento] = $this->crearRitYDocumento(incorporarCompleto: false);

        app(RitActualizacionAutomaticaService::class)->evaluarCambio($rit, $documento);

        Http::assertSent(function ($request) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($prompt, 'REGLA CENTRAL: solo puedes señalar UN bloque')
                && ! str_contains($prompt, 'incorporarse COMPLETO y LITERAL');
        });
    }

    public function test_el_texto_del_anexo_se_copia_literal_de_los_fragmentos_no_de_la_ia(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'titulo_anexo' => 'Política de Prevención de Acoso Sexual y Discriminación',
                        'bloque_indice' => 1,
                        'texto_referencia' => 'Ver Anexo para el detalle de conductas y sanciones por acoso sexual.',
                        'justificacion' => 'La política declara ser parte integral del RIT.',
                        'parece_ser_puntual' => false,
                    ])]]],
                ]],
            ], 200),
        ]);

        [$rit, $documento] = $this->crearRitYDocumento(incorporarCompleto: true);

        $cambio = app(RitActualizacionAutomaticaService::class)->evaluarCambio($rit, $documento);

        $this->assertNotNull($cambio);
        $this->assertSame('anexar_completo', $cambio['tipo_cambio']);
        $this->assertStringContainsString('ARTÍCULO 1. Objeto.', $cambio['texto_anexo']);
        $this->assertStringContainsString('ARTÍCULO 2. Ámbito de aplicación.', $cambio['texto_anexo']);
        $this->assertSame('Ver Anexo para el detalle de conductas y sanciones por acoso sexual.', $cambio['texto_propuesto']);
        $this->assertNull($cambio['alerta_incoherencia']);
    }

    public function test_alerta_incoherencia_cuando_la_ia_detecta_que_deberia_ser_anexo(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'cambio_necesario' => true,
                        'tipo_cambio' => 'agregar',
                        'bloque_indice' => 1,
                        'texto_propuesto' => 'Parágrafo agregado.',
                        'justificacion' => 'Ajuste puntual.',
                        'requiere_anexo_completo' => true,
                        'motivo_incoherencia' => 'Esta política declara ser parte integral del Reglamento.',
                    ])]]],
                ]],
            ], 200),
        ]);

        [$rit, $documento] = $this->crearRitYDocumento(incorporarCompleto: false);

        $cambio = app(RitActualizacionAutomaticaService::class)->evaluarCambio($rit, $documento);

        $this->assertNotNull($cambio);
        $this->assertSame('Esta política declara ser parte integral del Reglamento.', $cambio['alerta_incoherencia']);
    }

    public function test_aplicar_sugerencia_anexar_completo_agrega_referencia_y_anexo_al_texto_completo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'subido',
            'texto_completo' => "Artículo 9. Principio de no discriminación.\nArtículo 135. Régimen disciplinario general.",
        ]);
        $documento = DocumentoLegal::create([
            'titulo' => 'Política de Prevención de Acoso Sexual y Discriminación',
            'tipo' => 'otro',
            'incorporar_completo' => true,
            'estado' => 'procesado',
            'activo' => true,
        ]);
        $usuario = User::factory()->create(['role' => 'super_admin', 'active' => true]);

        $sugerencia = SugerenciaActualizacionRit::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $rit->id,
            'documento_legal_id' => $documento->id,
            'bloque_indice' => 1,
            'tipo_cambio' => 'anexar_completo',
            'texto_anterior' => null,
            'texto_propuesto' => 'Ver Anexo I para el detalle de conductas y sanciones.',
            'titulo_anexo' => 'Anexo I: Política de Prevención de Acoso Sexual',
            'texto_anexo' => 'ARTÍCULO 1. Objeto. Prevenir el acoso sexual laboral.',
            'justificacion_ia' => 'La política declara ser parte integral del RIT.',
            'estado' => 'pendiente',
        ]);

        $aplicada = app(RitActualizacionAutomaticaService::class)->aplicarSugerencia($sugerencia, $usuario);

        $this->assertTrue($aplicada);
        $rit->refresh();
        $this->assertStringContainsString('Ver Anexo I para el detalle de conductas y sanciones.', $rit->texto_completo);
        $this->assertStringContainsString('ANEXO I: POLÍTICA DE PREVENCIÓN DE ACOSO SEXUAL', $rit->texto_completo);
        $this->assertStringContainsString('ARTÍCULO 1. Objeto. Prevenir el acoso sexual laboral.', $rit->texto_completo);
        $this->assertSame('aprobada', $sugerencia->fresh()->estado);
    }

    public function test_checkbox_incorporar_completo_existe_en_el_formulario_de_biblioteca_legal(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('view_any_biblioteca::legal', 'web');
        \Spatie\Permission\Models\Permission::findOrCreate('create_biblioteca::legal', 'web');
        $usuario = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $usuario->givePermissionTo(['view_any_biblioteca::legal', 'create_biblioteca::legal']);
        $this->actingAs($usuario);

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\BibliotecaLegalResource\Pages\CreateBibliotecaLegal::class)
            ->set('data.incorporar_completo', true)
            ->assertSet('data.incorporar_completo', true);
    }
}
