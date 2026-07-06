<?php

namespace Tests\Feature\Bufete;

use App\Filament\Admin\Pages\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistroBufeteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Los métodos de registro llaman a assignRole(), que requiere que el rol
        // exista en la BD (Spatie). Se crean aquí para no depender del seeder.
        Role::firstOrCreate(['name' => 'bufete', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
    }

    public function test_registro_bufete_crea_bufete_y_abogado(): void
    {
        $page = new Register();
        $user = $page->crearCuentaBufete([
            'tipo_cuenta'    => 'bufete',
            'bufete_nombre'  => 'Rendón & Asociados',
            'bufete_nit'     => '900111222-3',
            'name'           => 'Juan',
            'email'          => 'juan@bufete.co',
            'password'       => 'secret123',
        ]);

        $this->assertSame('bufete', $user->role);
        $this->assertNotNull($user->bufete_id);
        $this->assertNull($user->empresa_id);
        $this->assertDatabaseHas('bufetes', ['nombre' => 'Rendón & Asociados', 'nit' => '900111222-3']);
        $this->assertDatabaseHas('users', ['email' => 'juan@bufete.co', 'role' => 'bufete']);
    }
}
