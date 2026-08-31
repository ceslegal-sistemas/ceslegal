<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\CreateSolicitudContrato;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DuracionContratoTest extends TestCase
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

    public function test_duracion_en_dias_calcula_la_fecha_fin(): void
    {
        $this->usuario();

        // fillForm() NO aplica de forma confiable en Wizards de este proyecto
        // (ver memoria: filament-wizard-fillform-no-aplica.md) - se usa
        // set('data.campo', valor) campo por campo, mismo criterio ya
        // establecido en otros tests de wizards.
        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.fecha_inicio_propuesta', '2026-01-01')
            ->set('data.duracion_unidad', 'dia')
            ->set('data.duracion_cantidad', 10)
            ->assertSet('data.fecha_fin_contrato', '2026-01-11');
    }

    public function test_duracion_en_meses_calcula_la_fecha_fin(): void
    {
        $this->usuario();

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.fecha_inicio_propuesta', '2026-01-01')
            ->set('data.duracion_unidad', 'mes')
            ->set('data.duracion_cantidad', 6)
            ->assertSet('data.fecha_fin_contrato', '2026-07-01');
    }

    public function test_duracion_en_meses_mas_dias_adicionales(): void
    {
        $this->usuario();

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.fecha_inicio_propuesta', '2026-01-01')
            ->set('data.duracion_unidad', 'mes')
            ->set('data.duracion_cantidad', 1)
            ->set('data.duracion_cantidad_2', 15)
            ->assertSet('data.fecha_fin_contrato', '2026-02-16');
    }

    public function test_duracion_en_anios_mas_meses_mas_dias(): void
    {
        $this->usuario();

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.fecha_inicio_propuesta', '2026-01-01')
            ->set('data.duracion_unidad', 'anio')
            ->set('data.duracion_cantidad', 1)
            ->set('data.duracion_unidad_2', 'mes')
            ->set('data.duracion_cantidad_2', 2)
            ->set('data.duracion_cantidad_3', 10)
            ->assertSet('data.fecha_fin_contrato', '2027-03-11');
    }

    public function test_editar_la_fecha_fin_descompone_la_duracion_hacia_atras(): void
    {
        $this->usuario();

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.fecha_inicio_propuesta', '2026-01-01')
            ->set('data.fecha_fin_contrato', '2027-03-11')
            ->assertSet('data.duracion_unidad', 'anio')
            ->assertSet('data.duracion_cantidad', 1)
            ->assertSet('data.duracion_unidad_2', 'mes')
            ->assertSet('data.duracion_cantidad_2', 2)
            ->assertSet('data.duracion_cantidad_3', 10);
    }

    public function test_editar_la_fecha_fin_a_solo_dias_no_deja_unidad_2(): void
    {
        $this->usuario();

        Livewire::test(CreateSolicitudContrato::class)
            ->set('data.fecha_inicio_propuesta', '2026-01-01')
            ->set('data.fecha_fin_contrato', '2026-01-11')
            ->assertSet('data.duracion_unidad', 'dia')
            ->assertSet('data.duracion_cantidad', 10)
            ->assertSet('data.duracion_unidad_2', null);
    }
}
