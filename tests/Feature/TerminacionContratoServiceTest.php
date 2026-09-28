<?php

namespace Tests\Feature;

use App\Models\ArticuloLegal;
use App\Models\Empresa;
use App\Models\Configuracion;
use App\Models\SolicitudContrato;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\TerminacionContratoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Terminación de Contrato (justa causa / sin justa causa + indemnización
 * Art. 64 CST) - 100% determinístico, sin IA (pedido explícito del usuario:
 * el monto que se le debe a un trabajador no debe depender de un LLM, y
 * además es más barato).
 */
class TerminacionContratoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // SolicitudContratoObserver crea un Timeline al crear el contrato,
        // usando auth()->id() ?? 1 como user_id (FK real) - mismo gotcha ya
        // conocido en LogroDescargosServiceTest.
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function configurarSmlmv(string $valor): void
    {
        Configuracion::updateOrCreate(
            ['clave' => \App\Services\TerminacionContratoService::CLAVE_SMLMV_VIGENTE],
            ['valor' => $valor, 'tipo' => 'number', 'categoria' => 'legal']
        );
    }

    private function crearTrabajador(Empresa $empresa): Trabajador
    {
        return Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(100000000, 999999999),
            'genero' => 'masculino',
            'nombres' => 'Ana',
            'apellidos' => 'Gómez',
            'cargo' => 'Auxiliar',
            'active' => true,
        ]);
    }

    private function crearSolicitud(Empresa $empresa, Trabajador $trabajador, array $overrides = []): SolicitudContrato
    {
        return SolicitudContrato::create(array_merge([
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'estado' => 'aprobado',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => $trabajador->nombres,
            'trabajador_apellidos' => $trabajador->apellidos,
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => $trabajador->numero_documento,
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'salario_propuesto' => 3000000,
            'fecha_inicio_propuesta' => '2024-01-01',
            'fecha_inicio_periodo_actual' => '2024-01-01',
            'fecha_fin_contrato' => '2027-01-01',
        ], $overrides));
    }

    public function test_con_justa_causa_nunca_calcula_indemnizacion(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);
        $solicitud = $this->crearSolicitud($empresa, $trabajador);

        $calculo = app(TerminacionContratoService::class)
            ->calcularIndemnizacion($solicitud, 'con_justa_causa', now());

        $this->assertNull($calculo);
    }

    public function test_termino_fijo_indemniza_los_dias_restantes_del_plazo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);
        $solicitud = $this->crearSolicitud($empresa, $trabajador, [
            'tipo_contrato' => 'Contrato a Término Fijo',
            'salario_propuesto' => 3000000,
            'fecha_fin_contrato' => '2026-02-01',
        ]);

        $calculo = app(TerminacionContratoService::class)
            ->calcularIndemnizacion($solicitud, 'sin_justa_causa', Carbon::parse('2026-01-02'));

        // 2026-01-02 -> 2026-02-01 = 30 días. Salario diario = 3.000.000/30 = 100.000.
        $this->assertSame(30, $calculo['dias']);
        $this->assertSame(3000000.0, $calculo['monto']);
    }

    public function test_termino_fijo_sin_dias_pendientes_si_ya_vencio_el_plazo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);
        $solicitud = $this->crearSolicitud($empresa, $trabajador, [
            'tipo_contrato' => 'Contrato de Obra o Labor',
            'salario_propuesto' => 3000000,
            'fecha_fin_contrato' => '2026-01-01',
        ]);

        $calculo = app(TerminacionContratoService::class)
            ->calcularIndemnizacion($solicitud, 'sin_justa_causa', Carbon::parse('2026-02-01'));

        $this->assertSame(0, $calculo['dias']);
        $this->assertSame(0.0, $calculo['monto']);
    }

    public function test_indefinido_lanza_excepcion_si_falta_configurar_el_smlmv(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);
        $solicitud = $this->crearSolicitud($empresa, $trabajador, [
            'tipo_contrato' => 'Contrato a Término Indefinido',
        ]);

        $this->expectException(\RuntimeException::class);

        app(TerminacionContratoService::class)
            ->calcularIndemnizacion($solicitud, 'sin_justa_causa', now());
    }

    public function test_indefinido_menor_a_10_smlmv_un_anio_o_menos_son_30_dias(): void
    {
        $this->configurarSmlmv('1300000');

        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);
        $solicitud = $this->crearSolicitud($empresa, $trabajador, [
            'tipo_contrato' => 'Contrato a Término Indefinido',
            'salario_propuesto' => 3000000, // < 10 SMLMV (13.000.000)
            'fecha_inicio_propuesta' => '2026-01-01',
        ]);

        $calculo = app(TerminacionContratoService::class)
            ->calcularIndemnizacion($solicitud, 'sin_justa_causa', Carbon::parse('2026-06-01'));

        $this->assertSame(30, $calculo['dias']);
    }

    public function test_indefinido_menor_a_10_smlmv_mas_de_un_anio_suma_20_dias_por_anio_adicional(): void
    {
        $this->configurarSmlmv('1300000');

        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);
        $solicitud = $this->crearSolicitud($empresa, $trabajador, [
            'tipo_contrato' => 'Contrato a Término Indefinido',
            'salario_propuesto' => 3000000,
            'fecha_inicio_propuesta' => '2023-01-01',
        ]);

        // 2023-01-01 -> 2026-01-01 = 3 años calendario exactos.
        $calculo = app(TerminacionContratoService::class)
            ->calcularIndemnizacion($solicitud, 'sin_justa_causa', Carbon::parse('2026-01-01'));

        // 30 base + 20 x 2 años adicionales = 70.
        $this->assertSame(70, $calculo['dias']);
    }

    public function test_indefinido_mayor_o_igual_a_10_smlmv_usa_20_base_y_15_por_anio_adicional(): void
    {
        $this->configurarSmlmv('1300000');

        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);
        $solicitud = $this->crearSolicitud($empresa, $trabajador, [
            'tipo_contrato' => 'Contrato a Término Indefinido',
            'salario_propuesto' => 15000000, // >= 10 SMLMV (13.000.000)
            'fecha_inicio_propuesta' => '2023-01-01',
        ]);

        // 3 años exactos: 20 base + 15 x 2 años adicionales = 50.
        $calculo = app(TerminacionContratoService::class)
            ->calcularIndemnizacion($solicitud, 'sin_justa_causa', Carbon::parse('2026-01-01'));

        $this->assertSame(50, $calculo['dias']);
    }

    public function test_terminar_actualiza_estado_del_contrato_y_desactiva_al_trabajador(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);
        $solicitud = $this->crearSolicitud($empresa, $trabajador);

        app(TerminacionContratoService::class)->terminar($solicitud, [
            'tipo' => 'con_justa_causa',
            'motivo' => 'Incumplimiento reiterado de sus funciones.',
            'fecha_terminacion' => '2026-06-01',
        ]);

        $this->assertSame('terminado', $solicitud->fresh()->estado);
        $this->assertFalse($trabajador->fresh()->active);
    }

    public function test_terminar_crea_el_registro_con_los_datos_del_calculo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);
        $solicitud = $this->crearSolicitud($empresa, $trabajador, [
            'salario_propuesto' => 3000000,
            'fecha_fin_contrato' => '2026-02-01',
        ]);

        $terminacion = app(TerminacionContratoService::class)->terminar($solicitud, [
            'tipo' => 'sin_justa_causa',
            'fecha_terminacion' => '2026-01-02',
        ]);

        $this->assertSame($solicitud->id, $terminacion->solicitud_contrato_id);
        $this->assertSame($empresa->id, $terminacion->empresa_id);
        $this->assertSame(30, $terminacion->dias_indemnizacion);
        $this->assertSame(3000000.0, (float) $terminacion->monto_indemnizacion);
        $this->assertNotEmpty($terminacion->detalle_calculo);
    }

    public function test_terminar_genera_el_documento_pdf_citando_el_articulo_verbatim(): void
    {
        ArticuloLegal::create([
            'codigo' => 'Art. 62 CST',
            'titulo' => 'Terminación del contrato por justa causa',
            'descripcion' => 'x',
            'texto_completo' => 'Son justas causas para dar por terminado unilateralmente el contrato de trabajo...',
            'activo' => true,
        ]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = $this->crearTrabajador($empresa);
        $solicitud = $this->crearSolicitud($empresa, $trabajador);

        $terminacion = app(TerminacionContratoService::class)->terminar($solicitud, [
            'tipo' => 'con_justa_causa',
            'motivo' => 'Incumplimiento reiterado de sus funciones.',
            'fecha_terminacion' => '2026-06-01',
        ]);

        $this->assertNotNull($terminacion->ruta_documento);
        $this->assertNotNull($terminacion->fecha_generacion_documento);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($terminacion->ruta_documento));
    }
}
