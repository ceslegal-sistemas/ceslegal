<?php

namespace Tests\Feature;

use App\Services\RITGeneratorService;
use App\Services\RITMejoradoService;
use App\Support\RitGoldStandard;
use Tests\TestCase;

class RitGoldStandardYMejoraSincronizadosTest extends TestCase
{
    public function test_rit_gold_standard_checklist_tiene_una_clave_por_cada_capitulo(): void
    {
        $numeros = collect(RITGeneratorService::getCapitulos())->pluck('numero');

        foreach ($numeros as $numero) {
            $this->assertNotEmpty(
                RitGoldStandard::paraCapitulo($numero),
                "RitGoldStandard::CHECKLIST no tiene entrada para el capitulo {$numero}."
            );
        }
    }

    public function test_capitulo_a_seccion_tiene_una_clave_por_cada_capitulo_en_el_mismo_orden(): void
    {
        $numeros = collect(RITGeneratorService::getCapitulos())->pluck('numero')->all();

        $reflexion = new \ReflectionClass(RITMejoradoService::class);
        $capituloASeccion = $reflexion->getConstant('CAPITULO_A_SECCION');

        $this->assertSame($numeros, array_keys($capituloASeccion));
    }

    public function test_temas_capitulo_tiene_una_clave_por_cada_capitulo_en_el_mismo_orden(): void
    {
        $numeros = collect(RITGeneratorService::getCapitulos())->pluck('numero')->all();

        $reflexion = new \ReflectionClass(RITMejoradoService::class);
        $temasCapitulo = $reflexion->getConstant('TEMAS_CAPITULO');

        $this->assertSame($numeros, array_keys($temasCapitulo));
    }

    public function test_acoso_ahora_mapea_a_dos_capitulos(): void
    {
        $seccionAcoso = RitGoldStandard::paraSeccion('acoso');
        $this->assertNotEmpty($seccionAcoso);

        // Debe combinar contenido de XIV (acoso laboral/convivencia) y
        // XXI (acoso sexual dedicado) - si solo quedara uno de los dos,
        // esta lista seria mas corta de lo esperado.
        $soloXIV = RitGoldStandard::paraCapitulo('XIV');
        $soloXXI = RitGoldStandard::paraCapitulo('XXI');
        $this->assertGreaterThan(count($soloXIV), count($seccionAcoso));
        $this->assertGreaterThan(count($soloXXI), count($seccionAcoso));
    }

    /**
     * Regresion directa del bug encontrado durante el diseno de este plan:
     * con 22 capitulos canonicos, un capitulo "extra" preservado de un RIT
     * subido debe numerarse XXIII, nunca colisionar con XXII (Disposiciones
     * Finales).
     */
    public function test_capitulo_extra_preservado_no_colisiona_con_disposiciones_finales(): void
    {
        $totalCanonicos = count(RITGeneratorService::getCapitulos());
        $this->assertSame(22, $totalCanonicos);

        $reflexion = new \ReflectionClass(RITMejoradoService::class);
        $metodo = $reflexion->getMethod('preservarCapitulosAdicionales');
        $metodo->setAccessible(true);

        $instancia = $reflexion->newInstanceWithoutConstructor();
        $resultado = $metodo->invoke($instancia, ['Capitulo extra de prueba' => "CAPÍTULO XVII\nTELETRABAJO\nARTÍCULO 1 Texto de prueba."], $totalCanonicos, 200);

        // OJO: "CAPÍTULO XXII" es un substring literal de "CAPÍTULO XXIII"
        // (XXIII = XXII + "I") - un assertStringNotContainsString('CAPÍTULO XXII', ...)
        // fallaria SIEMPRE aunque el resultado sea correcto. Se usa un
        // negative lookahead para exigir que XXII no vaya seguido de otra "I".
        $this->assertMatchesRegularExpression('/CAPÍTULO XXIII\b/', $resultado);
        $this->assertDoesNotMatchRegularExpression('/CAPÍTULO XXII(?!I)/', $resultado);
    }
}
