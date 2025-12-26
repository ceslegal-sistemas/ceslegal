<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ArticulosLegalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $articulos = [
            [
                'codigo' => 'Art. 58',
                'titulo' => 'Obligaciones especiales del trabajador',
                'descripcion' => 'Obligaciones del trabajador frente al empleador',
                'categoria' => 'Obligaciones',
                'orden' => 1,
            ],
            [
                'codigo' => 'Art. 60',
                'titulo' => 'Prohibiciones a los trabajadores',
                'descripcion' => 'Prohibiciones generales a los trabajadores',
                'categoria' => 'Prohibiciones',
                'orden' => 2,
            ],
            [
                'codigo' => 'Art. 60 Num. 1',
                'titulo' => 'Faltar al trabajo sin justa causa',
                'descripcion' => 'Faltar al trabajo sin justa causa de impedimento o sin permiso del empleador',
                'categoria' => 'Prohibiciones',
                'orden' => 3,
            ],
            [
                'codigo' => 'Art. 60 Num. 2',
                'titulo' => 'Sustraer de la fábrica, taller o establecimiento',
                'descripcion' => 'Sustraer de la fábrica, taller o establecimiento, los útiles de trabajo y las materias primas o productos elaborados',
                'categoria' => 'Prohibiciones',
                'orden' => 4,
            ],
            [
                'codigo' => 'Art. 60 Num. 3',
                'titulo' => 'Presentarse en estado de embriaguez',
                'descripcion' => 'Presentarse al trabajo en estado de embriaguez o bajo la influencia de narcóticos o drogas enervantes',
                'categoria' => 'Prohibiciones',
                'orden' => 5,
            ],
            [
                'codigo' => 'Art. 60 Num. 4',
                'titulo' => 'Conservar armas',
                'descripcion' => 'Conservar armas de cualquier clase en el sitio de trabajo, a excepción de las que con autorización legal puedan llevar los celadores',
                'categoria' => 'Prohibiciones',
                'orden' => 6,
            ],
            [
                'codigo' => 'Art. 60 Num. 5',
                'titulo' => 'Faltar al respeto y consideración',
                'descripcion' => 'Faltar al respeto y consideración debidos al empleador, a los miembros de su familia, a los representantes o a los compañeros de trabajo',
                'categoria' => 'Prohibiciones',
                'orden' => 7,
            ],
            [
                'codigo' => 'Art. 60 Num. 6',
                'titulo' => 'Hacer colectas o propaganda',
                'descripcion' => 'Hacer colectas, rifas y suscripciones o cualquier clase de propaganda en los lugares de trabajo',
                'categoria' => 'Prohibiciones',
                'orden' => 8,
            ],
            [
                'codigo' => 'Art. 60 Num. 7',
                'titulo' => 'Coartar la libertad para trabajar o no trabajar',
                'descripcion' => 'Coartar la libertad para trabajar o no trabajar, o para afiliarse o no a un sindicato o permanecer en él o retirarse',
                'categoria' => 'Prohibiciones',
                'orden' => 9,
            ],
            [
                'codigo' => 'Art. 60 Num. 8',
                'titulo' => 'Usar los útiles o herramientas suministrados',
                'descripcion' => 'Usar los útiles o herramientas suministrados por el empleador en objetos distintos del trabajo contratado',
                'categoria' => 'Prohibiciones',
                'orden' => 10,
            ],
        ];

        foreach ($articulos as $articulo) {
            \App\Models\ArticuloLegal::create($articulo);
        }

        $this->command->info('✅ Artículos legales del CST cargados exitosamente');
        $this->command->info('📝 Total de artículos: ' . count($articulos));
    }
}
