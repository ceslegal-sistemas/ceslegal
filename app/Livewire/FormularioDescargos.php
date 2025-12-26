<?php

namespace App\Livewire;

use App\Models\DiligenciaDescargo;
use App\Models\PreguntaDescargo;
use App\Models\RespuestaDescargo;
use App\Services\IADescargoService;
use App\Services\ActaDescargosService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FormularioDescargos extends Component
{
    use WithFileUploads;

    public DiligenciaDescargo $diligencia;
    public array $respuestas = [];
    public array $preguntasProcesadas = [];
    public array $archivosTemporales = [];
    public bool $formularioCompletado = false;
    public bool $mostrarMensajeExito = false;
    public int $longitudMinimaRespuesta = 2;

    protected $listeners = ['respuestaGuardada' => 'refrescarPreguntas'];

    public function mount(DiligenciaDescargo $diligencia)
    {
        $this->diligencia = $diligencia;

        // Iniciar timer en primer acceso
        $this->diligencia->iniciarTimer();

        // Verificar si ya expiró
        if ($this->diligencia->tiempoHaExpirado()) {
            $this->diligencia->marcarTiempoExpirado();
            $this->formularioCompletado = true;
            session()->flash('error', 'El tiempo para completar los descargos ha expirado (45 minutos).');
            return;
        }

        $this->cargarRespuestasExistentes();
    }

    /**
     * Carga las respuestas ya existentes del trabajador
     */
    protected function cargarRespuestasExistentes()
    {
        $preguntas = $this->diligencia->preguntas()->with('respuesta')->get();

        foreach ($preguntas as $pregunta) {
            if ($pregunta->respuesta) {
                $this->respuestas[$pregunta->id] = $pregunta->respuesta->respuesta;
                $this->preguntasProcesadas[$pregunta->id] = true;
            } else {
                $this->respuestas[$pregunta->id] = '';
                $this->preguntasProcesadas[$pregunta->id] = false;
            }
        }
    }

    /**
     * Guarda o actualiza una respuesta y genera nuevas preguntas si es necesario
     */
    public function guardarRespuesta(int $preguntaId)
    {
        $pregunta = PreguntaDescargo::find($preguntaId);

        if (!$pregunta) {
            $this->addError("respuesta_{$preguntaId}", 'Pregunta no encontrada.');
            return;
        }

        $respuestaTexto = trim($this->respuestas[$preguntaId] ?? '');

        if (empty($respuestaTexto)) {
            $this->addError("respuesta_{$preguntaId}", 'La respuesta no puede estar vacía.');
            return;
        }

        if (strlen($respuestaTexto) < $this->longitudMinimaRespuesta) {
            $this->addError(
                "respuesta_{$preguntaId}",
                "La respuesta debe tener al menos {$this->longitudMinimaRespuesta} caracteres."
            );
            return;
        }

        try {
            if ($this->preguntasProcesadas[$preguntaId]) {
                $this->addError(
                    "respuesta_{$preguntaId}",
                    'Esta pregunta ya fue respondida y procesada.'
                );
                return;
            }

            // Guardar archivos adjuntos si existen
            $archivosGuardados = [];
            if (isset($this->archivosTemporales[$preguntaId]) && !empty($this->archivosTemporales[$preguntaId])) {
                foreach ($this->archivosTemporales[$preguntaId] as $archivo) {
                    if ($archivo) {
                        $path = $archivo->store('descargos/evidencias', 'public');
                        $archivosGuardados[] = [
                            'nombre' => $archivo->getClientOriginalName(),
                            'path' => $path,
                            'size' => $archivo->getSize(),
                            'tipo' => $archivo->getMimeType(),
                        ];
                    }
                }
            }

            $respuesta = RespuestaDescargo::updateOrCreate(
                ['pregunta_descargo_id' => $preguntaId],
                [
                    'respuesta' => $respuestaTexto,
                    'respondido_en' => now(),
                    'archivos_adjuntos' => !empty($archivosGuardados) ? $archivosGuardados : null,
                ]
            );

            $pregunta->update(['estado' => 'respondida']);

            $this->preguntasProcesadas[$preguntaId] = true;

            // Solo generar preguntas dinámicas si es una pregunta generada por IA
            // (no las preguntas estándar que tienen es_generada_por_ia = false)
            if ($pregunta->es_generada_por_ia) {
                $iaService = new IADescargoService();
                $nuevasPreguntas = $iaService->generarPreguntasDinamicas($pregunta, $respuesta);

                if (count($nuevasPreguntas) > 0) {
                    foreach ($nuevasPreguntas as $nuevaPregunta) {
                        $this->respuestas[$nuevaPregunta->id] = '';
                        $this->preguntasProcesadas[$nuevaPregunta->id] = false;
                    }

                    $this->dispatch('preguntasGeneradas', count: count($nuevasPreguntas));
                }
            }

            $this->dispatch('respuestaGuardada', preguntaId: $preguntaId);

            $this->resetErrorBag("respuesta_{$preguntaId}");

        } catch (\Exception $e) {
            Log::error('Error al guardar respuesta', [
                'pregunta_id' => $preguntaId,
                'error' => $e->getMessage(),
            ]);

            $this->addError(
                "respuesta_{$preguntaId}",
                'Ocurrió un error al guardar la respuesta. Por favor, intente nuevamente.'
            );
        }
    }

    /**
     * Refresca la lista de preguntas desde la base de datos
     */
    public function refrescarPreguntas()
    {
        $this->diligencia->refresh();
        $this->cargarRespuestasExistentes();
    }

    /**
     * Obtiene el tiempo restante en segundos
     */
    public function getTimerProperty()
    {
        return $this->diligencia->tiempoRestante() ?? 0;
    }

    /**
     * Verifica si el tiempo ha expirado (se llama con polling)
     */
    public function verificarTiempo()
    {
        if ($this->diligencia->tiempoHaExpirado()) {
            $this->diligencia->marcarTiempoExpirado();
            $this->formularioCompletado = true;
            session()->flash('error', 'El tiempo ha expirado.');
        }
    }

    /**
     * Finaliza el proceso de descargos
     */
    public function finalizarDescargos()
    {
        $preguntasSinResponder = $this->diligencia->preguntas()
            ->activas()
            ->whereDoesntHave('respuesta')
            ->count();

        if ($preguntasSinResponder > 0) {
            $this->addError('finalizacion', 'Debe responder todas las preguntas antes de finalizar.');
            return;
        }

        try {
            // Actualizar la diligencia
            $this->diligencia->update([
                'trabajador_asistio' => true,
                'fecha_diligencia' => now(),
            ]);

            // Generar el acta de descargos automáticamente
            $actaService = new ActaDescargosService();
            $resultado = $actaService->generarActaDescargos($this->diligencia);

            if ($resultado['success']) {
                Log::info('Acta de descargos generada exitosamente', [
                    'diligencia_id' => $this->diligencia->id,
                    'filename' => $resultado['filename'],
                ]);

                // Guardar referencia del archivo en la diligencia
                $this->diligencia->update([
                    'acta_generada' => true,
                    'ruta_acta' => $resultado['path'],
                ]);
            } else {
                Log::warning('No se pudo generar el acta automáticamente', [
                    'diligencia_id' => $this->diligencia->id,
                    'error' => $resultado['error'] ?? 'Error desconocido',
                ]);
            }

            $this->formularioCompletado = true;
            $this->mostrarMensajeExito = true;

            $this->dispatch('descargosFinalizados');

        } catch (\Exception $e) {
            Log::error('Error al finalizar descargos', [
                'diligencia_id' => $this->diligencia->id,
                'error' => $e->getMessage(),
            ]);

            $this->addError('finalizacion', 'Ocurrió un error al finalizar. Por favor, intente nuevamente.');
        }
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        // Mostrar solo la primera pregunta sin responder
        $preguntaSiguiente = $this->diligencia->preguntas()
            ->with('respuesta', 'preguntaPadre')
            ->activas()
            ->whereDoesntHave('respuesta')
            ->ordenadas()
            ->first();

        $proceso = $this->diligencia->proceso;
        $trabajador = $proceso->trabajador;

        // Contar progreso
        $totalPreguntas = $this->diligencia->preguntas()->count();
        $preguntasRespondidas = $this->diligencia->preguntas()->has('respuesta')->count();

        return view('livewire.formulario-descargos', [
            'preguntaSiguiente' => $preguntaSiguiente,
            'totalPreguntas' => $totalPreguntas,
            'preguntasRespondidas' => $preguntasRespondidas,
            'proceso' => $proceso,
            'trabajador' => $trabajador,
        ]);
    }
}
