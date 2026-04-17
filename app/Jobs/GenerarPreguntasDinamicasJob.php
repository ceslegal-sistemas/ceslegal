<?php

namespace App\Jobs;

use App\Models\PreguntaDescargo;
use App\Models\RespuestaDescargo;
use App\Services\IADescargoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Genera preguntas dinámicas con IA DESPUÉS de enviar la respuesta al browser.
 *
 * Se despacha con Bus::dispatchAfterResponse() para que el formulario del
 * trabajador nunca quede bloqueado esperando a Gemini.
 * Livewire hace polling cada 3s para detectar nuevas preguntas.
 */
class GenerarPreguntasDinamicasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(
        public readonly int $preguntaId,
        public readonly int $respuestaId,
    ) {}

    public function handle(): void
    {
        $pregunta  = PreguntaDescargo::find($this->preguntaId);
        $respuesta = RespuestaDescargo::find($this->respuestaId);

        if (!$pregunta || !$respuesta) {
            Log::warning('GenerarPreguntasDinamicasJob: pregunta o respuesta no encontrada', [
                'pregunta_id'  => $this->preguntaId,
                'respuesta_id' => $this->respuestaId,
            ]);
            return;
        }

        (new IADescargoService())->generarPreguntasDinamicas($pregunta, $respuesta);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerarPreguntasDinamicasJob: falló después de todos los intentos', [
            'pregunta_id' => $this->preguntaId,
            'error'       => $exception->getMessage(),
        ]);
    }
}
