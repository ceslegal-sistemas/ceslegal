<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CrearSolicitudContratoWizardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Filament renderiza los 5 pasos del Wizard completos en el HTML inicial
     * (oculta los inactivos con Alpine, no los omite del DOM) - basta con
     * montar la página para confirmar que los 5 step-header nuevos (Forms\
     * Components\View) no rompen el render de ningún paso. Navegar entre
     * pasos con fillForm()/nextStep() es sabidamente frágil en Wizards de
     * este proyecto (ver memoria: filament-wizard-fillform-no-aplica.md),
     * así que no se ejercita aquí - suficiente con la verificación visual
     * manual del usuario.
     */
    public function test_el_wizard_de_crear_renderiza_los_5_pasos_con_step_header(): void
    {
        Permission::findOrCreate('create_solicitud::contrato', 'web');
        Permission::findOrCreate('view_any_solicitud::contrato', 'web');
        $user = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $user->givePermissionTo(['create_solicitud::contrato', 'view_any_solicitud::contrato']);
        $this->actingAs($user);

        // Visitar la ruta real (no montar el Livewire component suelto):
        // CreateRecord::mount() resuelve Filament::getCurrentPanel() a partir
        // de la URL de la petición - sin pasar por una request HTTP real a
        // una ruta del panel, el contexto de panel queda vacío y
        // canCreate()/la Policy fallan con 403 aunque el permiso sí exista.
        $response = $this->get(\App\Filament\Admin\Resources\SolicitudContratoResource::getUrl('create'))
            ->assertSuccessful()
            ->assertSee('Asistente de Generación de Contratos')
            ->assertSee('Información Básica')
            ->assertSee('Datos del Trabajador')
            ->assertSee('Detalles del Cargo')
            // "Detalles del Cargo" (fechas/salario/ubicación/jornada) se separó
            // en su propio paso a pedido del usuario - el paso original era
            // demasiado largo.
            ->assertSee('Condiciones del Contrato')
            ->assertSee('Documentos')
            ->assertSee('Paso 1 de 5')
            ->assertSee('Paso 5 de 5')
            // El hero widget de arriba (SolicitudContratoRecordHeroWidget) se quitó
            // de esta página - quedaba redundante con el Paso Bienvenida nuevo.
            ->assertDontSee('ASISTENTE CON IA')
            // Clase que activa la regla CSS ya registrada globalmente en
            // PanelBrandingServiceProvider (.ces-hide-wizard-steps .fi-fo-wizard-header
            // {display:none}) - oculta el stepper nativo de Filament, igual que
            // CreateProcesoDisciplinario.
            ->assertSee('ces-hide-wizard-steps', false);
    }
}
