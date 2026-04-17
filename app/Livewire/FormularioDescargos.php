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

    // Autenticación 2FA
    public string $etapa = 'otp';
    public string $otpCodigo = '';
    public string $otpError = '';
    public bool   $otpEnviado = false;
    public bool   $disclaimerAceptado = false;

    protected $listeners = ['respuestaGuardada' => 'refrescarPreguntas'];

    public bool $tiempoExpiradoMostrarEvidencias = false;

    // Feedback orgánico — paso actual (1-5 = pregunta activa, 6 = completado)
    public int    $feedbackPaso      = 1;
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

        // Si el trabajador ya completó el formulario
        if ($this->diligencia->trabajador_asistio) {
            $this->formularioCompletado = true;
            $this->mostrarAdvertencia = false;
            $this->etapa = 'completado';
            return;
        }

        // Backward compat: diligencia previa al flujo v2 (tiene primer_acceso_en pero sin OTP)
        if ($this->diligencia->primer_acceso_en && !$this->diligencia->otp_verificado_en) {
            $this->etapa = 'formulario';
            $this->mostrarAdvertencia = false;

            if ($this->diligencia->tiempoHaExpirado()) {
                $this->diligencia->marcarTiempoExpirado();

                $preguntasSinResponder = $this->diligencia->preguntas()
                    ->activas()
                    ->whereDoesntHave('respuesta')
                    ->count();

                if ($preguntasSinResponder === 0) {
                    $this->tiempoExpiradoMostrarEvidencias = true;
                } else {
                    $this->formularioCompletado = true;
                    session()->flash('error', 'La fecha de la diligencia ya pasó. Contacte al administrador del proceso.');
                    return;
                }
            }

            $this->cargarRespuestasExistentes();
            return;
        }

        // Máquina de estados v2
        if (!$this->diligencia->otp_verificado_en) {
            $this->etapa = 'otp';
        } elseif (!$this->diligencia->disclaimer_aceptado_en) {
            $this->etapa = 'disclaimer';
        } elseif (!$this->diligencia->foto_inicio_path) {
            $this->etapa = 'foto_inicio';
        } elseif ($this->diligencia->foto_fin_path) {
            $this->etapa = 'completado';
            $this->formularioCompletado = true;
        } else {
            $this->etapa = 'formulario';
            $this->mostrarAdvertencia = false;

            if ($this->diligencia->tiempoHaExpirado()) {
                $this->diligencia->marcarTiempoExpirado();

                $preguntasSinResponder = $this->diligencia->preguntas()
                    ->activas()
                    ->whereDoesntHave('respuesta')
                    ->count();

                if ($preguntasSinResponder === 0) {
                    $this->tiempoExpiradoMostrarEvidencias = true;
                } else {
                    $this->formularioCompletado = true;
                    session()->flash('error', 'La fecha de la diligencia ya pasó. Contacte al administrador del proceso.');
                    return;
                }
            }
        }

        $this->cargarRespuestasExistentes();

        // Si la foto de cierre ya fue tomada, verificar auto-completado al cargar
        if ($this->diligencia->foto_fin_path) {
            $this->verificarAutoCompletado();
        }
    }

    // ─── Métodos de autenticación v2 ──────────────────────────────────────

    public function enviarOtp(): void
    {
        $this->otpError = '';

        if (!$this->diligencia->emailTrabajador()) {
            $this->otpError = 'sin_email';
            return;
        }

        $enviado = $this->diligencia->enviarOtp();

        if ($enviado) {
            $this->otpEnviado = true;
        } else {
            $this->otpError = 'Error al enviar el código. Intente de nuevo.';
        }
    }

    public function verificarOtp(): void
    {
        $this->otpError = '';

        if ($this->diligencia->otpBloqueado()) {
            $this->otpError = 'bloqueado';
            return;
        }

        if (!$this->diligencia->otpEsValido()) {
            $this->otpError = 'expirado';
            return;
        }

        $this->diligencia->refresh();

        if ($this->diligencia->verificarOtp(trim($this->otpCodigo))) {
            $this->etapa = 'disclaimer';
        } else {
            $this->diligencia->refresh();
            if ($this->diligencia->otpBloqueado()) {
                $this->otpError = 'bloqueado';
            } else {
                $this->otpError = 'incorrecto';
            }
        }
    }

    public function reenviarOtp(): void
    {
        if (!$this->diligencia->puedeReenviar()) {
            return;
        }
        $this->enviarOtp();
    }

    public function aceptarDisclaimer(): void
    {
        $this->diligencia->update([
            'disclaimer_aceptado_en' => now(),
            'disclaimer_ip'          => request()->ip(),
        ]);
        $this->etapa = 'foto_inicio';
    }

    public function guardarFotoInicio(string $base64): void
    {
        $path = $this->guardarFotoBase64($base64, 'inicio');

        $this->diligencia->update([
            'foto_inicio_path' => $path,
            'foto_inicio_en'   => now(),
        ]);

        $this->actualizarEvidenciaMetadata();
        $this->iniciarDiligencia();
        $this->etapa = 'formulario';
        $this->mostrarAdvertencia = true; // muestra la advertencia de 45 min
    }

    public function guardarFotoFin(string $base64): void
    {
        $path = $this->guardarFotoBase64($base64, 'fin');

        $this->diligencia->update([
            'foto_fin_path' => $path,
            'foto_fin_en'   => now(),
        ]);

        $this->actualizarEvidenciaMetadata();

        // Regresar al formulario para adjuntar evidencias y enviar
        // (NO finalizar aquí — el trabajador puede adjuntar archivos antes)
        $this->etapa = 'formulario';
    }

    private function guardarFotoBase64(string $base64, string $nombre): string
    {
        // Eliminar prefijo data URI si existe
        $datos = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        $imagen = base64_decode($datos);

        $ruta = "private/fotos-descargos/{$this->diligencia->id}/{$nombre}.jpg";
        Storage::put($ruta, $imagen);

        return $ruta;
    }

    private function actualizarEvidenciaMetadata(): void
    {
        $this->diligencia->refresh();
        $meta = $this->diligencia->evidencia_metadata ?? [];

        $meta = array_merge($meta, array_filter([
            'ip'           => request()->ip(),
            'user_agent'   => request()->userAgent(),
            'otp_canal'    => $this->diligencia->otp_canal,
            'foto_inicio_en' => $this->diligencia->foto_inicio_en?->toISOString(),
            'foto_fin_en'    => $this->diligencia->foto_fin_en?->toISOString(),
        ]));

        $this->diligencia->update(['evidencia_metadata' => $meta]);
    }

    /**
     * El trabajador confirma que está listo para iniciar la diligencia
     */
    public function iniciarDiligencia()
    {
        $this->diligencia->iniciarTimer();
        $this->mostrarAdvertencia = false;
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

            // Generar preguntas dinámicas para preguntas IA y para estándar relevantes
            // (excluir preguntas puramente administrativas que no aportan contexto disciplinario)
            $preguntasAdministrativas = [
                '¿Para qué empresa trabaja usted?',
                '¿Cuál es su cargo en la empresa?',
                '¿Quién es su jefe directo?',
                '¿Va a asistir acompañado(a) por alguien?',
                '¿Qué relación tiene esa persona con usted?',
                'Acompañante: indique su nombre completo y en qué calidad asiste a esta diligencia (apoyo moral, representante sindical, apoderado, testigo u otro).',
                '¿Cuál es el nombre de esa empresa contratista o tercero?',
            ];

            // Prefijos para preguntas administrativas cuyo texto varía (contienen nombre de empresa)
            $prefijosAdministrativos = [
                '¿Trabaja usted para una empresa contratista o tercero diferente a',
            ];

            $esPreguntaAdministrativa = in_array($pregunta->pregunta, $preguntasAdministrativas)
                || collect($prefijosAdministrativos)->contains(
                    fn($p) => str_starts_with($pregunta->pregunta, $p)
                );

            $esPreguntaRelevante = $pregunta->es_generada_por_ia || !$esPreguntaAdministrativa;

            if ($esPreguntaRelevante) {
                // Llamada sincrónica: el circuit breaker en llamarGemini() garantiza que
                // si Gemini está caído, falla en ~0ms en lugar de bloquear 7+ segundos.
                $iaService = new IADescargoService();
                $nuevasPreguntas = $iaService->generarPreguntasDinamicas($pregunta, $respuesta);
                foreach ($nuevasPreguntas as $nuevaPregunta) {
                    $this->respuestas[$nuevaPregunta->id] = '';
                    $this->preguntasProcesadas[$nuevaPregunta->id] = false;
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
     * Registra cuándo el trabajador llegó a la sección de feedback.
     */
    public function marcarPreguntasCompletadas(): void
    {
        if (!$this->diligencia->preguntas_completadas_en) {
            $this->diligencia->update(['preguntas_completadas_en' => now()]);
        }
    }

    /**
     * Avanza al siguiente paso del feedback.
     * Valida el campo requerido del paso actual antes de avanzar.
     */
    public function avanzarFeedback(): void
    {
        // Registrar que llegó al punto de finalizar (solo la primera vez)
        if ($this->feedbackPaso === 1) {
            $this->marcarPreguntasCompletadas();
        }

        // Validar campo requerido del paso actual
        $requiereSeleccion = [1 => 'fbExperiencia', 2 => 'fbAlgoConfuso', 4 => 'fbPreguntasClaras', 5 => 'fbSinAyuda'];

        if (isset($requiereSeleccion[$this->feedbackPaso]) && $this->{$requiereSeleccion[$this->feedbackPaso]} === '') {
            $this->addError('fb', 'Por favor seleccione una opción para continuar.');
            return;
        }

        // Paso 3: sugerencia de texto obligatoria
        if ($this->feedbackPaso === 3 && trim($this->fbQueCambiaria) === '') {
            $this->addError('fb', 'Por favor escriba su sugerencia para continuar.');
            return;
        }

        $this->resetErrorBag('fb');
        $this->feedbackPaso++;

        // Al completar el feedback (paso 6+), solicitar foto de cierre antes de adjuntar
        if ($this->feedbackPaso > 5 && $this->diligencia->otp_verificado_en && !$this->diligencia->foto_fin_path) {
            $this->etapa = 'foto_fin';
        }
    }

    /**
     * Auto-completa los descargos si la foto de cierre fue tomada hace más de 10 min
     * y el trabajador no envió manualmente. Se llama por polling y en mount().
     */
    public function verificarAutoCompletado(): void
    {
        if ($this->formularioCompletado || $this->diligencia->trabajador_asistio) {
            return;
        }

        if (!$this->diligencia->foto_fin_en) {
            return;
        }

        if ($this->diligencia->foto_fin_en->diffInMinutes(now()) < 10) {
            return;
        }

        $this->finalizarDescargos();
    }

    /**
     * Guarda el feedback orgánico junto con los descargos (no bloqueante).
     */
    private function guardarFeedbackOrganico(): void
    {
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

        // Contar progreso incluyendo las 5 preguntas de feedback
        $totalPreguntas       = $this->diligencia->preguntas()->count();
        $preguntasRespondidas = $this->diligencia->preguntas()->has('respuesta')->count();
        $totalConFeedback     = $totalPreguntas + 5;
        $respondidosConFeedback = $preguntasRespondidas + ($this->feedbackPaso - 1);

        return view('livewire.formulario-descargos', [
            'preguntaSiguiente'     => $preguntaSiguiente,
            'totalPreguntas'        => $totalConFeedback,
            'preguntasRespondidas'  => $respondidosConFeedback,
            'proceso'               => $proceso,
            'trabajador'            => $trabajador,
        ]);
    }
}
