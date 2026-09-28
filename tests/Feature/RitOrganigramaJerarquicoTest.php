<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Services\RITGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Organigrama del RIT (2026-09-27, backlog-organigrama-rit-diseno-visual.md):
 * pedido explícito del usuario tras ver la primera propuesta (una tabla
 * plana) - "me gustaría que fuera en organigrama... así sea mejorar la
 * forma de registrar los cargos". El dato de origen (nombre_cargo,
 * instancia_sancionatoria, reporta_a) es plano con un campo de jerarquía
 * nuevo - la jerarquía se construye 100% en PHP (nunca la redacta la IA,
 * mismo criterio de determinismo que TerminacionContratoService) y se
 * inyecta en el Capítulo VIII con el marcado ORGANIGRAMA:/NIVEL:/FIN_ORGANIGRAMA,
 * que textoAHtml() ya sabe convertir a HTML con sangría por nivel.
 */
class RitOrganigramaJerarquicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_cargos_no_genera_ningun_markup(): void
    {
        $this->assertSame('', RITGeneratorService::construirMarkupOrganigrama([]));
    }

    public function test_un_solo_cargo_raiz_sin_jerarquia(): void
    {
        $markup = RITGeneratorService::construirMarkupOrganigrama([
            ['nombre_cargo' => 'Gerente General', 'instancia_sancionatoria' => 'primera_instancia', 'reporta_a' => null],
        ]);

        $this->assertStringContainsString('ORGANIGRAMA:', $markup);
        $this->assertStringContainsString('NIVEL: 0 | Gerente General | primera instancia (impone la sanción)', $markup);
        $this->assertStringContainsString('FIN_ORGANIGRAMA', $markup);
    }

    public function test_jerarquia_de_3_niveles_en_orden_padre_antes_que_hijo(): void
    {
        $markup = RITGeneratorService::construirMarkupOrganigrama([
            ['nombre_cargo' => 'Auxiliar Administrativo', 'instancia_sancionatoria' => 'ninguna', 'reporta_a' => 'Jefe de Recursos Humanos'],
            ['nombre_cargo' => 'Gerente General', 'instancia_sancionatoria' => 'segunda_instancia', 'reporta_a' => null],
            ['nombre_cargo' => 'Jefe de Recursos Humanos', 'instancia_sancionatoria' => 'primera_instancia', 'reporta_a' => 'Gerente General'],
        ]);

        $posGerente = strpos($markup, 'NIVEL: 0 | Gerente General');
        $posJefe = strpos($markup, 'NIVEL: 1 | Jefe de Recursos Humanos');
        $posAuxiliar = strpos($markup, 'NIVEL: 2 | Auxiliar Administrativo');

        $this->assertNotFalse($posGerente);
        $this->assertNotFalse($posJefe);
        $this->assertNotFalse($posAuxiliar);
        $this->assertTrue($posGerente < $posJefe && $posJefe < $posAuxiliar);
    }

    public function test_cargo_que_reporta_a_un_nombre_inexistente_queda_como_raiz(): void
    {
        $markup = RITGeneratorService::construirMarkupOrganigrama([
            ['nombre_cargo' => 'Vendedor', 'instancia_sancionatoria' => 'ninguna', 'reporta_a' => 'Cargo Que No Existe'],
        ]);

        $this->assertStringContainsString('NIVEL: 0 | Vendedor', $markup);
    }

    /**
     * Protección contra ciclos (A reporta a B, B reporta a A) - no debe
     * colgarse ni perder ningún cargo, se resuelve tratando el primero como
     * raíz.
     */
    public function test_ciclo_entre_2_cargos_no_cuelga_y_no_pierde_ningun_cargo(): void
    {
        $markup = RITGeneratorService::construirMarkupOrganigrama([
            ['nombre_cargo' => 'Cargo A', 'instancia_sancionatoria' => 'ninguna', 'reporta_a' => 'Cargo B'],
            ['nombre_cargo' => 'Cargo B', 'instancia_sancionatoria' => 'ninguna', 'reporta_a' => 'Cargo A'],
        ]);

        $this->assertStringContainsString('Cargo A', $markup);
        $this->assertStringContainsString('Cargo B', $markup);
        $this->assertSame(1, substr_count($markup, 'Cargo A'));
        $this->assertSame(1, substr_count($markup, 'Cargo B'));
    }

    public function test_textoAHtml_convierte_el_markup_en_divs_con_sangria_por_nivel(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $texto = "CAPÍTULO VIII\nRÉGIMEN DISCIPLINARIO\n\nORGANIGRAMA:\nNIVEL: 0 | Gerente General | primera instancia (impone la sanción)\nNIVEL: 1 | Auxiliar | sin facultad disciplinaria\nFIN_ORGANIGRAMA\n";

        $html = app(RITGeneratorService::class)->textoAHtml($texto, $empresa);

        $this->assertStringContainsString('class="rit-org"', $html);
        $this->assertStringContainsString('margin-left:0pt', $html);
        $this->assertStringContainsString('margin-left:16pt', $html);
        $this->assertStringContainsString('Gerente General', $html);
        $this->assertStringContainsString('Auxiliar', $html);
        $this->assertStringNotContainsString('ORGANIGRAMA:', $html);
        $this->assertStringNotContainsString('FIN_ORGANIGRAMA', $html);
    }
}
