<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmpresaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $empresas = [
            [
                'razon_social' => 'Grupo Empresarial Andino S.A.S.',
                'nit' => '900123456-1',
                'direccion' => 'Calle 100 # 18A-30',
                'telefono' => '(601) 555-1000',
                'email_contacto' => 'rrhh@grupoandino.com',
                'ciudad' => 'Bogotá',
                'departamento' => 'Cundinamarca',
                'representante_legal' => 'Carlos Alberto Rodríguez',
                'active' => true,
            ],
            [
                'razon_social' => 'Tecnologías del Valle Ltda.',
                'nit' => '890234567-2',
                'direccion' => 'Carrera 5 # 10-45',
                'telefono' => '(602) 555-2000',
                'email_contacto' => 'talento@tecvalle.com',
                'ciudad' => 'Cali',
                'departamento' => 'Valle del Cauca',
                'representante_legal' => 'María Fernanda Gómez',
                'active' => true,
            ],
            [
                'razon_social' => 'Industrias del Norte S.A.',
                'nit' => '800345678-3',
                'direccion' => 'Avenida Oriental # 52-120',
                'telefono' => '(604) 555-3000',
                'email_contacto' => 'recursoshumanos@indusnorte.com',
                'ciudad' => 'Medellín',
                'departamento' => 'Antioquia',
                'representante_legal' => 'Jorge Esteban Martínez',
                'active' => true,
            ],
        ];

        foreach ($empresas as $empresa) {
            \App\Models\Empresa::create($empresa);
        }
    }
}
