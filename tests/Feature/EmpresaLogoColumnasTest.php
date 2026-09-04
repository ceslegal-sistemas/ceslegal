<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaLogoColumnasTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_empresa_nueva_no_tiene_logo_por_defecto(): void
    {
        $empresa = Empresa::factory()->create();

        $this->assertNull($empresa->logo_path);
        $this->assertNull($empresa->logo_color_acento);
    }

    public function test_logo_path_y_logo_color_acento_son_fillable(): void
    {
        $empresa = Empresa::factory()->create([
            'logo_path' => 'logos/1/logo.png',
            'logo_color_acento' => '#E46350',
        ]);

        $this->assertSame('logos/1/logo.png', $empresa->fresh()->logo_path);
        $this->assertSame('#E46350', $empresa->fresh()->logo_color_acento);
    }
}
