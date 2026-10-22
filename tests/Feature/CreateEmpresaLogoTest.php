<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\EmpresaResource\Pages\CreateEmpresa;
use App\Models\Empresa;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bug real reportado por el usuario (2026-10-22): al crear una empresa
 * desde el panel de super_admin, el logo se subía (quedaba en el disco
 * temporal) pero nunca aparecía al editar la empresa - CreateEmpresa nunca
 * tenía el código que mueve logo_empresa_temp a su ruta permanente y guarda
 * logo_path, a diferencia de EditEmpresa y Register (que sí lo tienen).
 */
class CreateEmpresaLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $departamentoId = DB::table('departamentos')->insertGetId([
            'codigo' => '11', 'nombre' => 'Bogotá D.C.', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('municipios')->insert([
            'codigo' => '11001', 'departamento_id' => $departamentoId, 'nombre' => 'Bogotá D.C.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function datosMinimos(): array
    {
        return [
            'razon_social'        => 'EMPRESA DE PRUEBA SAS',
            'nit'                 => '900123456-1',
            'representante_legal' => 'Ana Prueba',
            'departamento'        => 'Bogotá D.C.',
            'ciudad'              => 'Bogotá D.C.',
            'user_nombre'         => 'Ana Prueba',
            'user_email'          => 'ana@empresaprueba.co',
            'user_email_confirmation' => 'ana@empresaprueba.co',
        ];
    }

    public function test_crear_una_empresa_con_logo_lo_guarda_de_forma_permanente(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(CreateEmpresa::class)
            ->fillForm(array_merge($this->datosMinimos(), [
                'logo_empresa_temp' => UploadedFile::fake()->image('logo.png', 100, 100),
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $empresa = Empresa::where('nit', '900123456-1')->firstOrFail();

        $this->assertNotNull($empresa->logo_path, 'logo_path debía quedar guardado al crear la empresa.');
        $this->assertNotNull($empresa->logo_color_acento);
        Storage::disk('local')->assertExists($empresa->logo_path);
    }

    public function test_crear_una_empresa_sin_logo_no_falla(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(CreateEmpresa::class)
            ->fillForm($this->datosMinimos())
            ->call('create')
            ->assertHasNoFormErrors();

        $empresa = Empresa::where('nit', '900123456-1')->firstOrFail();
        $this->assertNull($empresa->logo_path);
    }
}
