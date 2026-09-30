<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ruta pública del video didáctico (2026-09-29, "Conecta el video a la vista
 * pública que ve el trabajador") - misma autorización que la página de
 * socialización: poseer el token, no la sesión (ver Gotcha crítico #3 en
 * SocializacionRit.php sobre withoutGlobalScope).
 */
class SocializacionRitVideoPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_token_invalido_da_404(): void
    {
        $response = $this->get('/rit/socializar/deadbeef/video');

        $response->assertNotFound();
    }

    public function test_sin_video_generado_da_404(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $token = $empresa->tokenSocializacionRit();

        $response = $this->get("/rit/socializar/{$token}/video");

        $response->assertNotFound();
    }

    public function test_sirve_el_video_del_rit_activo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        Storage::disk('local')->put('rit-videos/1/video.mp4', '%mp4-fake-bytes');
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'video_didactico_path' => 'rit-videos/1/video.mp4',
        ]);
        $token = $empresa->tokenSocializacionRit();

        $response = $this->get("/rit/socializar/{$token}/video");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'video/mp4');
    }

    /**
     * Rediseño 2026-09-30: el video es una serie de capítulos independientes
     * (ver RitVideoDidacticoService) - ?capitulo=N selecciona cuál servir.
     */
    public function test_sirve_el_capitulo_solicitado_por_query_param(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        Storage::disk('local')->put('rit-videos/1/cap1.mp4', '%mp4-capitulo-uno-contenido-mas-largo');
        Storage::disk('local')->put('rit-videos/1/cap2.mp4', '%mp4-cap2');
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'video_didactico_capitulos' => [
                ['titulo' => 'Jornada laboral', 'path' => 'rit-videos/1/cap1.mp4'],
                ['titulo' => 'Vacaciones', 'path' => 'rit-videos/1/cap2.mp4'],
            ],
        ]);
        $token = $empresa->tokenSocializacionRit();

        $respuestaCap0 = $this->get("/rit/socializar/{$token}/video?capitulo=0");
        $respuestaCap1 = $this->get("/rit/socializar/{$token}/video?capitulo=1");

        $respuestaCap0->assertOk();
        $respuestaCap1->assertOk();
        $this->assertSame(strlen('%mp4-capitulo-uno-contenido-mas-largo'), (int) $respuestaCap0->headers->get('Content-Length'));
        $this->assertSame(strlen('%mp4-cap2'), (int) $respuestaCap1->headers->get('Content-Length'));
    }

    public function test_capitulo_fuera_de_rango_da_404(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        Storage::disk('local')->put('rit-videos/1/cap1.mp4', '%mp4-fake');
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'video_didactico_capitulos' => [['titulo' => 'Jornada laboral', 'path' => 'rit-videos/1/cap1.mp4']],
        ]);
        $token = $empresa->tokenSocializacionRit();

        $response = $this->get("/rit/socializar/{$token}/video?capitulo=5");

        $response->assertNotFound();
    }

    /**
     * Bug real ya corregido una vez (mostrar()) - una sesión autenticada de
     * OTRO rol/empresa abierta en el mismo navegador no debe interferir con
     * la resolución del token.
     */
    public function test_resuelve_la_empresa_correcta_aunque_haya_otra_sesion_autenticada(): void
    {
        $empresaDelToken = Empresa::factory()->create(['active' => true]);
        Storage::disk('local')->put('rit-videos/1/video.mp4', '%mp4-fake-bytes');
        ReglamentoInterno::create([
            'empresa_id' => $empresaDelToken->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'video_didactico_path' => 'rit-videos/1/video.mp4',
        ]);
        $token = $empresaDelToken->tokenSocializacionRit();

        $otraEmpresa = Empresa::factory()->create(['active' => true]);
        $usuarioDeOtraEmpresa = \App\Models\User::factory()->create([
            'role' => 'cliente', 'empresa_id' => $otraEmpresa->id, 'active' => true,
        ]);
        $this->actingAs($usuarioDeOtraEmpresa);

        $response = $this->get("/rit/socializar/{$token}/video");

        $response->assertOk();
    }
}
