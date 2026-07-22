<?php

namespace App\Jobs;

use App\Models\DocumentoLegal;
use App\Services\BibliotecaLegalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

class ProcesarBibliotecaLegal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Extracción de texto + fragmentación + embeddings de un solo documento.
     *  Documentos grandes (ej. una ley completa) pueden generar 100+ fragmentos,
     *  cada uno con su propia llamada de embedding - mismo margen que la auditoría de RIT. */
    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly DocumentoLegal $documento,
    ) {
        // Misma cola que la auditoría de RIT - comparten el mismo throttle de Gemini.
        $this->onQueue('gemini');
    }

    /** Throttle global compartido con el resto de llamadas a Gemini. */
    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(BibliotecaLegalService $biblioteca): void
    {
        // procesarDocumento() ya deja el registro en 'procesado' o 'error' con su mensaje.
        $biblioteca->procesarDocumento($this->documento);
    }

    public function failed(\Throwable $e): void
    {
        $this->documento->update([
            'estado'        => 'error',
            'error_mensaje' => $e->getMessage(),
        ]);
    }
}
