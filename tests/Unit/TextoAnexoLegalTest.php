<?php

namespace Tests\Unit;

use App\Support\TextoAnexoLegal;
use PHPUnit\Framework\TestCase;

class TextoAnexoLegalTest extends TestCase
{
    private function palabras(int $n, string $prefijo = 'w'): string
    {
        return implode(' ', array_map(fn ($i) => $prefijo . $i, range(1, $n)));
    }

    public function test_quita_el_solapamiento_entre_fragmentos(): void
    {
        $f1 = "Texto del primer fragmento.\n\n" . $this->palabras(100);
        $f2 = $this->palabras(80, 'z') . "\n\nARTÍCULO 1. Texto nuevo.";

        $r = TextoAnexoLegal::reconstruir([$f1, $f2]);

        $this->assertStringNotContainsString('z1 z2', $r);
        $this->assertStringContainsString('ARTÍCULO 1. Texto nuevo.', $r);
    }

    public function test_no_recorta_un_prefijo_largo_que_no_es_solapamiento(): void
    {
        $largo = $this->palabras(120) . "\n\nresto";

        $this->assertSame($largo, TextoAnexoLegal::quitarSolapamiento($largo));
    }

    public function test_quita_menus_y_aviso_de_la_pagina_web(): void
    {
        $r = TextoAnexoLegal::limpiarRuidoWeb(
            "Inicio\nArtículo\nDerechos de autor reservados - Prohibida su reproducción\nLEY 2466 DE 2025"
        );

        $this->assertSame('LEY 2466 DE 2025', $r);
    }

    public function test_une_la_frase_cortada_pero_no_los_titulos(): void
    {
        $r = TextoAnexoLegal::limpiarRuidoWeb(
            "Última actualización: 15 de julio de 2026 - (Diario Oficial No. 53.546 - 7 de\njulio de 2026)\n\nLEY 2466 DE 2025\n\n(junio 25)"
        );

        $this->assertStringContainsString('7 de julio de 2026)', $r);
        $this->assertStringContainsString("LEY 2466 DE 2025\n\n(junio 25)", $r);
    }

    public function test_parrafos_de_lineas_prepara_un_diff_para_leerse(): void
    {
        $r = TextoAnexoLegal::parrafosDeLineas([
            'Las disposiciones de la Ley.',
            'Última actualización: 15 de julio - (Diario Oficial No. 53.546 - 7 de',
            'julio de 2026)',
            'Derechos de autor reservados - Prohibida su reproducción',
            'Inicio',
            '',
            'LEY 2466 DE 2025',
        ]);

        $this->assertSame([
            'Las disposiciones de la Ley.',
            'Última actualización: 15 de julio - (Diario Oficial No. 53.546 - 7 de julio de 2026)',
            'LEY 2466 DE 2025',
        ], $r);
    }
}
