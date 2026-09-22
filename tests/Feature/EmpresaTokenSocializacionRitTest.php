<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaTokenSocializacionRitTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_el_token_una_sola_vez(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'token_socializacion_rit' => null]);

        $token1 = $empresa->tokenSocializacionRit();
        $token2 = $empresa->fresh()->tokenSocializacionRit();

        $this->assertSame($token1, $token2);
        $this->assertSame(64, strlen($token1));
    }

    public function test_url_socializacion_rit_incluye_el_token(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'token_socializacion_rit' => null]);

        $url = $empresa->urlSocializacionRit();

        $this->assertStringContainsString($empresa->fresh()->token_socializacion_rit, $url);
        $this->assertStringContainsString('/rit/socializar/', $url);
    }
}
