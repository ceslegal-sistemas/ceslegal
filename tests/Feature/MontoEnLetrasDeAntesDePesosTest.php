<?php

namespace Tests\Feature;

use App\Support\MontoEnLetras;
use Tests\TestCase;

/**
 * Regla gramatical del español (bug real reportado por el usuario,
 * 2026-09-28): "de" va después de millón/millones/billón/billones cuando
 * esa palabra queda inmediatamente antes de "pesos" (múltiplo exacto) -
 * "CUATRO MILLONES DE PESOS", no "CUATRO MILLONES PESOS". NO aplica a "mil".
 */
class MontoEnLetrasDeAntesDePesosTest extends TestCase
{
    public function test_multiplo_exacto_de_millones_lleva_de(): void
    {
        $this->assertSame('CUATRO MILLONES DE PESOS ($4.000.000 COP)', MontoEnLetras::pesos(4000000));
        $this->assertSame('UN MILLÓN DE PESOS ($1.000.000 COP)', MontoEnLetras::pesos(1000000));
        $this->assertSame('DOS MIL MILLONES DE PESOS ($2.000.000.000 COP)', MontoEnLetras::pesos(2000000000));
    }

    public function test_millones_con_miles_o_unidades_adicionales_no_lleva_de(): void
    {
        $this->assertSame('UN MILLÓN TRESCIENTOS MIL PESOS ($1.300.000 COP)', MontoEnLetras::pesos(1300000));
    }

    public function test_miles_sin_millones_nunca_lleva_de(): void
    {
        $this->assertSame('NOVECIENTOS CINCUENTA MIL PESOS ($950.000 COP)', MontoEnLetras::pesos(950000));
    }
}
