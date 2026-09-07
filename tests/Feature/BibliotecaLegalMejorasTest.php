<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\BibliotecaLegalResource\Pages\CreateBibliotecaLegal;
use App\Filament\Admin\Resources\BibliotecaLegalResource\Pages\EditBibliotecaLegal;
use App\Models\DocumentoLegal;
use App\Models\TemaNormativo;
use App\Services\BibliotecaLegalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Mejoras pedidas por el usuario tras el incidente de la política de acoso
 * sexual (2026-09-07): fecha de expedición, fuente/emisor libre, relación
 * "reemplaza a" entre documentos, Temas Normativos visibles (ya los asigna
 * la IA pero eran invisibles), y autocompletar con IA al subir el archivo.
 */
class BibliotecaLegalMejorasTest extends TestCase
{
    use RefreshDatabase;

    protected function usuario(): \App\Models\User
    {
        Permission::findOrCreate('view_any_biblioteca::legal', 'web');
        Permission::findOrCreate('create_biblioteca::legal', 'web');
        Permission::findOrCreate('update_biblioteca::legal', 'web');
        $user = \App\Models\User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_any_biblioteca::legal', 'create_biblioteca::legal', 'update_biblioteca::legal']);
        $this->actingAs($user);
        return $user;
    }

    // ─── Relación "reemplaza a" ──────────────────────────────────────────────

    public function test_reemplaza_a_y_su_inversa_funcionan(): void
    {
        $viejo = DocumentoLegal::create(['titulo' => 'Ley vieja', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => false]);
        $nuevo = DocumentoLegal::create(['titulo' => 'Ley nueva', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => true, 'reemplaza_a_id' => $viejo->id]);

        $this->assertTrue($nuevo->reemplazaA->is($viejo));
        $this->assertTrue($viejo->reemplazadoPor->is($nuevo));
    }

    public function test_borrar_el_documento_viejo_no_bloquea_ni_borra_el_que_lo_reemplaza(): void
    {
        $viejo = DocumentoLegal::create(['titulo' => 'Ley vieja', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => false]);
        $nuevo = DocumentoLegal::create(['titulo' => 'Ley nueva', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => true, 'reemplaza_a_id' => $viejo->id]);

        $viejo->delete();

        $this->assertNotNull(DocumentoLegal::find($nuevo->id), 'El documento que reemplaza no debía borrarse ni bloquearse.');
        $this->assertNull($nuevo->fresh()->reemplaza_a_id, 'El vínculo debía quedar en null (nullOnDelete), no bloquear el borrado.');
    }

    // ─── Formulario: campos nuevos ───────────────────────────────────────────

    public function test_el_formulario_guarda_fecha_expedicion_fuente_emisor_y_reemplaza_a(): void
    {
        // No interesa el procesamiento real del archivo en este test (eso ya
        // lo cubre BibliotecaLegalServiceTest si existe) - sin esto,
        // CreateBibliotecaLegal::afterCreate() despacha ProcesarBibliotecaLegal
        // de forma síncrona (cola por defecto en tests) e intenta extraer
        // texto real de un PDF falso, cayendo al fallback de Gemini Vision.
        Queue::fake();
        $this->usuario();
        $viejo = DocumentoLegal::create(['titulo' => 'Ley vieja', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => false]);

        Livewire::test(CreateBibliotecaLegal::class)
            ->set('data.titulo', 'Ley nueva')
            ->set('data.tipo', 'ley')
            ->set('data.fecha_expedicion', '2026-01-15')
            ->set('data.fuente_emisor', 'Congreso de la República')
            ->set('data.reemplaza_a_id', $viejo->id)
            ->set('data.archivo_path', UploadedFile::fake()->create('ley.pdf', 10))
            ->call('create')
            ->assertHasNoFormErrors();

        $creado = DocumentoLegal::where('titulo', 'Ley nueva')->firstOrFail();
        $this->assertSame('2026-01-15', $creado->fecha_expedicion->format('Y-m-d'));
        $this->assertSame('Congreso de la República', $creado->fuente_emisor);
        $this->assertSame($viejo->id, $creado->reemplaza_a_id);
    }

    public function test_muestra_los_temas_normativos_ya_clasificados_en_editar(): void
    {
        $this->usuario();
        $documento = DocumentoLegal::create(['titulo' => 'Ley con temas', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => true]);
        $tema = TemaNormativo::create(['nombre' => 'Acoso Laboral', 'descripcion' => 'Prueba.']);
        $documento->temasNormativos()->attach($tema->id);

        Livewire::test(EditBibliotecaLegal::class, ['record' => $documento->id])
            ->assertSee('Acoso Laboral');
    }

    public function test_muestra_aviso_de_reemplazado_por_en_el_documento_viejo(): void
    {
        $this->usuario();
        $viejo = DocumentoLegal::create(['titulo' => 'Ley vieja', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => false]);
        DocumentoLegal::create(['titulo' => 'Ley nueva reemplazante', 'tipo' => 'ley', 'estado' => 'procesado', 'activo' => true, 'reemplaza_a_id' => $viejo->id]);

        Livewire::test(EditBibliotecaLegal::class, ['record' => $viejo->id])
            ->assertSee('Ley nueva reemplazante');
    }

    // ─── Refactor de extracción de texto ─────────────────────────────────────

    public function test_extraer_texto_de_archivo_funciona_igual_para_txt(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'test') . '.txt';
        file_put_contents($ruta, 'Contenido de prueba.');

        $texto = app(BibliotecaLegalService::class)->extraerTextoDeArchivo($ruta, 'txt');

        $this->assertSame('Contenido de prueba.', trim($texto));
        unlink($ruta);
    }

    public function test_extraer_texto_de_archivo_lanza_excepcion_en_formato_no_soportado(): void
    {
        $this->expectException(\RuntimeException::class);
        app(BibliotecaLegalService::class)->extraerTextoDeArchivo('/tmp/no-existe.xyz', 'xyz');
    }

    // ─── sugerirMetadatos() ───────────────────────────────────────────────────

    public function test_sugerir_metadatos_parsea_la_respuesta_de_gemini(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'titulo' => 'Ley 2466 de 2025',
                        'tipo' => 'ley',
                        'referencia' => 'Ley 2466 de 2025',
                        'fecha_expedicion' => '2025-08-13',
                        'incorporar_completo' => false,
                    ])]]],
                ]],
            ], 200),
        ]);

        $sugerencia = app(BibliotecaLegalService::class)->sugerirMetadatos('LEY 2466 DE 2025. Congreso de la República...');

        $this->assertSame('Ley 2466 de 2025', $sugerencia['titulo']);
        $this->assertSame('ley', $sugerencia['tipo']);
        $this->assertSame('2025-08-13', $sugerencia['fecha_expedicion']);
        $this->assertArrayNotHasKey('incorporar_completo', $sugerencia);
    }

    public function test_sugerir_metadatos_detecta_incorporar_completo(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'titulo' => 'Política de Prevención de Acoso Sexual',
                        'incorporar_completo' => true,
                        'justificacion_incorporar_completo' => 'Declara ser parte integral del Reglamento.',
                    ])]]],
                ]],
            ], 200),
        ]);

        $sugerencia = app(BibliotecaLegalService::class)->sugerirMetadatos('Esta política hace parte integral del Reglamento Interno de Trabajo.');

        $this->assertTrue($sugerencia['incorporar_completo']);
        $this->assertSame('Declara ser parte integral del Reglamento.', $sugerencia['justificacion_incorporar_completo']);
    }

    public function test_sugerir_metadatos_no_lanza_excepcion_si_gemini_falla(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Error', 500),
        ]);

        $sugerencia = app(BibliotecaLegalService::class)->sugerirMetadatos('Cualquier texto.');

        $this->assertSame([], $sugerencia);
    }

    public function test_sugerir_metadatos_devuelve_vacio_con_texto_vacio(): void
    {
        $sugerencia = app(BibliotecaLegalService::class)->sugerirMetadatos('');
        $this->assertSame([], $sugerencia);
    }

    // ─── Autocompletar al subir el archivo (flujo end-to-end) ────────────────

    public function test_autocompletar_llena_los_campos_vacios_al_subir_el_archivo(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'titulo' => 'Ley 2466 de 2025',
                        'tipo' => 'ley',
                        'referencia' => 'Ley 2466 de 2025',
                        'fecha_expedicion' => '2025-08-13',
                        'incorporar_completo' => false,
                    ])]]],
                ]],
            ], 200),
        ]);
        $this->usuario();

        $archivo = UploadedFile::fake()->createWithContent(
            'ley.txt',
            str_repeat('LEY 2466 DE 2025. El Congreso de la República decreta lo siguiente. ', 10)
        );

        Livewire::test(CreateBibliotecaLegal::class)
            ->set('data.archivo_path', $archivo)
            ->assertSet('data.titulo', 'Ley 2466 de 2025')
            ->assertSet('data.tipo', 'ley')
            ->assertSet('data.referencia', 'Ley 2466 de 2025')
            ->assertSet('data.fecha_expedicion', '2025-08-13');
    }

    public function test_autocompletar_no_sobreescribe_un_campo_ya_escrito_a_mano(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'titulo' => 'Título sugerido por la IA',
                    ])]]],
                ]],
            ], 200),
        ]);
        $this->usuario();

        $archivo = UploadedFile::fake()->createWithContent(
            'ley.txt',
            str_repeat('Contenido de prueba con suficiente longitud para pasar el umbral minimo. ', 10)
        );

        Livewire::test(CreateBibliotecaLegal::class)
            ->set('data.titulo', 'Título que el abogado ya escribió')
            ->set('data.archivo_path', $archivo)
            ->assertSet('data.titulo', 'Título que el abogado ya escribió');
    }
}
