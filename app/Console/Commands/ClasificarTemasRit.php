<?php

namespace App\Console\Commands;

use App\Models\ReglamentoInterno;
use App\Services\TemaClasificadorService;
use Illuminate\Console\Command;

class ClasificarTemasRit extends Command
{
    protected $signature = 'rit:clasificar-temas {--todos : Reclasificar incluso los que ya tienen temas_texto_hash actualizado}';

    protected $description = 'Clasifica por temas normativos los RIT activos (backfill inicial, 1 llamada IA por RIT)';

    public function handle(TemaClasificadorService $clasificador): int
    {
        $query = ReglamentoInterno::where('activo', true)->whereNotNull('texto_completo');

        if (!$this->option('todos')) {
            $query->whereNull('temas_texto_hash');
        }

        $rits = $query->get();

        if ($rits->isEmpty()) {
            $this->info('No hay RIT pendientes de clasificar.');
            return self::SUCCESS;
        }

        if (!$this->confirm("Se van a clasificar {$rits->count()} RIT (1 llamada IA cada uno). ¿Continuar?", true)) {
            $this->warn('Cancelado.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($rits->count());
        $bar->start();

        $exitosos = 0;
        $fallidos = 0;

        foreach ($rits as $rit) {
            try {
                $clasificador->asegurarTemas($rit);
                $exitosos++;
            } catch (\Throwable $e) {
                $fallidos++;
                $this->newLine();
                $this->error("RIT #{$rit->id} ({$rit->nombre}): {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Clasificados: {$exitosos}. Fallidos: {$fallidos}.");

        return self::SUCCESS;
    }
}
