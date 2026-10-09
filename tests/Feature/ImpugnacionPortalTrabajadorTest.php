<?php

namespace Tests\Feature;

use App\Mail\ImpugnacionConstancia;
use App\Mail\ImpugnacionOtp;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Sancion;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\ImpugnacionPortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Portal público de impugnación: el trabajador la presenta él mismo con su enlace
 * personal + un código (OTP) enviado a su correo; la empresa ya no la registra.
 */
class ImpugnacionPortalTrabajadorTest extends TestCase
{
    use RefreshDatabase;

    private function procesoSancionado(): ProcesoDisciplinario
    {
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '1007568729',
            'nombres' => 'Juan Pablo', 'apellidos' => 'Rendon Beltran', 'cargo' => 'Desarrollador',
            'email' => 'trabajador@test.com', 'area' => 'Tecnología', 'fecha_ingreso' => '2026-01-01', 'active' => true,
        ]);

        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-IMP', 'empresa_id' => $empresa->id, 'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.', 'tipo_sancion' => 'llamado_atencion',
        ]);
        Sancion::create([
            'proceso_id' => $proceso->id, 'tipo_sancion' => 'llamado_atencion',
            'motivo_sancion' => 'Motivo.', 'fundamento_legal' => 'Art. 112 CST.',
        ]);
        $proceso->update(['estado' => 'sancion_emitida', 'fecha_notificacion' => now()]);

        return $proceso->fresh();
    }

    private function servicio(): ImpugnacionPortalService
    {
        return app(ImpugnacionPortalService::class);
    }

    public function test_el_enlace_personal_se_crea_una_sola_vez(): void
    {
        $proceso = $this->procesoSancionado();

        $token = $proceso->asegurarTokenImpugnacion();

        $this->assertSame(64, strlen($token));
        $this->assertSame($token, $proceso->fresh()->asegurarTokenImpugnacion());
        $this->assertStringContainsString("/impugnar/{$token}", $proceso->urlImpugnacion());
    }

    public function test_un_token_invalido_no_resuelve_ningun_proceso(): void
    {
        $this->procesoSancionado();

        $this->assertNull($this->servicio()->resolverPorToken('abc'));
        $this->assertNull($this->servicio()->resolverPorToken(str_repeat('a', 64)));
    }

    public function test_el_codigo_llega_al_correo_y_solo_el_correcto_verifica_la_identidad(): void
    {
        Mail::fake();
        $proceso = $this->procesoSancionado();
        $proceso->asegurarTokenImpugnacion();
        $proceso = $this->servicio()->resolverPorToken($proceso->impugnacion_token);

        $this->servicio()->enviarOtp($proceso);

        $codigo = null;
        Mail::assertSent(ImpugnacionOtp::class, function (ImpugnacionOtp $mail) use (&$codigo) {
            $codigo = $mail->codigo;

            return $mail->hasTo('trabajador@test.com');
        });

        $proceso = $proceso->fresh();
        $this->assertNotSame($codigo, $proceso->impugnacion_otp_hash, 'El código no se guarda en claro.');

        $this->assertSame('incorrecto', $this->servicio()->verificarOtp($proceso, $codigo === '000000' ? '111111' : '000000'));
        $this->assertFalse($this->servicio()->identidadVerificada($proceso->fresh()));

        $this->assertSame('ok', $this->servicio()->verificarOtp($proceso->fresh(), $codigo));
        $this->assertTrue($this->servicio()->identidadVerificada($proceso->fresh()));
    }

    public function test_tres_intentos_fallidos_bloquean_el_codigo(): void
    {
        Mail::fake();
        $proceso = $this->procesoSancionado();

        $this->servicio()->enviarOtp($proceso->fresh());

        $this->assertSame('incorrecto', $this->servicio()->verificarOtp($proceso->fresh(), '999990'));
        $this->assertSame('incorrecto', $this->servicio()->verificarOtp($proceso->fresh(), '999991'));
        $this->assertSame('bloqueado', $this->servicio()->verificarOtp($proceso->fresh(), '999992'));
        $this->assertSame('bloqueado', $this->servicio()->verificarOtp($proceso->fresh(), '999993'));
    }

    public function test_sin_verificar_la_identidad_no_se_puede_presentar(): void
    {
        $proceso = $this->procesoSancionado();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('identidad');

        $this->servicio()->presentar($proceso, ['motivos' => ['debido_proceso'], 'detalle' => ''], [], '127.0.0.1', 'PHPUnit');
    }

    public function test_presentar_registra_la_impugnacion_cambia_el_estado_y_envia_la_constancia(): void
    {
        Mail::fake();
        $proceso = $this->procesoSancionado();
        $proceso->forceFill(['impugnacion_otp_verificado_en' => now()])->saveQuietly();

        $impugnacion = $this->servicio()->presentar(
            $proceso->fresh(),
            ['motivos' => ['debido_proceso', 'desproporcion'], 'detalle' => 'No se me dio la oportunidad de defenderme.'],
            [],
            '203.0.113.7',
            'PHPUnit UA',
        );

        $this->assertSame('portal_web', $impugnacion->medio_recepcion);
        $this->assertSame('trabajador', $impugnacion->registrada_por);
        $this->assertSame('203.0.113.7', $impugnacion->ip_registro);
        $this->assertStringContainsString('No se respetó el debido proceso', $impugnacion->motivos_impugnacion);
        $this->assertStringContainsString('No se me dio la oportunidad', $impugnacion->motivos_impugnacion);
        $this->assertStringStartsWith('IMP-', $impugnacion->radicado());

        $this->assertSame('impugnacion_realizada', $proceso->fresh()->estado);
        $this->assertTrue((bool) $proceso->fresh()->impugnado);

        Mail::assertSent(ImpugnacionConstancia::class, fn ($mail) => $mail->hasTo('trabajador@test.com'));
    }

    public function test_no_se_puede_impugnar_dos_veces(): void
    {
        Mail::fake();
        $proceso = $this->procesoSancionado();
        $proceso->forceFill(['impugnacion_otp_verificado_en' => now()])->saveQuietly();

        $this->servicio()->presentar($proceso->fresh(), ['motivos' => ['debido_proceso']], [], null, null);

        $this->expectException(\DomainException::class);
        $this->servicio()->presentar($proceso->fresh(), ['motivos' => ['debido_proceso']], [], null, null);
    }

    public function test_pasado_el_plazo_no_se_puede_presentar(): void
    {
        Mail::fake();
        $proceso = $this->procesoSancionado();
        $proceso->forceFill([
            'impugnacion_otp_verificado_en' => now(),
            'fecha_limite_impugnacion' => now()->subDays(2),
        ])->saveQuietly();

        $this->assertFalse($proceso->fresh()->puedeImpugnarse());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('plazo');
        $this->servicio()->presentar($proceso->fresh(), ['motivos' => ['debido_proceso']], [], null, null);
    }

    public function test_la_pagina_publica_responde_sin_sesion(): void
    {
        $this->withoutVite();
        $proceso = $this->procesoSancionado();
        $url = $proceso->urlImpugnacion();

        $this->get($url)->assertOk()->assertSee('Impugnar la decisión');
        $this->get('/impugnar/' . str_repeat('b', 64))->assertOk();
    }
}
