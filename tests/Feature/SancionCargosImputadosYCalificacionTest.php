<?php

namespace Tests\Feature;

use App\Models\ArticuloLegal;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario (2026-10-13, plantilla de referencia real
 * "CDA AUTOMAS"): el documento de emisión de sanción debe ser "muy pero muy
 * específico" sobre qué artículo del Reglamento Interno de Trabajo de la
 * empresa cometió el trabajador, y si la falta es leve o grave - igual que ya
 * exige CitacionDescargosHechosYNormasTest para la citación inicial. Estos
 * bloques (CARGOS IMPUTADOS / CALIFICACIÓN DE LAS CONDUCTAS) se construyen
 * en PHP, NUNCA por la IA, para no arriesgar un artículo inventado en un
 * documento que puede terminar un contrato de trabajo.
 */
class SancionCargosImputadosYCalificacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ProcesoDisciplinarioObserver registra en el timeline con
        // Auth::id() ?? 1 - sin sesión activa necesita que exista un
        // usuario con id=1 para la FK (mismo patrón que
        // CitacionDescargosHechosYNormasTest/ProcesoDisciplinarioDisclaimerAutorizadorFechaTest).
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function invocarConstruir(ProcesoDisciplinario $proceso, Empresa $empresa): array
    {
        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'construirCargosImputadosYCalificacion');
        $metodo->setAccessible(true);

        return $metodo->invoke($service, $proceso, $empresa);
    }

    private function invocarInyectar(string $html, ProcesoDisciplinario $proceso, Empresa $empresa): string
    {
        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'inyectarCargosImputadosYCalificacion');
        $metodo->setAccessible(true);

        return $metodo->invoke($service, $html, $proceso, $empresa);
    }

    private function crearTrabajador(Empresa $empresa): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '999888777',
            'genero' => 'masculino',
            'nombres' => 'Trabajador',
            'apellidos' => 'De Prueba',
            'cargo' => 'Operario',
            'email' => 'trabajador.prueba@test.com',
            'telefono' => '3000000001',
            'direccion' => 'Calle Ficticia 2',
            'active' => true,
        ]);
    }

    public function test_cita_el_articulo_exacto_del_rit_y_la_gravedad_grave(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);

        $conductaReal = 'Omisión en el reporte de novedades técnicas detectadas durante la inspección.';

        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'RIT de prueba',
            'texto_completo' => 'ARTÍCULO 69. Texto completo de prueba del reglamento interno.',
            'activo' => true,
        ]);
        // Igual que en CitacionDescargosHechosYNormasTest: saveQuietly() en
        // una segunda escritura para no disparar la invalidación del
        // observer (ver docblock de ese test).
        $rit->conductas_sancionables = [
            'leve' => [],
            'grave' => [
                ['conducta' => $conductaReal, 'medida' => 'Terminación del contrato con justa causa', 'tipo' => 'terminacion', 'dias_suspension' => null, 'base_legal' => 'Artículo 69 RIT'],
            ],
            'gravisima' => [],
        ];
        $rit->saveQuietly();

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-SANCION-0001',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba relacionados con una omisión en el reporte.',
            'sanciones_laborales_ids' => [$conductaReal],
        ]);

        $bloques = $this->invocarConstruir($proceso, $empresa);

        $this->assertStringContainsString('<strong>Artículo 69</strong> del Reglamento Interno de Trabajo de', $bloques['cargos']);
        $this->assertStringContainsString($conductaReal, $bloques['cargos']);
        $this->assertStringContainsString('GRAVE', $bloques['calificacion']);
        $this->assertStringContainsString('<strong>Artículo 69</strong> del Reglamento Interno de Trabajo de', $bloques['calificacion']);
    }

    public function test_cita_el_texto_verbatim_del_cst_cuando_la_conducta_cae_al_catalogo_generico(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);

        ArticuloLegal::create([
            'codigo' => 'Art. 60 CST',
            'titulo' => 'Prohibiciones a los trabajadores',
            'descripcion' => 'Prohibiciones del trabajador',
            'texto_completo' => 'Se prohíbe a los trabajadores, texto verbatim de prueba...',
            'activo' => true,
        ]);

        // Empresa sin RIT: la conducta viene del catálogo genérico del CST.
        $conductaGenerica = 'Faltar un día al trabajo sin justa causa ni aviso';

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-SANCION-0002',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'El trabajador no se presentó a su puesto de trabajo.',
            'sanciones_laborales_ids' => [$conductaGenerica],
        ]);

        $bloques = $this->invocarConstruir($proceso, $empresa);

        $this->assertStringContainsString('<strong>Artículo 60</strong> del Código Sustantivo del Trabajo', $bloques['cargos']);
        $this->assertStringContainsString('Se prohíbe a los trabajadores, texto verbatim de prueba...', $bloques['cargos']);
        $this->assertStringNotContainsString('Reglamento Interno de Trabajo de', $bloques['cargos']);
        $this->assertStringContainsString('GRAVE', $bloques['calificacion']);
    }

    public function test_sin_conducta_identificada_no_inventa_cita_alguna(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-SANCION-0003',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba sin conducta clasificada.',
        ]);

        $bloques = $this->invocarConstruir($proceso, $empresa);

        $this->assertStringContainsString('No se identificó una conducta específica', $bloques['cargos']);
        $this->assertStringContainsString('No se identificó una conducta específica', $bloques['calificacion']);
    }

    public function test_inyectar_reemplaza_ambos_marcadores_en_el_html(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);

        $conductaReal = 'Incumplimiento de las instrucciones del jefe inmediato.';

        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'RIT de prueba',
            'texto_completo' => 'ARTÍCULO 55. Texto completo de prueba del reglamento interno.',
            'activo' => true,
        ]);
        $rit->conductas_sancionables = [
            'leve' => [
                ['conducta' => $conductaReal, 'medida' => 'Llamado de atención escrito', 'tipo' => 'llamado_atencion', 'dias_suspension' => null, 'base_legal' => 'Artículo 55 RIT'],
            ],
            'grave' => [],
            'gravisima' => [],
        ];
        $rit->saveQuietly();

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-SANCION-0004',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
            'sanciones_laborales_ids' => [$conductaReal],
        ]);

        $html = '<div>ANTES<!--CARGOS_IMPUTADOS-->ENTRE<!--CALIFICACION_CONDUCTAS-->DESPUES</div>';
        $resultado = $this->invocarInyectar($html, $proceso, $empresa);

        $this->assertStringNotContainsString('<!--CARGOS_IMPUTADOS-->', $resultado);
        $this->assertStringNotContainsString('<!--CALIFICACION_CONDUCTAS-->', $resultado);
        $this->assertStringContainsString('ANTES', $resultado);
        $this->assertStringContainsString('ENTRE', $resultado);
        $this->assertStringContainsString('DESPUES', $resultado);
        $this->assertStringContainsString('<strong>Artículo 55</strong> del Reglamento Interno de Trabajo de', $resultado);
        $this->assertStringContainsString('LEVE', $resultado);
    }
}
