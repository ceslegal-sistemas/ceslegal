<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real en producción (empresa RENBEL, empresa_id=4):
 * UniqueConstraintViolationException "Duplicate entry 'SC-2026-0001'" al
 * crear la primera Solicitud de Contrato de una empresa nueva, porque otra
 * empresa ya tenía ese código.
 *
 * Causa raíz: SolicitudContrato usa ScopedToBufeteOrEmpresa (global scope
 * que filtra por empresa_id para el rol cliente). SolicitudContratoObserver::
 * generarCodigoUnico() buscaba "el último código" sin excluir ese scope, así
 * que para un usuario cliente la búsqueda quedaba silenciosamente acotada a
 * SU empresa - la primera solicitud de cualquier empresa nueva siempre
 * calculaba "0001", aunque `codigo` es único en TODA la tabla (todas las
 * empresas comparten la misma secuencia SC-{año}-NNNN).
 */
class SolicitudContratoCodigoGlobalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function datosMinimos(int $empresaId): array
    {
        return [
            'empresa_id' => $empresaId,
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123456',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
        ];
    }

    public function test_la_primera_solicitud_de_una_empresa_nueva_no_choca_con_el_codigo_de_otra_empresa(): void
    {
        $empresaA = Empresa::factory()->create(['active' => true]);
        $empresaB = Empresa::factory()->create(['active' => true]);

        // Empresa A ya tiene una solicitud - se llevó "SC-{año}-0001".
        $userA = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresaA->id, 'active' => true]);
        $this->actingAs($userA);
        $solicitudA = SolicitudContrato::create($this->datosMinimos($empresaA->id));

        $anio = now()->year;
        $this->assertSame("SC-{$anio}-0001", $solicitudA->codigo);

        // Empresa B crea su PRIMERA solicitud - antes del fix, un usuario
        // "cliente" generaba "0001" de nuevo (scope leak) y chocaba con la
        // restricción única global de `codigo`.
        $userB = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresaB->id, 'active' => true]);
        $this->actingAs($userB);
        $solicitudB = SolicitudContrato::create($this->datosMinimos($empresaB->id));

        $this->assertSame("SC-{$anio}-0002", $solicitudB->codigo);
        $this->assertNotSame($solicitudA->codigo, $solicitudB->codigo);
    }
}
