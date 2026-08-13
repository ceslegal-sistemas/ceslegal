<?php

namespace App\Jobs;

use App\Models\ReglamentoInterno;
use App\Services\ReglamentoInternoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

/**
 * Corre en segundo plano la extracción de faltas + sanción por gravedad
 * (leve/grave/muy grave) del texto del RIT, para que quede lista sin que el
 * cliente tenga que pedirla con un botón manual ("Re-extraer sanciones").
 * Se dispara: (a) justo después de subir un RIT (MiReglamentoInterno::subirRITAction),
 * y (b) como respaldo perezoso al abrir "Mi Reglamento Interno" si un RIT ya
 * existente todavía no tiene sanciones_extraidas (dato legado o extracción
 * fallida). El botón manual sigue existiendo, pero solo visible para
 * super_admin, como herramienta de reintento.
 */
class ExtraerSancionesRITJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 1;

    public function __construct(
        public readonly ReglamentoInterno $rit,
    ) {
        $this->onQueue('gemini');
    }

    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(ReglamentoInternoService $service): void
    {
        // Ya se extrajo mientras el job esperaba en cola (ej. el cliente hizo
        // clic manual) - no repetir la llamada a la IA.
        if (!empty($this->rit->fresh()?->sanciones_extraidas)) {
            return;
        }

        $service->extraerYPersistirSanciones($this->rit);
    }
}
