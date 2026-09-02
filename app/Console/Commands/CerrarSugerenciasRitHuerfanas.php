<?php

namespace App\Console\Commands;

use App\Models\ReglamentoInterno;
use App\Models\SugerenciaActualizacionRit;
use Illuminate\Console\Command;

/**
 * Limpieza de una sola vez (correr manualmente en producción después del
 * deploy del fix): cierra las SugerenciaActualizacionRit pendientes que ya
 * quedaron huérfanas ANTES de que ReglamentoInterno::desactivarActivosDe()
 * existiera - apuntan a un RIT que ya no está activo, así que aprobarlas
 * no tendría ningún efecto visible. Ver commit que agregó
 * desactivarActivosDe() para el contexto completo del bug real (empresa
 * RENBEL: el Dashboard seguía contando la sugerencia, "Mi Reglamento
 * Interno" ya no la mostraba).
 */
class CerrarSugerenciasRitHuerfanas extends Command
{
    protected $signature = 'rit:cerrar-sugerencias-huerfanas {--dry-run : Solo mostrar cuáles se cerrarían, sin modificar nada}';

    protected $description = 'Cierra sugerencias de actualización de RIT pendientes que apuntan a un RIT ya no activo';

    public function handle(): int
    {
        $huerfanas = SugerenciaActualizacionRit::where('estado', 'pendiente')
            ->whereDoesntHave('reglamentoInterno', fn ($q) => $q->where('activo', true))
            ->get();

        if ($huerfanas->isEmpty()) {
            $this->info('No hay sugerencias huérfanas pendientes.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'empresa_id', 'reglamento_interno_id'],
            $huerfanas->map(fn ($s) => [$s->id, $s->empresa_id, $s->reglamento_interno_id])->all()
        );

        if ($this->option('dry-run')) {
            $this->info("{$huerfanas->count()} sugerencia(s) se cerrarían (--dry-run, no se modificó nada).");
            return self::SUCCESS;
        }

        SugerenciaActualizacionRit::whereIn('id', $huerfanas->pluck('id'))
            ->update(['estado' => 'rechazada', 'resuelto_por' => null, 'resuelto_en' => now()]);

        $this->info("{$huerfanas->count()} sugerencia(s) huérfana(s) cerrada(s).");

        return self::SUCCESS;
    }
}
