<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real en PRODUCCIÓN (descargos.ceslegal.co, proceso_id=26): al emitir
 * una sanción, guardar `disclaimer_datos_autorizador_en` tiraba
 * "SQLSTATE[22007]: Invalid datetime format... '2026-09-02T11:49:25-05:00'".
 *
 * Causa raíz: HasVerificacionFotografica::aceptarDisclaimerDatos() guarda
 * el momento como `now()->toIso8601String()` (string con offset de
 * timezone, ej. "2026-09-02T11:49:25-05:00"), y ese string se pasa
 * directo a $record->update() en ProcesoDisciplinarioResource.php. Sin un
 * cast 'datetime' en el modelo, Eloquent no lo convierte antes de
 * mandarlo a MySQL, que rechaza el formato ISO 8601 con 'T' y offset.
 */
class ProcesoDisciplinarioDisclaimerAutorizadorFechaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ProcesoDisciplinarioObserver registra en el timeline con
        // Auth::id() ?? 1 - sin sesión activa necesita que exista un
        // usuario con id=1 para la FK (mismo patrón ya usado en
        // SolicitudContratoTimelineTest).
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    public function test_acepta_un_string_iso8601_con_offset_de_timezone(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '123456',
            'genero' => 'masculino',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'cargo' => 'Analista',
            'email' => 'juan@test.com',
            'telefono' => '3001234567',
            'direccion' => 'Calle 1',
            'active' => true,
        ]);
        $proceso = ProcesoDisciplinario::create([
            'codigo' => 'PD-TEST-0001',
            'empresa_id' => $empresa->id,
            'trabajador_id' => $trabajador->id,
            'hechos' => 'Hechos de prueba.',
        ]);

        // Reproduce exactamente lo que HasVerificacionFotografica::aceptarDisclaimerDatos()
        // guarda: now()->toIso8601String(), ej. "2026-09-02T11:49:25-05:00".
        $isoConOffset = now()->toIso8601String();

        $proceso->update(['disclaimer_datos_autorizador_en' => $isoConOffset]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $proceso->fresh()->disclaimer_datos_autorizador_en);
    }
}
