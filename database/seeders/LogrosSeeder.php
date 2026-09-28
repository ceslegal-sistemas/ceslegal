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
        // Mismo lord-icon para los 4 (ya usado y confirmado en el proyecto
        // para "Logro desbloqueado" - ver dashboard-socializacion-rit-notice.blade.php).
        // 'image' es una columna de cjmellor/level-up sin usar hasta ahora -
        // se reutiliza para guardar la URL del lord-icon, no una imagen
        // subida, para que la vitrina "Mis Logros" (LogrosVitrinaService) no
        // tenga que inventar un ícono por su cuenta.
        $iconoLogro = 'https://cdn.lordicon.com/wpsdctqb.json';

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
            [
                'name' => 'Reglamento 100% aceptado',
                'description' => 'El 100% de los trabajadores activos aceptó la versión vigente del Reglamento Interno de Trabajo.',
            ],
        ];

        foreach ($logros as $logro) {
            // updateOrCreate (no firstOrCreate): si el logro ya existía sin
            // 'image' (como en producción, sembrado antes de la vitrina de
            // logros), esto lo completa en vez de dejarlo desactualizado.
            Achievement::updateOrCreate(['name' => $logro['name']], [
                'description' => $logro['description'],
                'image' => $iconoLogro,
                'is_secret' => false,
            ]);
        }
    }
}
