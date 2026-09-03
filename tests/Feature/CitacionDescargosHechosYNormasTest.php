<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito del usuario: la "Citación a Diligencia de Descargos" que se
 * envía al trabajador no tenía el mismo nivel de detalle/claridad que una
 * citación real de referencia usada en producción por el bufete, en dos
 * puntos concretos:
 *
 *  1. Los HECHOS se mostraban como una lista numerada (<ol><li>), que
 *     fragmentaba un relato de varios párrafos en "puntos" sueltos.
 *  2. Los ARTÍCULOS que sustentan la falta caían SIEMPRE al genérico
 *     "Reglamento Interno... + Art. 58 CST" aunque el proceso ya tuviera una
 *     conducta real del RIT clasificada (manualmente o por IA al crear el
 *     proceso) - caso real confirmado: PD-2026-0008, con
 *     sanciones_laborales_ids y clasificacion_incidente_ia ya poblados, la
 *     citación seguía sin citarlos.
 *
 * Estos tests cubren la lógica de armado del HTML (sin tocar el motor de
 * clasificación de IA en sí, que ya tiene sus propios tests).
 */
class CitacionDescargosHechosYNormasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function invocarGenerarHTML(ProcesoDisciplinario $proceso): string
    {
        $service = new DocumentGeneratorService();
        $metodo = new \ReflectionMethod(DocumentGeneratorService::class, 'generarHTMLCitacionDescargos');
        $metodo->setAccessible(true);

        return $metodo->invoke($service, $proceso);
    }

    private function crearTrabajador(Empresa $empresa): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '111222333',
            'genero' => 'femenino',
            'nombres' => 'Trabajadora',
            'apellidos' => 'De Prueba',
            'cargo' => 'Auxiliar Administrativa',
            'email' => 'trabajadora.prueba@test.com',
            'telefono' => '3000000000',
            'direccion' => 'Calle Ficticia 1',
            'active' => true,
        ]);
    }

    public function test_los_hechos_se_presentan_como_parrafos_narrativos_no_como_lista_numerada(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);

        $parrafo1 = 'El día quince (15) de agosto de dos mil veintiséis (2026), la trabajadora fue sorprendida usando las credenciales de una compañera sin autorización.';
        $parrafo2 = 'Como consecuencia de lo anterior, la compañera afectada presentó una queja formal por escrito ante la empresa.';

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0100',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => $parrafo1 . "\n\n" . $parrafo2,
        ]);

        $html = $this->invocarGenerarHTML($proceso);

        // No debe fragmentar el relato en una lista numerada.
        $this->assertStringNotContainsString('<ol><li>' . $parrafo1, $html);
        $this->assertStringNotContainsString('<ol>', $html);

        // Cada párrafo real debe aparecer completo, envuelto en su propio <p>.
        $this->assertStringContainsString('<p>' . $parrafo1 . '</p>', $html);
        $this->assertStringContainsString('<p>' . $parrafo2 . '</p>', $html);
    }

    public function test_cita_la_conducta_real_del_rit_seleccionada_manualmente_en_vez_del_generico(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);

        $conductaReal = 'No hacer uso adecuado y oportuno de la plataforma de registro de clientes dispuesta por la empresa.';

        // conductas_sancionables se guarda con saveQuietly() en una segunda
        // escritura a propósito: ReglamentoInternoObserver::saved() invalida
        // (pone en null) conductas_sancionables/sanciones_extraidas/organigrama
        // cada vez que texto_completo cambia en el MISMO guardado (ver
        // docblock del observer) - si se envían juntos en un solo create(),
        // el observer los borra inmediatamente después de insertarlos.
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'RIT de prueba',
            'texto_completo' => 'Texto completo de prueba del reglamento interno.',
            'activo' => true,
        ]);
        $rit->conductas_sancionables = [
            'leve' => [
                ['conducta' => $conductaReal, 'medida' => 'Llamado de atención escrito', 'tipo' => 'llamado_atencion', 'dias_suspension' => null],
            ],
            'grave' => [],
            'gravisima' => [],
        ];
        $rit->saveQuietly();

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0101',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba relacionados con el uso de la plataforma.',
            'sanciones_laborales_ids' => [$conductaReal],
        ]);

        $html = $this->invocarGenerarHTML($proceso);

        // La conducta real del RIT debe citarse literalmente, con su gravedad.
        $this->assertStringContainsString($conductaReal, $html);
        $this->assertStringContainsString('falta leve', $html);
        // El anclaje general al Art. 58 CST se conserva como respaldo adicional.
        $this->assertStringContainsString('Artículo 58 del Código Sustantivo del Trabajo', $html);
    }

    public function test_usa_la_clasificacion_de_ia_como_respaldo_cuando_no_hubo_seleccion_manual(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);

        $conductaReal = 'Divulgar información confidencial de la empresa sin autorización.';

        // Ver comentario del test anterior: conductas_sancionables se guarda
        // con saveQuietly() en una segunda escritura para no disparar la
        // invalidación de ReglamentoInternoObserver::saved().
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'RIT de prueba',
            'texto_completo' => 'Texto completo de prueba del reglamento interno.',
            'activo' => true,
        ]);
        $rit->conductas_sancionables = [
            'leve' => [],
            'grave' => [
                ['conducta' => $conductaReal, 'medida' => 'Suspensión hasta por 8 días', 'tipo' => 'suspension', 'dias_suspension' => 8],
            ],
            'gravisima' => [],
        ];
        $rit->saveQuietly();

        // sanciones_laborales_ids vacío a propósito: el proceso se editó sin
        // pasar por "Motivo de los descargos", igual que el caso real que
        // motivó motivosDescargosDesdeClasificacionIA().
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0102',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba sobre divulgación de información.',
            'clasificacion_incidente_ia' => json_encode([
                'conducta_rit_aplicable' => $conductaReal,
            ]),
        ]);

        $html = $this->invocarGenerarHTML($proceso);

        $this->assertStringContainsString($conductaReal, $html);
        $this->assertStringContainsString('falta grave', $html);
    }

    public function test_conserva_el_respaldo_generico_cuando_no_hay_ninguna_conducta_clasificada(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);

        // Sin RIT, sin selección manual y sin clasificación de IA: debe
        // mantenerse el comportamiento anterior (nunca inventar un artículo).
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0103',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba sin conducta clasificada.',
        ]);

        $html = $this->invocarGenerarHTML($proceso);

        $this->assertStringContainsString('en sus disposiciones sobre obligaciones, conducta y disciplina del trabajador', $html);
        $this->assertStringContainsString('Artículo 58 del Código Sustantivo del Trabajo', $html);
    }
}
