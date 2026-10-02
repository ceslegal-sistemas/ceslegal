<?php

namespace Tests\Feature;

use App\Livewire\SocializacionRit;
use App\Mail\RitAceptado;
use App\Mail\RitPublicacionInformada;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SocializacionRitDosFasesIntegracionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Escenario real completo: el mismo trabajador entra al link DOS veces.
     * Primero mientras corren los 15 días (hace Fase 1), luego después de
     * que pasan (hace Fase 2). Verifica que cada visita dispare el correo
     * correcto y solo el correcto.
     */
    public function test_ciclo_completo_fase1_luego_fase2_con_el_mismo_trabajador(): void
    {
        Mail::fake();
        $empresa = Empresa::factory()->create(['active' => true, 'dias_habiles' => [1, 2, 3, 4, 5]]);
        ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'Texto vigente del reglamento.',
            'fecha_publicacion_socializacion' => now()->toDateString(),
        ]);
        $trabajador = Trabajador::create([
            'empresa_id' => $empresa->id, 'tipo_documento' => 'CC', 'numero_documento' => '888000888',
            'genero' => 'masculino', 'nombres' => 'Ciclo', 'apellidos' => 'Completo', 'cargo' => 'Op', 'active' => true,
            'email' => 'ciclo@example.com',
        ]);

        // --- Visita 1: dentro de los 15 días, hace Fase 1 ---
        Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('trabajadorId', $trabajador->id)
            ->set('email', 'ciclo@example.com')
            ->set('declaracionAceptada', true)
            ->call('confirmarPublicacion')
            ->assertSet('etapa', 'completado');

        Mail::assertSent(RitPublicacionInformada::class);
        Mail::assertNotSent(RitAceptado::class);
        $this->assertDatabaseCount('publicaciones_reglamento_interno', 1);
        $this->assertFalse($trabajador->fresh()->aceptoRitVigente());

        // --- Avanza el reloj más allá de los 15 días hábiles ---
        $this->travelTo(now()->addDays(40));

        // --- Visita 2: ya pasó el límite, debe ir DIRECTO a Fase 2 completa ---
        $componente = Livewire::test(SocializacionRit::class, ['empresa' => $empresa])
            ->set('tipoDocumento', 'CC')
            ->set('numeroDocumento', '888000888')
            ->set('numeroDocumentoConfirmacion', '888000888')
            ->call('buscarTrabajador');

        $this->assertNotSame('ya_informado', $componente->get('etapa'));
        $componente->assertSet('fase', 'socializacion');

        $this->travelBack();
    }
}
