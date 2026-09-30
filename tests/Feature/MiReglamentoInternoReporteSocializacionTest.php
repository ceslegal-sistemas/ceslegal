<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\AceptacionReglamentoInterno;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Desglose por trabajador de la barra "X de Y trabajadores han aceptado"
 * (pedido de Andrés Sarmiento, 2026-09-29): la barra sola no bastaba, quería
 * ver quién específicamente falta por aceptar.
 */
class MiReglamentoInternoReporteSocializacionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCliente(Empresa $empresa): User
    {
        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);
        $this->actingAs($user);

        return $user;
    }

    private function crearTrabajador(Empresa $empresa, array $overrides = []): Trabajador
    {
        return Trabajador::create(array_merge([
            'empresa_id' => $empresa->id,
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(100000000, 999999999),
            'genero' => 'masculino',
            'nombres' => 'Trabajador',
            'apellidos' => 'De Prueba',
            'cargo' => 'Operario',
            'active' => true,
        ], $overrides));
    }

    public function test_detalle_marca_aceptado_y_pendiente_correctamente(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'Texto vigente']);

        $aceptado = $this->crearTrabajador($empresa, ['nombres' => 'Ana', 'apellidos' => 'Pérez']);
        $pendiente = $this->crearTrabajador($empresa, ['nombres' => 'Luis', 'apellidos' => 'Gómez']);

        AceptacionReglamentoInterno::create([
            'trabajador_id' => $aceptado->id,
            'reglamento_interno_id' => $rit->id,
            'texto_rit_hash' => hash('sha256', $rit->texto_completo),
            'aceptado_en' => now(),
        ]);

        $this->actingAsCliente($empresa);

        $detalle = Livewire::test(MiReglamentoInterno::class)->instance()->detalleTrabajadoresSocializacion();

        $porNombre = collect($detalle)->keyBy('nombre');
        $this->assertTrue($porNombre['Ana Pérez']['acepto']);
        $this->assertFalse($porNombre['Luis Gómez']['acepto']);
    }

    public function test_trabajador_inactivo_no_aparece_en_el_detalle(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $this->crearTrabajador($empresa, ['active' => false, 'nombres' => 'Retirado', 'apellidos' => 'Ya']);

        $this->actingAsCliente($empresa);

        $detalle = Livewire::test(MiReglamentoInterno::class)->instance()->detalleTrabajadoresSocializacion();

        $this->assertEmpty($detalle);
    }

    public function test_reporte_completo_visible_solo_con_trabajadores_registrados(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        ReglamentoInterno::create(['empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1']);
        $this->actingAsCliente($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->assertActionHidden('verReporteSocializacion');

        $this->crearTrabajador($empresa);

        Livewire::test(MiReglamentoInterno::class)
            ->assertActionVisible('verReporteSocializacion');
    }
}
