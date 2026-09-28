<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Services\RITGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real encontrado 2026-09-27 mientras se investigaba por qué el capítulo
 * de cargos del RIT se veía como "una lista sin diseño" (backlog:
 * backlog-organigrama-rit-diseno-visual.md): construirContextoEmpresa()
 * seguía leyendo el campo viejo 'puede_sancionar' (booleano), migrado hace
 * semanas a 'instancia_sancionatoria' (3 valores) en CreateReglamentoInterno -
 * la IA siempre recibía "no sanciona" para todos los cargos sin importar el
 * valor real guardado.
 */
class RitContextoCargosFacultadDisciplinariaTest extends TestCase
{
    use RefreshDatabase;

    private function cap(): array
    {
        return ['datos_empresa_keys' => ['cargos']];
    }

    public function test_usa_instancia_sancionatoria_en_vez_del_campo_viejo_puede_sancionar(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $respuestas = [
            'cargos' => [
                ['nombre_cargo' => 'Gerente General', 'instancia_sancionatoria' => 'primera_instancia'],
                ['nombre_cargo' => 'Jefe de Recursos Humanos', 'instancia_sancionatoria' => 'segunda_instancia'],
                ['nombre_cargo' => 'Auxiliar Administrativo', 'instancia_sancionatoria' => 'ninguna'],
            ],
        ];

        $contexto = app(RITGeneratorService::class)->construirContextoEmpresa($this->cap(), $respuestas, $empresa);

        $this->assertStringContainsString('Gerente General (primera instancia (impone la sanción))', $contexto);
        $this->assertStringContainsString('Jefe de Recursos Humanos (segunda instancia (resuelve apelaciones))', $contexto);
        $this->assertStringContainsString('Auxiliar Administrativo (sin facultad disciplinaria)', $contexto);
    }

    /**
     * Dato legado: un registro guardado ANTES de la migración a
     * instancia_sancionatoria no debe reventar - cae a "sin facultad
     * disciplinaria" en vez de un error o un "puede sancionar" incorrecto.
     */
    public function test_dato_legado_sin_instancia_sancionatoria_cae_a_sin_facultad(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $respuestas = ['cargos' => [
            ['nombre_cargo' => 'Cargo Legado', 'puede_sancionar' => true],
        ]];

        $contexto = app(RITGeneratorService::class)->construirContextoEmpresa($this->cap(), $respuestas, $empresa);

        $this->assertStringContainsString('Cargo Legado (sin facultad disciplinaria)', $contexto);
    }

    /**
     * El organigrama ya NO se le pide a la IA (ver
     * RitOrganigramaJerarquicoTest para el builder determinístico) - se
     * inyecta en generarCapitulosRIT() después de generar el texto del
     * Capítulo VIII. Se verifica por código fuente (ejecutar el pipeline
     * completo requeriría llamar a Gemini).
     */
    public function test_el_organigrama_se_inyecta_despues_del_capitulo_viii(): void
    {
        $fuente = file_get_contents(app_path('Services/RITGeneratorService.php'));

        $this->assertStringContainsString("\$cap['numero'] === 'VIII'", $fuente);
        $this->assertStringContainsString('construirMarkupOrganigrama', $fuente);
    }
}
