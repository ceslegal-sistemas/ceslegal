<?php

namespace App\Console\Commands;

use App\Models\ReglamentoInterno;
use App\Services\RitBloqueEmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Genera de una vez los bloques+embeddings de todos los RIT activos que
 * todavía no los tienen (bloques_texto_hash null) - correr esto ANTES de
 * habilitar RIT_FILTRO_IMPACTO_HABILITADO=true en producción, en horario de
 * bajo tráfico. Sin este paso, el primer DocumentoLegal procesado dispara la
 * misma regeneración completa de golpe, dentro del ciclo de vida síncrono
 * del Observer/job que lo procesó - ver el comentario en
 * DocumentoLegalObserver.php y config/ces.php.
 */
class PrecalentarBloquesRit extends Command
{
    protected $signature = 'rit:precalentar-bloques
        {--todos : Por defecto solo procesa RIT sin bloques_texto_hash. Con esta opción, regenera los bloques de TODOS los RIT activos, incluso los que ya tienen bloques_texto_hash al día.}';

    protected $description = 'Precalienta los bloques+embeddings de los RIT activos, antes de habilitar el filtro de impacto de Biblioteca Legal.';

    public function handle(RitBloqueEmbeddingService $service): int
    {
        $query = ReglamentoInterno::where('activo', true);

        if (!$this->option('todos')) {
            $query->whereNull('bloques_texto_hash');
        }

        $rits = $query->get();

        if ($rits->isEmpty()) {
            $this->info('No hay RIT activos pendientes de precalentar (use --todos para forzar la regeneración de todos).');
            return self::SUCCESS;
        }

        $this->info("Precalentando bloques de {$rits->count()} RIT activos...");
        $this->warn('Esto hace llamadas REALES a la API de embeddings de Gemini, una por bloque - puede tardar y consumir cuota real.');

        if (!$this->confirm('¿Continuar?')) {
            $this->comment('Cancelado.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($rits->count());
        foreach ($rits as $rit) {
            try {
                $service->asegurarBloques($rit);
            } catch (\Throwable $e) {
                // No debe abortar el lote completo por la falla de un solo RIT -
                // se loguea y se sigue con los demás, revisando el resumen final.
                Log::warning('rit:precalentar-bloques: fallo al precalentar un RIT, se continúa con los demás', [
                    'reglamento_interno_id' => $rit->id,
                    'error' => $e->getMessage(),
                ]);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $sinExito = ReglamentoInterno::where('activo', true)->whereNull('bloques_texto_hash')->count();
        if ($sinExito > 0) {
            $this->warn("{$sinExito} RIT no lograron generar ningún embedding (falla de API) - revisar storage/logs/laravel.log y volver a correr este comando.");
        } else {
            $this->info('Todos los RIT activos quedaron con sus bloques al día.');
        }

        return self::SUCCESS;
    }
}
