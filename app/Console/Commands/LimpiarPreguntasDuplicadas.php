<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimpiarPreguntasDuplicadas extends Command
{
    protected $signature = 'ces:limpiar-preguntas-duplicadas {--diligencia= : ID específico de diligencia} {--dry-run : Solo mostrar, no borrar}';
    protected $description = 'Limpia preguntas estándar duplicadas causadas por el bug de generarPreguntasCompletas';

    public function handle(): int
    {
        // Encontrar diligencias afectadas (más de 15 preguntas)
        $afectadas = DB::table('preguntas_descargos')
            ->select('diligencia_descargo_id', DB::raw('count(*) as total'))
            ->groupBy('diligencia_descargo_id')
            ->having('total', '>', 15)
            ->when($this->option('diligencia'), fn ($q) => $q->where('diligencia_descargo_id', $this->option('diligencia')))
            ->get();

        if ($afectadas->isEmpty()) {
            $this->info('No se encontraron diligencias con preguntas duplicadas.');
            return 0;
        }

        foreach ($afectadas as $row) {
            $dId = $row->diligencia_descargo_id;
            $this->line("\n─── Diligencia ID: {$dId} — {$row->total} preguntas ───");

            // Todas las preguntas estándar ordenadas por ID (las primeras 13 son las originales)
            $estandares = DB::table('preguntas_descargos')
                ->where('diligencia_descargo_id', $dId)
                ->where('es_generada_por_ia', false)
                ->orderBy('id')
                ->pluck('id');

            $originales = $estandares->take(13);        // primeras 13 = originales
            $duplicadas  = $estandares->slice(13);       // el resto = duplicadas

            $ia = DB::table('preguntas_descargos')
                ->where('diligencia_descargo_id', $dId)
                ->where('es_generada_por_ia', true)
                ->pluck('id');

            $this->table(
                ['Tipo', 'Cantidad', 'IDs'],
                [
                    ['Estándar originales (conservar)', $originales->count(), $originales->join(', ')],
                    ['Estándar duplicadas (BORRAR)',    $duplicadas->count(),  $duplicadas->join(', ')],
                    ['IA (conservar)',                  $ia->count(),           $ia->join(', ')],
                ]
            );

            if ($duplicadas->isEmpty()) {
                $this->warn('  Sin duplicados detectados.');
                continue;
            }

            if ($this->option('dry-run')) {
                $this->warn('  [dry-run] No se borrará nada.');
                continue;
            }

            if (!$this->confirm("  ¿Borrar {$duplicadas->count()} preguntas duplicadas de la diligencia {$dId}?")) {
                $this->line('  Omitido.');
                continue;
            }

            DB::table('preguntas_descargos')->whereIn('id', $duplicadas->toArray())->delete();

            // Reordenar las preguntas restantes (orden 1..N)
            $restantes = DB::table('preguntas_descargos')
                ->where('diligencia_descargo_id', $dId)
                ->orderByRaw('es_generada_por_ia ASC, id ASC')  // estándar primero, luego IA
                ->pluck('id');

            foreach ($restantes as $pos => $preguntaId) {
                DB::table('preguntas_descargos')
                    ->where('id', $preguntaId)
                    ->update(['orden' => $pos + 1]);
            }

            $totalFinal = DB::table('preguntas_descargos')->where('diligencia_descargo_id', $dId)->count();
            $this->info("  ✓ Limpieza completada. Preguntas restantes: {$totalFinal}");
        }

        return 0;
    }
}
