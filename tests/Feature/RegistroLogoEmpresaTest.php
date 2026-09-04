<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Auth\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistroLogoEmpresaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
    }

    private function datosMinimos(): array
    {
        return [
            'razon_social'        => 'Empresa de Prueba S.A.S.',
            'nit'                 => '900123456-1',
            'representante_legal' => 'Ana Prueba',
            'name'                => 'Ana Prueba',
            'email'               => 'ana@empresaprueba.co',
            'password'            => 'secret123',
        ];
    }

    public function test_completa_el_registro_sin_logo_si_elige_lo_agregare_despues(): void
    {
        $page = new Register();
        $user = $page->crearCuentaEmpresa($this->datosMinimos());

        $this->assertNull($user->empresa->logo_path);
        $this->assertNull($user->empresa->logo_color_acento);
    }

    public function test_completa_el_registro_con_logo_si_elige_ya_lo_tengo(): void
    {
        Storage::fake('local');
        $archivo = UploadedFile::fake()->image('logo.png', 100, 100);
        $rutaTemp = $archivo->store('logos-temp', 'local');

        $page = new Register();
        $user = $page->crearCuentaEmpresa(array_merge($this->datosMinimos(), [
            'logo_opcion' => 'tiene',
            'logo_empresa_temp' => $rutaTemp,
        ]));

        $empresa = $user->empresa;
        $this->assertNotNull($empresa->logo_path);
        $this->assertNotNull($empresa->logo_color_acento);
        Storage::disk('local')->assertExists($empresa->logo_path);
    }
}
