<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DiaNoHabilSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $festivosColombia = [
            // 2025
            ['fecha' => '2025-01-01', 'descripcion' => 'Año Nuevo', 'tipo' => 'festivo', 'recurrente' => true],
            ['fecha' => '2025-01-06', 'descripcion' => 'Día de los Reyes Magos', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-03-24', 'descripcion' => 'Día de San José', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-04-17', 'descripcion' => 'Jueves Santo', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-04-18', 'descripcion' => 'Viernes Santo', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-05-01', 'descripcion' => 'Día del Trabajo', 'tipo' => 'festivo', 'recurrente' => true],
            ['fecha' => '2025-06-02', 'descripcion' => 'Ascensión del Señor', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-06-23', 'descripcion' => 'Corpus Christi', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-06-30', 'descripcion' => 'Sagrado Corazón de Jesús', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-07-07', 'descripcion' => 'San Pedro y San Pablo', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-07-20', 'descripcion' => 'Día de la Independencia', 'tipo' => 'festivo', 'recurrente' => true],
            ['fecha' => '2025-08-07', 'descripcion' => 'Batalla de Boyacá', 'tipo' => 'festivo', 'recurrente' => true],
            ['fecha' => '2025-08-18', 'descripcion' => 'Asunción de la Virgen', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-10-13', 'descripcion' => 'Día de la Raza', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-11-03', 'descripcion' => 'Todos los Santos', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-11-17', 'descripcion' => 'Independencia de Cartagena', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2025-12-08', 'descripcion' => 'Inmaculada Concepción', 'tipo' => 'festivo', 'recurrente' => true],
            ['fecha' => '2025-12-25', 'descripcion' => 'Navidad', 'tipo' => 'festivo', 'recurrente' => true],

            // 2026
            ['fecha' => '2026-01-01', 'descripcion' => 'Año Nuevo', 'tipo' => 'festivo', 'recurrente' => true],
            ['fecha' => '2026-01-12', 'descripcion' => 'Día de los Reyes Magos', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-03-23', 'descripcion' => 'Día de San José', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-04-02', 'descripcion' => 'Jueves Santo', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-04-03', 'descripcion' => 'Viernes Santo', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-05-01', 'descripcion' => 'Día del Trabajo', 'tipo' => 'festivo', 'recurrente' => true],
            ['fecha' => '2026-05-18', 'descripcion' => 'Ascensión del Señor', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-06-08', 'descripcion' => 'Corpus Christi', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-06-15', 'descripcion' => 'Sagrado Corazón de Jesús', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-06-29', 'descripcion' => 'San Pedro y San Pablo', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-07-20', 'descripcion' => 'Día de la Independencia', 'tipo' => 'festivo', 'recurrente' => true],
            ['fecha' => '2026-08-07', 'descripcion' => 'Batalla de Boyacá', 'tipo' => 'festivo', 'recurrente' => true],
            ['fecha' => '2026-08-17', 'descripcion' => 'Asunción de la Virgen', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-10-12', 'descripcion' => 'Día de la Raza', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-11-02', 'descripcion' => 'Todos los Santos', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-11-16', 'descripcion' => 'Independencia de Cartagena', 'tipo' => 'festivo', 'recurrente' => false],
            ['fecha' => '2026-12-08', 'descripcion' => 'Inmaculada Concepción', 'tipo' => 'festivo', 'recurrente' => true],
            ['fecha' => '2026-12-25', 'descripcion' => 'Navidad', 'tipo' => 'festivo', 'recurrente' => true],
        ];

        foreach ($festivosColombia as $festivo) {
            \App\Models\DiaNoHabil::create($festivo);
        }
    }
}
