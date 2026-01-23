<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Crear usuario super_admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@ceslegal.co'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin12345'),
                'role' => 'super_admin',
                'active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Asignar rol de Spatie Permission
        if (!$admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        $this->command->info('Usuario Super Admin creado:');
        $this->command->table(
            ['Campo', 'Valor'],
            [
                ['Email', 'admin@ceslegal.co'],
                ['Contraseña', 'admin12345'],
                ['Rol', 'super_admin'],
            ]
        );
    }
}
