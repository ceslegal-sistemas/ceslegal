<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fecha de publicación del RIT (pedido del equipo, 2026-09-29): la empresa
 * puede tardar días en publicar físicamente el Reglamento (carteleras)
 * después de generarlo en el sistema - los 15 días hábiles para objetar se
 * cuentan desde esa fecha declarada, no desde la creación del RIT.
 */
class MiReglamentoInternoFechaPublicacionActionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCliente(Empresa $empresa): User
    {
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($user);

        return $user;
    }

    public function test_visible_para_cliente_con_rit_con_texto(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->assertActionVisible('declararFechaPublicacion');
    }

    public function test_oculta_si_no_hay_texto_completo(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => null, 'estado_generacion' => 'generando']);
        $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->assertActionHidden('declararFechaPublicacion');
    }

    public function test_guarda_la_fecha_declarada(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $this->actingAsCliente($empresa);

        $fecha = now()->addDay()->toDateString();

        Livewire::test(MiReglamentoInterno::class)
            ->callAction('declararFechaPublicacion', data: [
                'fecha_publicacion_socializacion' => $fecha,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($fecha, $rit->fresh()->fecha_publicacion_socializacion->toDateString());
    }

    /**
     * Bug real reportado por el usuario (2026-09-30): minDate(now()) comparaba
     * contra la hora exacta de hoy, así que "hoy" (interpretado como
     * medianoche) siempre quedaba "antes" y la validación lo rechazaba -
     * incluso siendo el primer valor permitido según el enunciado del campo.
     */
    public function test_acepta_la_fecha_de_hoy(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->callAction('declararFechaPublicacion', data: [
                'fecha_publicacion_socializacion' => today()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(today()->toDateString(), $rit->fresh()->fecha_publicacion_socializacion->toDateString());
    }

    public function test_rechaza_una_fecha_pasada(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->callAction('declararFechaPublicacion', data: [
                'fecha_publicacion_socializacion' => now()->subDay()->toDateString(),
            ])
            ->assertHasActionErrors(['fecha_publicacion_socializacion']);

        $this->assertNull($rit->fresh()->fecha_publicacion_socializacion);
    }

    public function test_calcula_la_fecha_limite_de_objecion_a_15_dias_habiles(): void
    {
        $empresa = Empresa::factory()->create(['active' => true, 'dias_habiles' => [1, 2, 3, 4, 5]]);
        $fechaPublicacion = now()->addDay();
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'fecha_publicacion_socializacion' => $fechaPublicacion->toDateString(),
        ]);

        // Mismo cálculo que usa el modelo (TerminoLegalService), calculado
        // aquí de forma independiente para no depender de una fecha fija que
        // pueda desalinearse con "hoy" cuando corran los tests.
        $esperado = app(\App\Services\TerminoLegalService::class)
            ->calcularFechaVencimiento($fechaPublicacion->copy(), 15, [1, 2, 3, 4, 5]);

        $this->assertSame($esperado->toDateString(), $rit->fresh()->fechaLimiteObjecion()->toDateString());
    }

    public function test_sin_fecha_declarada_no_hay_fecha_limite(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);

        $this->assertNull($rit->fechaLimiteObjecion());
    }
}
