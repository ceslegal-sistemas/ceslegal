<?php

namespace App\Livewire;

use App\Models\DiligenciaDescargo;
use App\Models\Feedback;
use App\Models\PreguntaDescargo;
use App\Models\RespuestaDescargo;
use App\Services\IADescargoService;
use App\Services\ActaDescargosService;
use App\Services\DocumentGeneratorService;
use App\Services\EstadoProcesoService;
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
    public array $archivosEvidencia = [];
    public bool $formularioCompletado = false;
    public bool $mostrarMensajeExito = false;
    public int $longitudMinimaRespuesta = 2;
    public bool $mostrarAdvertencia = true;
    public bool $timerIniciado = false;

    protected $listeners = ['respuestaGuardada' => 'refrescarPreguntas'];

    public bool $tiempoExpiradoMostrarEvidencias = false;

    // Feedback orgánico (preguntas inline al finalizar)
    public string $fbExperiencia     = '';  // 'muy_buena'|'buena'|'mala'|'muy_mala'
    public string $fbAlgoConfuso     = '';  // 'si'|'no'
    public string $fbConfusoDetalle  = '';
    public string $fbQueCambiaria    = '';
    public string $fbPreguntasClaras = '';  // 'si'|'no'
    public string $fbClarasDetalle   = '';
    public string $fbSinAyuda        = '';  // 'si'|'no'
    public string $fbSinAyudaDetalle = '';

    public function mount(DiligenciaDescargo $diligencia)
    {
        $this->diligencia = $diligencia;

        // Si el trabajador ya completó el formulario, evitar re-envío de notificaciones
        if ($this->diligencia->trabajador_asistio) {
            $this->formularioCompletado = true;
            $this->timerIniciado = true;
            $this->mostrarAdvertencia = false;
            return;
        }

        // Verificar si ya había iniciado previamente
        if ($this->diligencia->primer_acceso_en) {
            $this->timerIniciado = true;
            $this->mostrarAdvertencia = false;

            // Verificar si ya expiró
            if ($this->diligencia->tiempoHaExpirado()) {
                $this->diligencia->marcarTiempoExpirado();

                // Verificar si todas las preguntas están respondidas
                $preguntasSinResponder = $this->diligencia->preguntas()
                    ->activas()
                    ->whereDoesntHave('respuesta')
                    ->count();

                if ($preguntasSinResponder === 0) {
                    // Todas respondidas: mostrar pantalla de evidencias
                    $this->tiempoExpiradoMostrarEvidencias = true;
                } else {
                    // Quedan preguntas sin responder: el trabajador no completó a tiempo
                    // No se marca asistencia ni se cambia estado hasta que todas las preguntas tengan respuesta
                    $this->formularioCompletado = true;
                    session()->flash('error', 'El tiempo para completar los descargos ha expirado (45 minutos).');
                    return;
                }
            }
        }

        $this->cargarRespuestasExistentes();
    }

    /**
     * Inicia el timer después de que el usuario acepte la advertencia
     */
    public function iniciarDiligencia()
    {
        // Iniciar timer en primer acceso
        $this->diligencia->iniciarTimer();
        $this->timerIniciado = true;
        $this->mostrarAdvertencia = false;

        // Verificar si ya expiró (por si acaso)
        if ($this->diligencia->tiempoHaExpirado()) {
            $this->diligencia->marcarTiempoExpirado();
            $this->formularioCompletado = true;
            session()->flash('error', 'El tiempo para completar los descargos ha expirado (45 minutos).');
        }
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
     * Salta preguntas condicionales si la respuesta padre lo requiere
     */
    protected function saltarPreguntasCondicionales(PreguntaDescargo $pregunta, string $respuestaTexto)
    {
        // Cargar preguntas hijas si existen
        $preguntasHijas = $pregunta->preguntasHijas;

        if ($preguntasHijas->isEmpty()) {
            return; // No hay preguntas condicionales
        }

        // Verificar si la respuesta es "NO" (case insensitive)
        $esRespuestaNo = stripos($respuestaTexto, 'no') !== false &&
                        stripos($respuestaTexto, 'si') === false;

        if ($esRespuestaNo) {
            // Saltar todas las preguntas hijas automáticamente
            foreach ($preguntasHijas as $preguntaHija) {
                // Crear respuesta automática "No aplica"
                RespuestaDescargo::updateOrCreate(
                    ['pregunta_descargo_id' => $preguntaHija->id],
                    [
                        'respuesta' => 'No aplica',
                        'respondido_en' => now(),
                    ]
                );

                $preguntaHija->update(['estado' => 'respondida']);

                // Marcar como procesada en el componente
                $this->preguntasProcesadas[$preguntaHija->id] = true;
                $this->respuestas[$preguntaHija->id] = 'No aplica';
            }

            Log::info('Preguntas condicionales saltadas automáticamente', [
                'pregunta_padre_id' => $pregunta->id,
                'preguntas_hijas_ids' => $preguntasHijas->pluck('id')->toArray(),
                'respuesta_padre' => $respuestaTexto,
            ]);
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

            $respuesta = RespuestaDescargo::updateOrCreate(
                ['pregunta_descargo_id' => $preguntaId],
                [
                    'respuesta' => $respuestaTexto,
                    'respondido_en' => now(),
                ]
            );

            $pregunta->update(['estado' => 'respondida']);

            $this->preguntasProcesadas[$preguntaId] = true;

            // Si es la pregunta sobre acompañantes y respondió NO, saltar preguntas hijas automáticamente
            $this->saltarPreguntasCondicionales($pregunta, $respuestaTexto);

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
     * Elimina un archivo de la lista de evidencias
     */
    public function eliminarArchivo(int $index)
    {
        if (isset($this->archivosEvidencia[$index])) {
            unset($this->archivosEvidencia[$index]);
            $this->archivosEvidencia = array_values($this->archivosEvidencia);
        }
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
        if ($this->diligencia->tiempoHaExpirado() && !$this->tiempoExpiradoMostrarEvidencias) {
            $this->diligencia->marcarTiempoExpirado();

            // Verificar si todas las preguntas están respondidas
            $preguntasSinResponder = $this->diligencia->preguntas()
                ->activas()
                ->whereDoesntHave('respuesta')
                ->count();

            if ($preguntasSinResponder === 0) {
                // Todas respondidas: permitir subir evidencias
                $this->tiempoExpiradoMostrarEvidencias = true;
                session()->flash('info', 'El tiempo ha expirado, pero puede adjuntar evidencias antes de enviar.');
            } else {
                // Quedan preguntas sin responder: no se marca asistencia ni se cambia estado
                $this->formularioCompletado = true;
                session()->flash('error', 'El tiempo ha expirado.');
            }
        }
    }

    /**
     * Finaliza el proceso de descargos
     */
    public function finalizarDescargos()
    {
        // Guard: evitar doble ejecución si el formulario ya fue completado
        if ($this->formularioCompletado) {
            return;
        }

        $preguntasSinResponder = $this->diligencia->preguntas()
            ->activas()
            ->whereDoesntHave('respuesta')
            ->count();

        if ($preguntasSinResponder > 0) {
            $this->addError('finalizacion', 'Debe responder todas las preguntas antes de finalizar.');
            return;
        }

        try {
            // Guardar archivos de evidencia si existen
            $archivosGuardados = [];
            if (!empty($this->archivosEvidencia)) {
                foreach ($this->archivosEvidencia as $archivo) {
                    if ($archivo) {
                        try {
                            $path = $archivo->store('descargos/evidencias', 'public');
                            $archivosGuardados[] = [
                                'nombre' => $archivo->getClientOriginalName(),
                                'path' => $path,
                                'size' => $archivo->getSize(),
                                'tipo' => $archivo->getMimeType(),
                            ];
                        } catch (\Exception $e) {
                            Log::warning('Error al guardar archivo de evidencia', [
                                'archivo' => $archivo->getClientOriginalName(),
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            // Actualizar la diligencia
            $this->diligencia->update([
                'trabajador_asistio' => true,
                'fecha_diligencia' => now(),
                'archivos_evidencia' => !empty($archivosGuardados) ? $archivosGuardados : null,
            ]);

            // Cambiar estado del proceso a "descargos_realizados"
            $estadoService = app(EstadoProcesoService::class);
            $estadoService->alCompletarDescargos($this->diligencia->proceso);

        } catch (\Exception $e) {
            Log::error('Error al finalizar descargos', [
                'diligencia_id' => $this->diligencia->id,
                'error' => $e->getMessage(),
            ]);
            $this->addError('finalizacion', 'Ocurrió un error al finalizar. Por favor, intente nuevamente.');
            return;
        }

        // Operaciones exitosas: marcar como completado
        $this->formularioCompletado = true;
        $this->mostrarMensajeExito = true;
        $this->tiempoExpiradoMostrarEvidencias = false;
        $this->dispatch('descargosFinalizados');

        // Guardar feedback orgánico si el trabajador respondió al menos la primera pregunta
        $this->guardarFeedbackOrganico();

        // Notificaciones al trabajador y al cliente (no críticas)
        $this->enviarNotificacionesCompletado();

        // Generar el acta de descargos automáticamente (no crítico)
        try {
            $actaService = new ActaDescargosService();
            $resultado = $actaService->generarActaDescargos($this->diligencia);

            if ($resultado['success']) {
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
        } catch (\Exception $e) {
            Log::warning('Excepción al generar acta automáticamente', [
                'diligencia_id' => $this->diligencia->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Registra cuándo el trabajador llegó a la sección de finalizar (todas las preguntas respondidas).
     * Se llama desde la vista vía wire:init cuando el bloque de finalización se renderiza.
     */
    public function marcarPreguntasCompletadas(): void
    {
        if (!$this->diligencia->preguntas_completadas_en) {
            $this->diligencia->update(['preguntas_completadas_en' => now()]);
        }
    }

    /**
     * Guarda el feedback orgánico junto con los descargos (no bloqueante).
     */
    private function guardarFeedbackOrganico(): void
    {
        if ($this->fbExperiencia === '') {
            return; // El trabajador no respondió ninguna pregunta de feedback
        }

        try {
            $calificacionMap = ['muy_buena' => 5, 'buena' => 4, 'mala' => 2, 'muy_mala' => 1];

            $respuestasAdicionales = array_filter([
                'algo_confuso'      => $this->fbAlgoConfuso ?: null,
                'confuso_detalle'   => $this->fbConfusoDetalle ?: null,
                'preguntas_claras'  => $this->fbPreguntasClaras ?: null,
                'claras_detalle'    => $this->fbClarasDetalle ?: null,
                'sin_ayuda'         => $this->fbSinAyuda ?: null,
                'sin_ayuda_detalle' => $this->fbSinAyudaDetalle ?: null,
            ]);

            Feedback::create([
                'calificacion'             => $calificacionMap[$this->fbExperiencia] ?? null,
                'sugerencia'               => $this->fbQueCambiaria ?: null,
                'tipo'                     => 'descargo_trabajador',
                'proceso_disciplinario_id' => $this->diligencia->proceso_id,
                'diligencia_descargo_id'   => $this->diligencia->id,
                'ip_address'               => request()->ip(),
                'user_agent'               => request()->userAgent(),
                'respuestas_adicionales'   => !empty($respuestasAdicionales) ? $respuestasAdicionales : null,
            ]);
        } catch (\Exception $e) {
            Log::warning('Error al guardar feedback del trabajador', [
                'diligencia_id' => $this->diligencia->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envía notificaciones por correo al trabajador y al cliente (empresa)
     * informando que los descargos fueron completados o que el tiempo expiró.
     */
    private function enviarNotificacionesCompletado(): void
    {
        try {
            $docService = app(DocumentGeneratorService::class);
            $docService->enviarNotificacionEstadoDescargos($this->diligencia->proceso, 'descargos_realizados');
            $docService->enviarNotificacionDescargosAlCliente($this->diligencia->proceso, 'descargos_realizados');
        } catch (\Exception $e) {
            Log::warning('Error al enviar notificaciones de descargos completados', [
                'diligencia_id' => $this->diligencia->id,
                'proceso_id'    => $this->diligencia->proceso_id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

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
