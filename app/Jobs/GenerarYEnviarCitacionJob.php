<?php

namespace App\Jobs;

use App\Models\ProcesoDisciplinario;
use App\Models\User;
use App\Services\DocumentGeneratorService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Genera el PDF de la citación, crea la diligencia, genera las preguntas
 * iniciales con IA y envía el correo - EN COLA. Reemplaza la ejecución
 * síncrona que vivía en CreateProcesoDisciplinario::afterCreate(): ese flujo
 * completo (PDF + diligencia + llamada a Gemini para las preguntas + envío de
 * correo) puede tardar 15-45+ segundos, tiempo suficiente para que el
 * proxy/PHP-FPM de producción (Hostinger) corte la conexión antes de
 * responder - el mismo límite de infraestructura ya diagnosticado para "RIT
 * mejorado" y "Emitir Sanción". Sin confirmación clara, el usuario reintentaba
 * el mismo formulario creyendo que no había funcionado, generando varios
 * procesos duplicados para el mismo trabajador/incidente (caso real:
 * PD-2026-0057/0058/0059).
 *
 * Con esto, crear el proceso vuelve a ser una operación rápida (solo el
 * insert en BD); la citación/diligencia/preguntas/correo se completan después
 * en segundo plano, sin bloquear al usuario ni arriesgar el timeout.
 */
class GenerarYEnviarCitacionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** PDF + diligencia + preguntas IA + envío de correo. */
    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public readonly ProcesoDisciplinario $proceso,
        public readonly ?int $userId = null,
    ) {
        $this->onQueue('gemini');
    }

    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(DocumentGeneratorService $documentService): void
    {
        $resultado = $documentService->generarYEnviarCitacion($this->proceso);

        $user = $this->userId ? User::find($this->userId) : null;

        if ($resultado['success']) {
            $preguntasConIA   = $resultado['preguntas_ia_generadas'] ?? false;
            $formatoDocumento = $resultado['formato_documento'] ?? 'pdf';

            $mensaje = $preguntasConIA
                ? 'La citación fue enviada automáticamente con link de acceso web y preguntas generadas por IA.'
                : 'La citación fue enviada exitosamente, pero no se pudieron generar preguntas con IA. Deberá generarlas manualmente.';

            if ($formatoDocumento === 'docx') {
                $mensaje .= ' ADVERTENCIA: El documento fue enviado en formato DOCX (LibreOffice no está instalado).';
            }

            Log::info('GenerarYEnviarCitacionJob: completado', [
                'proceso_id' => $this->proceso->id,
                'preguntas_ia_generadas' => $preguntasConIA,
            ]);

            if ($user) {
                Notification::make()
                    ->success()
                    ->title('Citación enviada: ' . $this->proceso->codigo)
                    ->body($mensaje)
                    ->sendToDatabase($user);
            }

            return;
        }

        Log::error('GenerarYEnviarCitacionJob: falló el envío de la citación', [
            'proceso_id' => $this->proceso->id,
            'error'      => $resultado['message'] ?? 'desconocido',
        ]);

        if ($user) {
            Notification::make()
                ->warning()
                ->title('No se pudo enviar la citación: ' . $this->proceso->codigo)
                ->body('El proceso fue creado pero hubo un error al enviar la citación: ' . ($resultado['message'] ?? 'error desconocido'))
                ->persistent()
                ->sendToDatabase($user);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerarYEnviarCitacionJob: fallo total', [
            'proceso_id' => $this->proceso->id,
            'error'      => $e->getMessage(),
        ]);

        $user = $this->userId ? User::find($this->userId) : null;
        if ($user) {
            Notification::make()
                ->warning()
                ->title('No se pudo enviar la citación: ' . $this->proceso->codigo)
                ->body('El proceso fue creado pero hubo un error inesperado al enviar la citación automáticamente.')
                ->persistent()
                ->sendToDatabase($user);
        }
    }
}
