<?php

namespace Tests\Feature;

use App\Mail\CitacionDescargosNotificacionCC;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "Con copia a" en la Citación de Descargos (pedido explícito del usuario,
 * 2026-09-24): correo INFORMATIVO aparte para un jefe/RRHH, no un CC
 * técnico real - el trabajador recibe su citación normal (sin cambios),
 * y cada correo en correos_cc recibe un mensaje distinto con el PDF
 * adjunto. Fail-open: un fallo de envío nunca debe romper la citación
 * real ya enviada al trabajador.
 */
class DocumentGeneratorServiceNotificarCorreosCCTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El observer de ProcesoDisciplinario registra en 'timeline' con
        // Auth::id() ?? 1 - sin sesión activa necesita que exista un usuario
        // con id=1 para la FK (mismo patrón de AsistentePanelServiceTest).
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function crearProceso(?array $correosCc = null): ProcesoDisciplinario
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '123456789',
            'genero' => 'masculino', 'nombres' => 'Juan', 'apellidos' => 'Perez', 'cargo' => 'Operario', 'active' => true,
        ]);

        return ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-CC1',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'x',
            'estado' => 'descargos_pendientes',
            'correos_cc' => $correosCc,
        ]);
    }

    private function pdfDePrueba(): string
    {
        $ruta = sys_get_temp_dir() . '/citacion_test_' . uniqid() . '.pdf';
        file_put_contents($ruta, '%PDF-1.4 contenido de prueba');

        return $ruta;
    }

    public function test_envia_el_correo_informativo_a_cada_correo_cc(): void
    {
        Mail::fake();

        $proceso = $this->crearProceso(['jefe@example.com', 'rrhh@example.com']);

        app(DocumentGeneratorService::class)->notificarCorreosCC($proceso, $this->pdfDePrueba());

        Mail::assertSent(CitacionDescargosNotificacionCC::class, 2);
        Mail::assertSent(CitacionDescargosNotificacionCC::class, fn ($mail) => $mail->hasTo('jefe@example.com'));
        Mail::assertSent(CitacionDescargosNotificacionCC::class, fn ($mail) => $mail->hasTo('rrhh@example.com'));
    }

    public function test_no_envia_nada_si_correos_cc_esta_vacio(): void
    {
        Mail::fake();

        $proceso = $this->crearProceso(null);

        app(DocumentGeneratorService::class)->notificarCorreosCC($proceso, $this->pdfDePrueba());

        Mail::assertNothingSent();
    }

    public function test_ignora_correos_con_formato_invalido(): void
    {
        Mail::fake();

        $proceso = $this->crearProceso(['no-es-un-correo', 'valido@example.com']);

        app(DocumentGeneratorService::class)->notificarCorreosCC($proceso, $this->pdfDePrueba());

        Mail::assertSent(CitacionDescargosNotificacionCC::class, 1);
        Mail::assertSent(CitacionDescargosNotificacionCC::class, fn ($mail) => $mail->hasTo('valido@example.com'));
    }

    /**
     * Respaldo del lado del servidor (2026-09-25, "prepáralo para cualquier
     * error de capa 8"): si correos_cc llegó a guardarse con el mismo correo
     * repetido en mayúsculas distintas (por otra vía que no sea el
     * formulario, ej. API o dato viejo), nunca debe enviarse dos veces al
     * mismo destinatario.
     */
    public function test_no_envia_dos_veces_al_mismo_correo_con_distinta_mayuscula(): void
    {
        Mail::fake();

        $proceso = $this->crearProceso(['Jefe@Ejemplo.com', 'jefe@ejemplo.com', ' jefe@ejemplo.com ']);

        app(DocumentGeneratorService::class)->notificarCorreosCC($proceso, $this->pdfDePrueba());

        Mail::assertSent(CitacionDescargosNotificacionCC::class, 1);
        Mail::assertSent(CitacionDescargosNotificacionCC::class, fn ($mail) => $mail->hasTo('jefe@ejemplo.com'));
    }

    public function test_no_bloquea_si_un_envio_falla(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP caido'));

        $proceso = $this->crearProceso(['jefe@example.com']);

        // No debe lanzar excepcion - fail-open.
        app(DocumentGeneratorService::class)->notificarCorreosCC($proceso, $this->pdfDePrueba());

        $this->assertTrue(true);
    }
}
