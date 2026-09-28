<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use App\Services\SolicitudContratoIAService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Botón opcional "Redactar con IA" del Motivo de Terminación de Contrato con
 * justa causa - puramente asistivo (el abogado puede escribir el motivo a
 * mano), a diferencia del cálculo de indemnización que es 100%
 * determinístico y nunca usa IA (ver TerminacionContratoServiceTest).
 */
class RedactarMotivoTerminacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_convierte_la_nota_breve_en_un_parrafo_formal(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => 'El trabajador incurrió en llegadas tarde reiteradas, habiendo recibido dos llamados de atención previos.',
                    ]]],
                ]],
            ], 200),
        ]);

        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $solicitud = SolicitudContrato::create([
            'empresa_id' => $empresa->id,
            'estado' => 'aprobado',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
            'fecha_inicio_propuesta' => '2024-01-01',
            'fecha_inicio_periodo_actual' => '2024-01-01',
        ]);

        $texto = app(SolicitudContratoIAService::class)
            ->redactarMotivoTerminacion('llegó tarde muchas veces, ya van 2 llamados', $solicitud);

        $this->assertStringContainsString('llegadas tarde reiteradas', $texto);
    }
}
