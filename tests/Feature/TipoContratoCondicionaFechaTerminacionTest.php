<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\CreateSolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pedido explícito del usuario: si el tipo de contrato es Indefinido, el
 * wizard no debería pedir fecha de terminación (ese dato solo aplica a
 * contratos de duración determinada). Análisis empírico contra las 3
 * plantillas PDF reales confirmó que Obra o Labor tampoco la usa (se rige
 * por la finalización de la obra, redactada por IA) - ver
 * SolicitudContratoResource::tiposConFechaTerminacion().
 */
class TipoContratoCondicionaFechaTerminacionTest extends TestCase
{
    use RefreshDatabase;

    protected function usuario(): User
    {
        Permission::findOrCreate('create_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['create_solicitud::contrato', 'view_any_solicitud::contrato']);
        $this->actingAs($user);
        return $user;
    }

    public function test_fecha_fin_contrato_oculta_para_termino_indefinido(): void
    {
        $this->usuario();

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.tipo_contrato', 'Contrato a Término Indefinido')
            ->assertFormFieldIsHidden('fecha_fin_contrato');
    }

    public function test_fecha_fin_contrato_oculta_para_obra_o_labor(): void
    {
        $this->usuario();

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.tipo_contrato', 'Contrato de Obra o Labor')
            ->assertFormFieldIsHidden('fecha_fin_contrato');
    }

    public function test_fecha_fin_contrato_visible_y_requerida_para_termino_fijo(): void
    {
        $this->usuario();

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.tipo_contrato', 'Contrato a Término Fijo')
            ->assertFormFieldIsVisible('fecha_fin_contrato')
            ->call('create')
            ->assertHasFormErrors(['fecha_fin_contrato' => 'required']);
    }

    /**
     * Los otros 2 tipos sin plantilla PDF real (Prestación de Servicios,
     * Aprendizaje) también tienen duración determinada bajo el CST/Ley 789 -
     * mismo criterio, confirmado con el usuario antes de implementar.
     */
    public function test_fecha_fin_contrato_visible_para_prestacion_de_servicios_y_aprendizaje(): void
    {
        $this->usuario();

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.tipo_contrato', 'Contrato de Prestación de Servicios')
            ->assertFormFieldIsVisible('fecha_fin_contrato');

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.tipo_contrato', 'Contrato de Aprendizaje')
            ->assertFormFieldIsVisible('fecha_fin_contrato');
    }

    /**
     * Bug real que este cambio podía introducir: al EDITAR una solicitud a
     * Término Fijo ya con fecha_fin_contrato guardada, y cambiar el tipo a
     * Indefinido, el campo oculto no se dehidrata en el update() - sin
     * limpiarlo explícitamente, la fecha vieja quedaría huérfana en la BD
     * sin que nadie la vea ni la pueda corregir.
     */
    public function test_cambiar_a_indefinido_limpia_la_fecha_fin_ya_puesta(): void
    {
        $this->usuario();

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.tipo_contrato', 'Contrato a Término Fijo')
            ->set('data.fecha_inicio_propuesta', '2026-01-01')
            ->set('data.duracion_unidad', 'mes')
            ->set('data.duracion_cantidad', 6)
            ->assertSet('data.fecha_fin_contrato', '2026-07-01')
            ->set('data.tipo_contrato', 'Contrato a Término Indefinido')
            ->assertSet('data.fecha_fin_contrato', null);
    }
}
