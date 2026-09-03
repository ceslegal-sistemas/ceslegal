<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LevelUp\Experience\Models\Achievement;

/**
 * Logros de "Plazos de Descargos Cumplidos" - cumplimiento proactivo
 * (nunca dejar vencer un término legal), no volumen de sanciones. El
 * paquete cjmellor/level-up no guarda una "meta" en el propio Achievement
 * - los 3 umbrales (1/5/10) viven en
 * LogroDescargosService::UMBRALES_DESCARGOS_A_TIEMPO, mapeados por el
 * mismo 'name' que se siembra acá.
 */
class LogrosSeeder extends Seeder
{
    public function run(): void
    {
        $logros = [
            [
                'name' => 'Primer plazo cumplido',
                'description' => 'Cerró su primer proceso disciplinario dentro del plazo legal.',
            ],
            [
                'name' => 'Gestor puntual',
                'description' => 'Cerró 5 procesos disciplinarios dentro del plazo legal.',
            ],
            [
                'name' => 'Constancia total',
                'description' => 'Cerró 10 procesos disciplinarios dentro del plazo legal.',
            ],
        ];

        foreach ($logros as $logro) {
            Achievement::firstOrCreate(['name' => $logro['name']], [
                'description' => $logro['description'],
                'is_secret' => false,
            ]);
        }
    }
}
