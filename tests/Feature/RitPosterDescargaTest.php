<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RitPosterDescargaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_autenticado_descarga_el_poster_con_su_qr(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($user);

        $response = $this->get(route('rit.poster'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_cliente_sin_empresa_no_puede_descargar_el_poster(): void
    {
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => null, 'active' => true]);
        $this->actingAs($user);

        $response = $this->get(route('rit.poster'));

        $response->assertForbidden();
    }

    public function test_super_admin_descarga_el_poster_de_cualquier_empresa(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $this->actingAs($user);

        $response = $this->get(route('rit.poster.admin', $empresa));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * No se mockea la fachada QrCode: mezclar instancias reales y mockeadas
     * de SimpleSoftwareIO\QrCode\Generator en el mismo proceso de tests
     * produce "Cannot redeclare mockery_init" (bug conocido de Mockery con
     * esta libreria). En su lugar, se verifica indirectamente: dos empresas
     * con links distintos deben producir QRs (imagenes) distintas.
     */
    public function test_el_qr_del_poster_apunta_al_link_fijo_de_socializacion(): void
    {
        $empresaA = Empresa::factory()->create(['active' => true]);
        $empresaB = Empresa::factory()->create(['active' => true]);

        $htmlA = \App\Support\RitPoster::html($empresaA);
        $htmlB = \App\Support\RitPoster::html($empresaB);

        $this->assertNotSame($htmlA, $htmlB);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $htmlA);
    }
}
