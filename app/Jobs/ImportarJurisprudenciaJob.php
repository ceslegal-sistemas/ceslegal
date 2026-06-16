<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\JurisprudenciaScraperService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Importa una sentencia de la Corte Constitucional a la biblioteca legal.
 * Se ejecuta en cola porque la descarga + generación de embeddings es lenta.
 */
class ImportarJurisprudenciaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 2;

    public function __construct(
        public string $referencia,
        public ?int $userId = null,
    ) {}

    public function handle(JurisprudenciaScraperService $scraper): void
    {
        try {
            $doc = $scraper->importar($this->referencia);
            $this->notificar(true, "Sentencia {$doc->referencia} importada · {$doc->total_fragmentos} fragmentos.");
        } catch (\Throwable $e) {
            Log::warning('ImportarJurisprudenciaJob: error', [
                'referencia' => $this->referencia,
                'error'      => $e->getMessage(),
            ]);
            $this->notificar(false, "No se pudo importar \"{$this->referencia}\": " . $e->getMessage());
        }
    }

    private function notificar(bool $ok, string $mensaje): void
    {
        if (! $this->userId) {
            return;
        }
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $n = Notification::make()
            ->title($ok ? 'Jurisprudencia importada' : 'Error al importar jurisprudencia')
            ->body($mensaje);
        $ok ? $n->success() : $n->danger();
        $n->sendToDatabase($user);
    }
}
