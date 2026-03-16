<?php

namespace App\Jobs;

use App\Models\DiligenciaDescargo;
use App\Models\PreguntaDescargo;
use App\Models\RespuestaDescargo;
use App\Services\IADescargoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerarPreguntaDinamicaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Máximo de intentos antes de declarar el job como fallido.
     * Con el backoff [10, 30, 60] eso significa 3 intentos en ≈100 segundos.
     */
    public int $tries = 3;

    /**
     * Tiempo máximo de ejecución por intento (segundos).
     */
    public int $timeout = 45;

    public function __construct(
        public readonly int $preguntaId,
        public readonly int $respuestaId,
        public readonly int $diligenciaId,
    ) {}

    /**
     * Espera entre reintentos: 10 s → 30 s → 60 s.
     * Suficiente para que Gemini se recupere de picos de demanda.
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(IADescargoService $iaService): void
    {
        $pregunta   = PreguntaDescargo::find($this->preguntaId);
        $respuesta  = RespuestaDescargo::find($this->respuestaId);
        $diligencia = DiligenciaDescargo::find($this->diligenciaId);

        if (!$pregunta || !$respuesta || !$diligencia) {
            Log::warning('GenerarPreguntaDinamicaJob: entidades no encontradas, abortando', [
                'pregunta_id'   => $this->preguntaId,
                'respuesta_id'  => $this->respuestaId,
                'diligencia_id' => $this->diligenciaId,
            ]);
            return;
        }

        // Si el trabajador ya completó el formulario no tiene sentido generar más preguntas
        if ($diligencia->trabajador_asistio) {
            return;
        }

        $iaService->generarPreguntasDinamicas($pregunta, $respuesta);
    }

    /**
     * Se ejecuta cuando el job agota todos los reintentos.
     * Genera una pregunta de banco fallback para que el formulario
     * nunca quede bloqueado esperando una respuesta de la IA.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('GenerarPreguntaDinamicaJob falló definitivamente — activando fallback', [
            'pregunta_id'   => $this->preguntaId,
            'diligencia_id' => $this->diligenciaId,
            'error'         => $e->getMessage(),
        ]);

        $diligencia = DiligenciaDescargo::find($this->diligenciaId);

        if ($diligencia && !$diligencia->trabajador_asistio) {
            app(IADescargoService::class)->generarPreguntaFallback(
                $diligencia,
                $this->preguntaId
            );
        }
    }
}
