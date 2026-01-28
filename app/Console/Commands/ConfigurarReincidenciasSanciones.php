<?php

namespace App\Console\Commands;

use App\Models\SancionLaboral;
use Illuminate\Console\Command;

class ConfigurarReincidenciasSanciones extends Command
{
    protected $signature = 'sanciones:configurar-reincidencias';

    protected $description = 'Configura automáticamente las relaciones de reincidencia en sanciones laborales basándose en los patrones del nombre';

    public function handle()
    {
        $this->info('Configurando reincidencias de sanciones laborales...');

        // Patrones de secuencia
        $patrones = [
            '(1ra vez)' => 1,
            '(2da vez)' => 2,
            '(3ra vez)' => 3,
            '(4ta vez)' => 4,
        ];

        $sanciones = SancionLaboral::all();
        $grupos = [];

        // Agrupar sanciones por nombre base
        foreach ($sanciones as $sancion) {
            $nombreBase = $sancion->nombre_claro;
            $ordenReincidencia = null;

            foreach ($patrones as $patron => $orden) {
                if (str_contains($sancion->nombre_claro, $patron)) {
                    $nombreBase = trim(str_replace($patron, '', $sancion->nombre_claro));
                    $ordenReincidencia = $orden;
                    break;
                }
            }

            if ($ordenReincidencia !== null) {
                $grupos[$nombreBase][$ordenReincidencia] = $sancion;
            }
        }

        $actualizadas = 0;

        // Procesar cada grupo
        foreach ($grupos as $nombreBase => $secuencia) {
            ksort($secuencia); // Ordenar por número de vez

            $primeraVez = $secuencia[1] ?? null;

            if (!$primeraVez) {
                $this->warn("No se encontró '1ra vez' para: {$nombreBase}");
                continue;
            }

            // Configurar la primera vez
            $primeraVez->update([
                'sancion_padre_id' => null,
                'orden_reincidencia' => 1,
            ]);
            $actualizadas++;
            $this->line("  [1ra vez] {$primeraVez->nombre_claro}");

            // Configurar las reincidencias
            foreach ($secuencia as $orden => $sancion) {
                if ($orden === 1) continue;

                $sancion->update([
                    'sancion_padre_id' => $primeraVez->id,
                    'orden_reincidencia' => $orden,
                ]);
                $actualizadas++;
                $this->line("    [{$orden}da/ra vez] {$sancion->nombre_claro} -> padre: {$primeraVez->id}");
            }
        }

        $this->newLine();
        $this->info("Se configuraron {$actualizadas} sanciones con reincidencia.");
        $this->info("Grupos encontrados: " . count($grupos));

        return Command::SUCCESS;
    }
}
