<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DepartamentosMunicipiosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Datos oficiales del DANE - División Político Administrativa de Colombia (DIVIPOLA)
     * Total: 32 departamentos + Bogotá D.C. y 1,122+ municipios
     * Fuente: https://www.datos.gov.co/resource/gdxc-w37w.json
     */
    public function run(): void
    {
        $this->command->info('Descargando datos oficiales del DANE...');

        // Descargar datos desde la API oficial del DANE
        $response = Http::timeout(60)->get('https://www.datos.gov.co/resource/gdxc-w37w.json', [
            '$limit' => 2000, // Obtener todos los municipios
        ]);

        if (!$response->successful()) {
            $this->command->error('Error al descargar datos del DANE. Usando datos locales...');
            $this->insertarDatosLocales();
            return;
        }

        $municipiosData = $response->json();
        $this->command->info('Descargados ' . count($municipiosData) . ' municipios del DANE');

        // Desactivar foreign keys temporalmente
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Extraer departamentos únicos
        $departamentosUnicos = collect($municipiosData)
            ->unique('cod_dpto')
            ->map(function($item) {
                return [
                    'codigo' => $item['cod_dpto'],
                    'nombre' => mb_convert_case(mb_strtolower($item['dpto'], 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
                ];
            })
            ->sortBy('codigo')
            ->values()
            ->toArray();

        $this->command->info('Insertando ' . count($departamentosUnicos) . ' departamentos...');

        // Insertar departamentos
        foreach ($departamentosUnicos as $dept) {
            DB::table('departamentos')->insert([
                'codigo' => $dept['codigo'],
                'nombre' => $dept['nombre'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Obtener IDs de departamentos
        $deptos = DB::table('departamentos')->pluck('id', 'codigo');

        $this->command->info('Insertando municipios...');

        // Insertar municipios en lotes
        $batchSize = 100;
        $municipiosBatch = [];
        $count = 0;

        foreach ($municipiosData as $muni) {
            $codDpto = $muni['cod_dpto'];
            $codMunicipio = $muni['cod_mpio'];
            $nombreMunicipio = mb_convert_case(mb_strtolower($muni['nom_mpio'], 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

            if (isset($deptos[$codDpto]) && $codMunicipio && $nombreMunicipio) {
                $municipiosBatch[] = [
                    'codigo' => $codMunicipio,
                    'departamento_id' => $deptos[$codDpto],
                    'nombre' => $nombreMunicipio,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $count++;

                if (count($municipiosBatch) >= $batchSize) {
                    DB::table('municipios')->insert($municipiosBatch);
                    $municipiosBatch = [];
                    $this->command->info("  → Insertados $count municipios...");
                }
            }
        }

        // Insertar el último lote
        if (!empty($municipiosBatch)) {
            DB::table('municipios')->insert($municipiosBatch);
        }

        // Reactivar foreign keys
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $totalDepartamentos = DB::table('departamentos')->count();
        $totalMunicipios = DB::table('municipios')->count();

        $this->command->info('Se insertaron exitosamente:');
        $this->command->info("  → $totalDepartamentos departamentos");
        $this->command->info("  → $totalMunicipios municipios");
    }

    /**
     * Insertar datos locales en caso de fallo de la API
     */
    private function insertarDatosLocales(): void
    {
        // Datos locales mínimos para funcionamiento básico
        $departamentos = [
            ['codigo' => '05', 'nombre' => 'Antioquia'],
            ['codigo' => '08', 'nombre' => 'Atlántico'],
            ['codigo' => '11', 'nombre' => 'Bogotá D.C.'],
            ['codigo' => '13', 'nombre' => 'Bolívar'],
            ['codigo' => '15', 'nombre' => 'Boyacá'],
            ['codigo' => '17', 'nombre' => 'Caldas'],
            ['codigo' => '18', 'nombre' => 'Caquetá'],
            ['codigo' => '19', 'nombre' => 'Cauca'],
            ['codigo' => '20', 'nombre' => 'Cesar'],
            ['codigo' => '23', 'nombre' => 'Córdoba'],
            ['codigo' => '25', 'nombre' => 'Cundinamarca'],
            ['codigo' => '27', 'nombre' => 'Chocó'],
            ['codigo' => '41', 'nombre' => 'Huila'],
            ['codigo' => '44', 'nombre' => 'La Guajira'],
            ['codigo' => '47', 'nombre' => 'Magdalena'],
            ['codigo' => '50', 'nombre' => 'Meta'],
            ['codigo' => '52', 'nombre' => 'Nariño'],
            ['codigo' => '54', 'nombre' => 'Norte de Santander'],
            ['codigo' => '63', 'nombre' => 'Quindío'],
            ['codigo' => '66', 'nombre' => 'Risaralda'],
            ['codigo' => '68', 'nombre' => 'Santander'],
            ['codigo' => '70', 'nombre' => 'Sucre'],
            ['codigo' => '73', 'nombre' => 'Tolima'],
            ['codigo' => '76', 'nombre' => 'Valle del Cauca'],
            ['codigo' => '81', 'nombre' => 'Arauca'],
            ['codigo' => '85', 'nombre' => 'Casanare'],
            ['codigo' => '86', 'nombre' => 'Putumayo'],
            ['codigo' => '88', 'nombre' => 'San Andrés y Providencia'],
            ['codigo' => '91', 'nombre' => 'Amazonas'],
            ['codigo' => '94', 'nombre' => 'Guainía'],
            ['codigo' => '95', 'nombre' => 'Guaviare'],
            ['codigo' => '97', 'nombre' => 'Vaupés'],
            ['codigo' => '99', 'nombre' => 'Vichada'],
        ];

        foreach ($departamentos as $dept) {
            DB::table('departamentos')->insert([
                'codigo' => $dept['codigo'],
                'nombre' => $dept['nombre'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Se insertaron ' . count($departamentos) . ' departamentos (datos locales)');
        $this->command->warn('No se pudieron descargar los municipios. Ejecute el seeder nuevamente con conexión a internet.');
    }
}
