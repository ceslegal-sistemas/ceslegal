<?php

namespace Tests\Feature;

use App\Models\TemaNormativo;
use App\Services\RITGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobertura de la ampliacion de 16 a 22 capitulos (ver
 * docs/superpowers/specs/2026-10-28-mejora-generador-rit-design.md).
 * No genera texto con Gemini - solo valida la estructura de datos.
 */
class RitGeneratorEstructuraCapitulosTest extends TestCase
{
    use RefreshDatabase;

    public function test_getCapitulos_devuelve_22_capitulos_sin_duplicados(): void
    {
        $capitulos = RITGeneratorService::getCapitulos();

        $this->assertCount(22, $capitulos);

        $numeros = collect($capitulos)->pluck('numero');
        $this->assertCount(22, $numeros->unique(), 'Hay numeros de capitulo duplicados.');

        $esperados = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'XIII', 'XIV', 'XV', 'XVI', 'XVII', 'XVIII', 'XIX', 'XX', 'XXI', 'XXII'];
        $this->assertSame($esperados, $numeros->all());
    }

    public function test_disposiciones_finales_es_el_ultimo_capitulo_xxii(): void
    {
        $capitulos = RITGeneratorService::getCapitulos();
        $ultimo = collect($capitulos)->last();

        $this->assertSame('XXII', $ultimo['numero']);
        $this->assertStringContainsString('DISPOSICIONES FINALES', $ultimo['titulo']);
    }

    public function test_acoso_sexual_ya_no_esta_en_el_capitulo_xiv(): void
    {
        $capXIV = collect(RITGeneratorService::getCapitulos())->firstWhere('numero', 'XIV');

        $this->assertNotContains('Acoso sexual laboral', $capXIV['temas']);
        $this->assertStringNotContainsString('PREVENCIÓN DEL ACOSO SEXUAL', $capXIV['instrucciones']);
    }

    public function test_capitulo_xxi_es_acoso_sexual_dedicado(): void
    {
        $cap = collect(RITGeneratorService::getCapitulos())->firstWhere('numero', 'XXI');

        $this->assertSame('ACOSO SEXUAL', $cap['titulo']);
        $this->assertContains('Acoso sexual laboral', $cap['temas']);
    }

    public function test_capitulo_xx_es_incapacidades_medicas(): void
    {
        $cap = collect(RITGeneratorService::getCapitulos())->firstWhere('numero', 'XX');

        $this->assertStringContainsString('INCAPACIDADES MÉDICAS', $cap['titulo']);
    }

    /**
     * Mismo test que ya existia en RitGeneratorUsaTaxonomiaTest, repetido aqui
     * a proposito como regresion directa de este cambio: los 2 temas nuevos
     * deben existir en la tabla para que el boost de buscarFragmentos() no
     * falle en silencio.
     */
    public function test_todos_los_temas_de_los_22_capitulos_existen_en_la_taxonomia(): void
    {
        $nombresValidos = TemaNormativo::pluck('nombre')->all();

        $nombresUsados = collect(RITGeneratorService::getCapitulos())
            ->flatMap(fn(array $cap) => $cap['temas'] ?? [])
            ->unique();

        foreach ($nombresUsados as $nombre) {
            $this->assertContains($nombre, $nombresValidos, "El tema '{$nombre}' no existe en temas_normativos.");
        }
    }
}
