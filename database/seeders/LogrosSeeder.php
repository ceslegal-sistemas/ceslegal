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
        // 'image' es una columna de cjmellor/level-up sin usar hasta ahora -
        // se reutiliza para guardar la URL del lord-icon, no una imagen
        // subida, para que la vitrina "Mis Logros" (LogrosVitrinaService) no
        // tenga que inventar un ícono por su cuenta.
        //
        // NO usar 'wpsdctqb' (usado en el resto del proyecto para "logro
        // desbloqueado") - se descubrió 2026-09-28 que ese JSON real es un
        // ícono de EMAIL ("system-regular-59-email"), nunca un trofeo; se
        // veía como un "@" genérico en "Mis Logros". Este archivo lo
        // proveyó el usuario, descargado directamente de lordicon.com y
        // verificado (nm: "doodle-motif-9-medal-first-place") antes de
        // usarlo - vive en public/lordicons/ (mismo patrón ya usado por los
        // lordicons de las páginas de error 403/404/500).
        $iconoLogro = asset('lordicons/doodle-motif-1780-medal-first-place-hover-pinch.json');

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
            // Logros agregados 2026-09-28 (pedido explícito del usuario) - ver
            // LogroSimpleService.
            [
                'name' => 'Constructor de RIT',
                'description' => 'Construyó su primer Reglamento Interno de Trabajo con el asistente de IA.',
            ],
            [
                'name' => 'Primer Otrosí',
                'description' => 'Formalizó su primera modificación contractual (Otrosí).',
            ],
            [
                'name' => 'Primera Renovación a Tiempo',
                'description' => 'Renovó un contrato de trabajo antes de que venciera su plazo.',
            ],
            [
                'name' => 'Renovador Confiable',
                'description' => 'Renovó 5 contratos de trabajo antes de que vencieran.',
            ],
            [
                'name' => 'Cero Vencimientos',
                'description' => 'Renovó 10 contratos de trabajo antes de que vencieran.',
            ],
            [
                'name' => 'Primera Actualización Aprobada',
                'description' => 'Aprobó su primera actualización sugerida del Reglamento Interno.',
            ],
            [
                'name' => 'Reglamento Actualizado',
                'description' => 'Aprobó 5 actualizaciones sugeridas del Reglamento Interno.',
            ],
            [
                'name' => 'Empresa Blindada',
                'description' => 'Aprobó 10 actualizaciones sugeridas del Reglamento Interno, manteniéndolo siempre al día.',
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
