<?php

namespace Tests\Feature;

use App\Mail\RitPublicacionInformada;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RitPublicacionInformadaMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_el_logo_real_si_existe(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('logos/renbel.png', 'contenido-fake-del-logo');
        $empresa = Empresa::factory()->create(['active' => true, 'logo_path' => 'logos/renbel.png']);

        $html = (new RitPublicacionInformada('Con Logo', $empresa->razon_social, $empresa))->render();

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString(route('logo-empresa.mostrar', ['empresa' => $empresa->id]), $html);
    }

    public function test_sin_logo_usa_encabezado_generico(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'logo_path' => null]);

        $html = (new RitPublicacionInformada('Sin Logo', $empresa->razon_social, $empresa))->render();

        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * Diferenciador clave frente a RitAceptado (Fase 2): este correo NUNCA
     * debe insinuar que el trabajador ya "comprendió" o "aceptó" el
     * Reglamento - solo que fue informado de su publicación.
     */
    public function test_el_copy_no_menciona_comprendio_ni_acepto(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        $html = (new RitPublicacionInformada('Trabajador', $empresa->razon_social, $empresa))->render();

        $this->assertStringNotContainsString('comprendió', $html);
        $this->assertStringNotContainsString('aceptó', $html);
    }
}
