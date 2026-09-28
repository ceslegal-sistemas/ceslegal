<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
use App\Models\TerminacionContrato;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 2 del "ecosistema conectado" (2026-09-27): la sanción "Terminación de
 * Contrato" de Emitir Sanción ya no es un callejón sin salida - termina
 * automáticamente el contrato laboral vigente del trabajador, reutilizando
 * TerminacionContratoService (ver TerminacionContratoServiceTest para el
 * cálculo en sí, que aquí NO se repite).
 */
class TerminarContratoPorSancionDisciplinariaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function crearProcesoConContrato(): array
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '111',
            'genero' => 'masculino',
            'nombres' => 'Luis',
            'apellidos' => 'Torres',
            'cargo' => 'Operario',
            'email' => 'luis@test.com',
            'telefono' => '3001111111',
            'direccion' => 'Calle 5',
            'active' => true,
        ]);

        $contrato = SolicitudContrato::create([
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'estado' => 'aprobado',
            'tipo_contrato' => 'Contrato a Término Indefinido',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Luis',
            'trabajador_apellidos' => 'Torres',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '111',
            'cargo_contrato' => 'Operario',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'salario_propuesto' => '3000000',
            'fecha_inicio_propuesta' => '2024-01-01',
            'fecha_inicio_periodo_actual' => '2024-01-01',
        ]);

        $proceso = ProcesoDisciplinario::create([
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Llegadas tarde reiteradas.',
        ]);

        return [$proceso, $contrato, $trabajador];
    }

    public function test_termina_el_contrato_laboral_vigente_con_justa_causa_y_lo_enlaza_al_proceso(): void
    {
        [$proceso, $contrato, $trabajador] = $this->crearProcesoConContrato();

        app(DocumentGeneratorService::class)->terminarContratoPorSancionDisciplinaria($proceso);

        $terminacion = TerminacionContrato::where('solicitud_contrato_id', $contrato->id)->first();
        $this->assertNotNull($terminacion);
        $this->assertSame('con_justa_causa', $terminacion->tipo);
        $this->assertSame($proceso->id, $terminacion->proceso_disciplinario_id);
        $this->assertNull($terminacion->monto_indemnizacion);

        $this->assertSame('terminado', $contrato->fresh()->estado);
        $this->assertFalse($trabajador->fresh()->active);
    }

    public function test_no_falla_si_el_trabajador_no_tiene_ningun_contrato_laboral_vigente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '222',
            'genero' => 'masculino',
            'nombres' => 'Ana',
            'apellidos' => 'Ruiz',
            'cargo' => 'Auxiliar',
            'active' => true,
        ]);
        $proceso = ProcesoDisciplinario::create([
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        app(DocumentGeneratorService::class)->terminarContratoPorSancionDisciplinaria($proceso);

        $this->assertSame(0, TerminacionContrato::count());
    }

    /**
     * generarYEnviarSancion() (1000+ líneas, sin ningún test directo
     * preexistente en este proyecto - solo mockeado por completo a nivel de
     * GenerarYEnviarSancionJobTest) depende de una diligencia de descargos
     * completa + Gemini + email para correr de punta a punta; reconstruir
     * todo eso solo para probar 3 líneas nuevas sería frágil y no aportaría
     * más confianza que ya dan las 2 pruebas directas de arriba sobre el
     * hook en sí. En su lugar, se verifica por código fuente que el hook
     * está realmente conectado en el punto correcto (mismo patrón usado
     * antes en este proyecto para verificar ->splitKeys([',']) sin
     * reconstruir el árbol de Livewire completo).
     */
    public function test_el_hook_esta_conectado_dentro_de_generar_y_enviar_sancion(): void
    {
        $codigo = file_get_contents(app_path('Services/DocumentGeneratorService.php'));

        $posicionHook = strpos($codigo, "if (\$tipoSancion === 'terminacion') {\n                    \$this->terminarContratoPorSancionDisciplinaria(\$proceso);");
        $posicionMetodo = strpos($codigo, 'function generarYEnviarSancion(ProcesoDisciplinario $proceso, string $tipoSancion): array');
        $posicionFinMetodo = strpos($codigo, 'function generarYEnviarConstanciaNoSancion');

        $this->assertNotFalse($posicionHook, 'El hook de terminación automática no está presente.');
        $this->assertGreaterThan($posicionMetodo, $posicionHook, 'El hook debe estar dentro de generarYEnviarSancion().');
        $this->assertLessThan($posicionFinMetodo, $posicionHook, 'El hook debe estar dentro de generarYEnviarSancion(), no de otro método.');
    }
}
