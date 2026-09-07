<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\EmpresaResource\Pages\EditEmpresa;
use App\Models\Empresa;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EmpresaResourceLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        // EmpresaPolicy::update()/view() exige permisos reales de Spatie
        // (view_empresa/update_empresa) - el rol 'super_admin' por sí solo
        // no basta, no hay ningún Gate::before que lo exima (confirmado, no
        // existe ninguno en el proyecto).
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_editar_una_empresa_para_agregarle_el_logo_calcula_el_color(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $admin->assignRole('super_admin');
        // telefono se sobreescribe con un valor de 10 dígitos: el form de
        // EmpresaResource tiene ->mask('9999999999')->maxLength(10), y el
        // valor por defecto de fake()->phoneNumber() no cumple ese formato
        // (falla la validación por algo ajeno a este test).
        $empresa = Empresa::factory()->create(['active' => true, 'telefono' => '3001234567']);

        Livewire::actingAs($admin)
            ->test(EditEmpresa::class, ['record' => $empresa->id])
            ->fillForm(['logo_empresa_temp' => UploadedFile::fake()->image('logo.png', 100, 100)])
            ->call('save')
            ->assertHasNoFormErrors();

        $empresa->refresh();

        $this->assertNotNull($empresa->logo_path);
        $this->assertNotNull($empresa->logo_color_acento);
        Storage::disk('local')->assertExists($empresa->logo_path);
    }

    /**
     * Bug real reportado por el usuario (2026-09-07): tras guardar el logo
     * y volver a "Mi empresa", el campo de subida aparecía vacío - el
     * cliente pensaba que no se había guardado y lo volvía a subir.
     */
    public function test_al_reabrir_el_formulario_el_logo_ya_guardado_se_precarga(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $admin->assignRole('super_admin');
        $empresa = Empresa::factory()->create(['active' => true, 'telefono' => '3001234567']);

        Livewire::actingAs($admin)
            ->test(EditEmpresa::class, ['record' => $empresa->id])
            ->fillForm(['logo_empresa_temp' => UploadedFile::fake()->image('logo.png', 100, 100)])
            ->call('save')
            ->assertHasNoFormErrors();

        $logoPathGuardado = $empresa->fresh()->logo_path;

        // Reabrir el formulario (nueva instancia del componente, como al
        // navegar de nuevo a "Mi empresa") - el campo debe venir precargado.
        // El estado de un FileUpload no-múltiple puede llegar como string o
        // como array [uuid => ruta] según cómo Livewire lo serialice - se
        // normaliza igual que ya hace EditEmpresa::afterSave().
        $component = Livewire::actingAs($admin)->test(EditEmpresa::class, ['record' => $empresa->id]);
        $estado = $component->get('data.logo_empresa_temp');
        $rutaPrecargada = is_array($estado) ? (reset($estado) ?: null) : $estado;

        $this->assertSame($logoPathGuardado, $rutaPrecargada);
    }

    /**
     * Guardar sin tocar el logo (campo ya precargado con la ruta permanente)
     * no debe reprocesarlo ni recalcular el color de acento de nuevo.
     */
    public function test_guardar_sin_cambiar_el_logo_no_lo_reprocesa(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $admin->assignRole('super_admin');
        $empresa = Empresa::factory()->create(['active' => true, 'telefono' => '3001234567']);

        Livewire::actingAs($admin)
            ->test(EditEmpresa::class, ['record' => $empresa->id])
            ->fillForm(['logo_empresa_temp' => UploadedFile::fake()->image('logo.png', 100, 100)])
            ->call('save');

        $colorOriginal = $empresa->fresh()->logo_color_acento;
        $rutaOriginal = $empresa->fresh()->logo_path;

        Livewire::actingAs($admin)
            ->test(EditEmpresa::class, ['record' => $empresa->id])
            ->fillForm(['razon_social' => 'RENBEL ACTUALIZADA'])
            ->call('save')
            ->assertHasNoFormErrors();

        $empresa->refresh();
        $this->assertSame($rutaOriginal, $empresa->logo_path);
        $this->assertSame($colorOriginal, $empresa->logo_color_acento);
    }
}
