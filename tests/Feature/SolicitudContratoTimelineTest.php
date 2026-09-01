<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Filament\Admin\Resources\SolicitudContratoResource\Pages\ListSolicitudContratos;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SolicitudContratoTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // SolicitudContratoObserver registra en el timeline con
        // Auth::id() ?? 1 ("usuario del sistema") - sin sesión activa
        // necesita que exista un usuario con id=1 para la FK.
        User::factory()->create(['id' => 1, 'role' => 'super_admin', 'active' => true]);
    }

    private function crearSolicitud(array $overrides = []): SolicitudContrato
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        return SolicitudContrato::create(array_merge([
            'empresa_id' => $empresa->id,
            'estado' => 'borrador',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Juan',
            'trabajador_apellidos' => 'Pérez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '123',
            'trabajador_email' => 'juan@test.com',
            'cargo_contrato' => 'Analista',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
        ], $overrides));
    }

    public function test_la_creacion_de_la_solicitud_queda_en_el_timeline(): void
    {
        $solicitud = $this->crearSolicitud();

        $this->assertDatabaseHas('timeline', [
            'proceso_tipo' => 'contrato',
            'proceso_id' => $solicitud->id,
            'accion' => 'Creación',
        ]);
    }

    /**
     * Hueco real encontrado en la auditoría: el cambio de estado a
     * "rechazado" ya quedaba en el timeline, pero sin el motivo (agregado
     * el mismo día a la tabla) - había que ir al registro, no al timeline,
     * para saber por qué se rechazó.
     */
    public function test_rechazar_registra_el_motivo_en_el_timeline(): void
    {
        $solicitud = $this->crearSolicitud();

        $solicitud->update([
            'estado' => 'rechazado',
            'motivo_rechazo' => 'No hay presupuesto para este cargo.',
        ]);

        $evento = Timeline::where('proceso_tipo', 'contrato')
            ->where('proceso_id', $solicitud->id)
            ->where('accion', 'Cambio de estado')
            ->latest()
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame('rechazado', $evento->estado_nuevo);
        $this->assertSame('No hay presupuesto para este cargo.', $evento->metadata['motivo_rechazo'] ?? null);
    }

    public function test_aprobar_no_agrega_motivo_al_timeline(): void
    {
        $solicitud = $this->crearSolicitud();

        $solicitud->update(['estado' => 'aprobado']);

        $evento = Timeline::where('proceso_tipo', 'contrato')
            ->where('proceso_id', $solicitud->id)
            ->where('accion', 'Cambio de estado')
            ->latest()
            ->first();

        $this->assertNotNull($evento);
        $this->assertNull($evento->metadata);
    }

    /**
     * Hueco real encontrado en la auditoría: "Regenerar Borrador" no cambia
     * el estado (sigue en 'borrador' antes y después), así que el
     * Observer nunca detectaba nada y la regeneración quedaba invisible
     * para el timeline.
     */
    public function test_regenerar_borrador_registra_documento_generado_en_el_timeline(): void
    {
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_any_solicitud::contrato']);
        $this->actingAs($user);

        $solicitud = $this->crearSolicitud();
        $solicitud->update(['ruta_contrato' => "solicitudes-contrato/{$solicitud->empresa_id}/contrato_{$solicitud->id}.pdf"]);

        Livewire::test(ListSolicitudContratos::class)
            ->callTableAction('regenerarBorrador', $solicitud);

        $this->assertDatabaseHas('timeline', [
            'proceso_tipo' => 'contrato',
            'proceso_id' => $solicitud->id,
            'accion' => 'Documento generado',
        ]);
    }

    public function test_la_pagina_ver_muestra_el_historial_incluyendo_el_motivo_de_rechazo(): void
    {
        Permission::findOrCreate('view_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_solicitud::contrato', 'view_any_solicitud::contrato']);
        $this->actingAs($user);

        $solicitud = $this->crearSolicitud();
        $solicitud->update([
            'estado' => 'rechazado',
            'motivo_rechazo' => 'No hay presupuesto para este cargo.',
        ]);

        $this->get(SolicitudContratoResource::getUrl('view', ['record' => $solicitud]))
            ->assertSuccessful()
            ->assertSee('Historial de la Solicitud')
            ->assertSee('Creación')
            ->assertSee('Cambio de estado')
            ->assertSee('Motivo: No hay presupuesto para este cargo.');
    }
}
