<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VerSolicitudContratoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_ver_muestra_las_tarjetas_de_resumen_y_el_objeto_juridico(): void
    {
        Permission::findOrCreate('view_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_solicitud::contrato', 'view_any_solicitud::contrato']);
        $this->actingAs($user);

        // Nota: Empresa normaliza el sufijo societario al guardar (ver
        // backlog-ux-rit-2026-08-12.md, "sufijo societario duplicado") - se
        // lee el valor YA persistido en vez de asumir el literal pasado al
        // factory.
        $empresa = Empresa::factory()->create(['active' => true, 'razon_social' => 'ACME S.A.S.'])->fresh();
        $solicitud = SolicitudContrato::create([
            'empresa_id' => $empresa->id,
            'estado' => 'aprobado',
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
            'objeto_juridico_redactado' => '<p>Texto redactado por la IA.</p>',
        ]);

        $this->get(SolicitudContratoResource::getUrl('view', ['record' => $solicitud]))
            ->assertSuccessful()
            ->assertSee($empresa->razon_social)
            ->assertSee('Juan Pérez')
            ->assertSee('Objeto Jurídico Redactado')
            ->assertSee('Texto redactado por la IA.', false);
    }

    public function test_la_pagina_ver_muestra_el_motivo_de_rechazo(): void
    {
        Permission::findOrCreate('view_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['view_solicitud::contrato', 'view_any_solicitud::contrato']);
        $this->actingAs($user);

        $empresa = Empresa::factory()->create(['active' => true]);
        $solicitud = SolicitudContrato::create([
            'empresa_id' => $empresa->id,
            'estado' => 'rechazado',
            'motivo_rechazo' => 'No hay presupuesto para este cargo.',
            'tipo_contrato' => 'Contrato a Término Fijo',
            'fecha_solicitud' => now(),
            'trabajador_nombres' => 'Ana',
            'trabajador_apellidos' => 'Gómez',
            'trabajador_documento_tipo' => 'CC',
            'trabajador_documento_numero' => '456',
            'trabajador_email' => 'ana@test.com',
            'cargo_contrato' => 'Operario',
            'responsabilidades' => '<p>x</p>',
            'objeto_comercial' => '<p>x</p>',
            'manual_funciones' => '<p>x</p>',
        ]);

        $this->get(SolicitudContratoResource::getUrl('view', ['record' => $solicitud]))
            ->assertSuccessful()
            ->assertSee('Motivo del Rechazo')
            ->assertSee('No hay presupuesto para este cargo.');
    }
}
