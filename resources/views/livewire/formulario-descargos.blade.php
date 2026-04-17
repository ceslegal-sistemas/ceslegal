<div class="min-h-screen bg-gray-50 sm:bg-gray-100 sm:py-8 sm:px-4">
    {{-- Contenedor centrado en desktop --}}
    <div class="sm:max-w-xl sm:mx-auto">
        {{-- Card principal --}}
        <div
            class="bg-white sm:rounded-2xl sm:shadow-lg sm:border sm:border-gray-200 min-h-screen sm:min-h-0 sm:overflow-hidden">

            {{-- Header --}}
            <header class="bg-white border-b border-gray-200 sticky top-0 z-10 sm:static sm:rounded-t-2xl">
                <div class="px-4 sm:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-base font-semibold text-gray-900">Descargos</h1>
                            <p class="text-xs text-gray-500">{{ $proceso->codigo }}</p>
                        </div>
                        @if (!$formularioCompletado && !$mostrarAdvertencia && !$tiempoExpiradoMostrarEvidencias && $diligencia->primer_acceso_en)
                            <div class="flex items-center gap-2 bg-warning-50 text-warning-600 px-3 py-1.5 rounded-lg"
                                wire:poll.10s="verificarTiempo">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="font-mono text-sm font-semibold">{{ gmdate('i:s', $this->timer) }}</span>
                            </div>
                        @elseif ($tiempoExpiradoMostrarEvidencias)
                            <div class="flex items-center gap-2 bg-danger-50 text-danger-600 px-3 py-1.5 rounded-lg">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-xs font-semibold">Expirado</span>
                            </div>
                        @endif
                    </div>
                </div>
                @if (!$formularioCompletado && !$mostrarAdvertencia && !$tiempoExpiradoMostrarEvidencias)
                    <div class="px-4 sm:px-6 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 bg-gray-200 rounded-full h-2">
                                <div class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                                    style="width: {{ $totalPreguntas > 0 ? ($preguntasRespondidas / $totalPreguntas) * 100 : 0 }}%">
                                </div>
                            </div>
                            <span
                                class="text-xs font-medium text-gray-600 tabular-nums">{{ $preguntasRespondidas }}/{{ $totalPreguntas }}</span>
                        </div>
                    </div>
                @endif
            </header>

            {{-- Contenido --}}
            <main class="px-4 sm:px-6 py-6">
                @if ($tiempoExpiradoMostrarEvidencias)
                    {{-- Estado: Tiempo expirado pero puede subir evidencias --}}
                    <div class="space-y-6">
                        <div class="bg-warning-50 border border-warning-200 rounded-xl p-4">
                            <div class="flex gap-3">
                                <svg class="w-5 h-5 text-warning-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <div class="text-sm">
                                    <p class="font-semibold text-warning-800">El tiempo ha expirado</p>
                                    <p class="text-warning-700 mt-1">Sus preguntas fueron respondidas. Puede adjuntar evidencias antes de enviar sus descargos.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Sección de Evidencias --}}
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    <span class="font-medium text-gray-700">Adjuntar evidencias</span>
                                    <span class="text-xs text-gray-500">(opcional)</span>
                                </div>
                            </div>
                            <div class="p-4">
                                <p class="text-sm text-gray-600 mb-4">
                                    Puede adjuntar documentos, fotos u otros archivos que respalden sus respuestas.
                                </p>

                                <div class="space-y-3">
                                    <input
                                        type="file"
                                        wire:model="archivosEvidencia"
                                        multiple
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.xls,.xlsx"
                                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 cursor-pointer"
                                    />

                                    <p class="text-xs text-gray-500">
                                        Formatos: PDF, Word, Excel, imágenes. Máximo 10MB por archivo.
                                    </p>

                                    @if (!empty($archivosEvidencia))
                                        <div class="mt-3 space-y-2">
                                            <p class="text-xs font-medium text-gray-700">Archivos seleccionados:</p>
                                            @foreach ($archivosEvidencia as $index => $archivo)
                                                @if ($archivo)
                                                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg text-sm">
                                                        <span class="text-gray-700 truncate flex-1">{{ $archivo->getClientOriginalName() }}</span>
                                                        <button
                                                            type="button"
                                                            wire:click="eliminarArchivo({{ $index }})"
                                                            class="ml-2 text-danger-600 hover:text-danger-700">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    @error('archivosEvidencia.*')
                                        <p class="text-sm text-danger-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        @error('finalizacion')
                            <div class="p-3 bg-danger-50 border border-danger-200 rounded-xl">
                                <p class="text-sm text-danger-700">{{ $message }}</p>
                            </div>
                        @enderror

                        <button wire:click="finalizarDescargos" type="button"
                            class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-success-600 hover:bg-success-700 active:bg-success-800 text-white font-semibold rounded-xl shadow-sm transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            Enviar Descargos
                        </button>
                    </div>
                @elseif ($formularioCompletado)
                    {{-- Estado: Completado --}}
                    <div class="text-center py-8">
                        <div
                            class="mx-auto w-16 h-16 bg-success-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-success-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900 mb-2">Descargos enviados</h2>
                        <p class="text-gray-500 mb-6">Sus respuestas fueron registradas correctamente.</p>
                        <div class="bg-gray-50 rounded-xl p-4 text-left text-sm text-gray-600">
                            <p class="font-medium text-gray-900 mb-1">¿Qué sigue?</p>
                            <p>El área correspondiente revisará su caso y le notificará la decisión por correo
                                electrónico.</p>
                        </div>
                    </div>
                @elseif ($mostrarAdvertencia)
                    {{-- Estado: Antes de iniciar --}}
                    <div class="space-y-5">
                        {{-- Info del trabajador --}}
                        <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                            <div
                                class="w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $trabajador->nombre_completo }}</p>
                                <p class="text-sm text-gray-500">{{ $trabajador->cargo }}</p>
                            </div>
                        </div>

                        {{-- Advertencia --}}
                        <div class="bg-warning-50 border border-warning-200 rounded-xl p-4">
                            <div class="flex gap-3">
                                <svg class="w-5 h-5 text-warning-600 flex-shrink-0 mt-0.5" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                <div class="text-sm">
                                    <p class="font-semibold text-warning-800 mb-2">Antes de iniciar</p>
                                    <ul class="space-y-1 text-warning-700">
                                        <li>• Tendrá <strong>45 minutos</strong> para responder</li>
                                        <li>• El tiempo no se puede pausar</li>
                                        <li>• Las respuestas se guardan una por una</li>
                                        <li>• Al final de la diligencia podrá adjuntar documentos como evidencia de los
                                            hechos</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- Botón iniciar --}}
                        <button type="button" x-data
                            @click="
                                Swal.fire({
                                    title: '¿Iniciar diligencia?',
                                    text: 'El cronómetro de 45 minutos comenzará inmediatamente.',
                                    icon: 'question',
                                    showCancelButton: true,
                                    confirmButtonColor: '#4f46e5',
                                    cancelButtonColor: '#6b7280',
                                    confirmButtonText: 'Sí, iniciar',
                                    cancelButtonText: 'Cancelar',
                                    reverseButtons: true
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        $wire.iniciarDiligencia();
                                    }
                                })
                            "
                            class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white font-semibold rounded-xl shadow-sm transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"
                                    clip-rule="evenodd" />
                            </svg>
                            Iniciar Diligencia
                        </button>

                        <p class="text-xs text-center text-gray-500">
                            Si no está listo, puede cerrar esta ventana y volver después.
                        </p>
                    </div>
                @else
                    {{-- Estado: Formulario activo --}}
                    <div class="space-y-5">
                        {{-- Polling silencioso: detecta preguntas IA generadas en background --}}
                        @if ($esperandoPreguntasIA)
                            <div wire:poll.3000ms="verificarNuevasPreguntas" style="display:none"></div>
                        @endif

                        @if (session('error'))
                            <div class="bg-danger-50 border border-danger-200 rounded-xl p-4">
                                <p class="text-sm text-danger-700">{{ session('error') }}</p>
                            </div>
                        @endif

                        {{-- Hechos (colapsable) --}}
                        <details class="group">
                            <summary
                                class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Ver hechos del proceso
                                </span>
                                <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition-transform"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </summary>
                            <div class="mt-3 p-4 bg-gray-50 rounded-xl text-sm text-gray-600 leading-relaxed">
                                {!! nl2br(e(strip_tags($proceso->hechos))) !!}
                            </div>
                        </details>

                        {{-- Pregunta actual --}}
                        @if ($preguntaSiguiente)
                            @php $pregunta = $preguntaSiguiente; @endphp

                            <div class="border border-gray-200 rounded-xl overflow-hidden"
                                wire:key="pregunta-{{ $pregunta->id }}">
                                {{-- Encabezado pregunta --}}
                                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                    <div class="flex items-start gap-3">
                                        <span
                                            class="flex-shrink-0 w-8 h-8 bg-primary-600 text-white rounded-lg flex items-center justify-center text-sm font-bold">
                                            {{ $preguntasRespondidas + 1 }}
                                        </span>
                                        <div class="flex-1 min-w-0 pt-1">
                                            @if ($pregunta->es_generada_por_ia)
                                                <span
                                                    class="inline-flex items-center gap-1 text-xs text-purple-600 font-medium mb-1">
                                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path
                                                            d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" />
                                                    </svg>
                                                    Pregunta de seguimiento
                                                </span>
                                            @endif
                                            <p class="text-gray-900">{{ $pregunta->pregunta }}</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Campo respuesta --}}
                                <div class="p-4">
                                    @if (!($preguntasProcesadas[$pregunta->id] ?? false))
                                        <textarea wire:model.defer="respuestas.{{ $pregunta->id }}" rows="5"
                                            class="w-full text-base border-gray-300 rounded-xl focus:border-primary-500 focus:ring-primary-500 resize-none"
                                            placeholder="Escriba su respuesta aquí..."></textarea>

                                        @error("respuesta_{$pregunta->id}")
                                            <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                                        @enderror

                                        <button wire:click="guardarRespuesta({{ $pregunta->id }})" type="button"
                                            class="mt-4 w-full flex items-center justify-center gap-2 px-5 py-3 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white font-semibold rounded-xl shadow-sm transition-colors">
                                            Guardar y continuar
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                            </svg>
                                        </button>
                                    @else
                                        <div class="bg-success-50 border border-success-200 rounded-xl p-4">
                                            <p class="text-sm text-success-700">{{ $respuestas[$pregunta->id] }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @elseif ($feedbackPaso <= 5)
                            {{-- Preguntas de feedback: una a la vez --}}
                            <div class="border border-gray-200 rounded-xl overflow-hidden"
                                wire:key="feedback-paso-{{ $feedbackPaso }}">

                                {{-- Encabezado --}}
                                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                    <div class="flex items-start gap-3">
                                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-700 rounded-lg flex items-center justify-center text-sm font-bold">
                                            {{ $preguntasRespondidas + 1 }}
                                        </span>
                                        <div class="flex-1 min-w-0 pt-1">
                                            <span class="inline-flex items-center gap-1 text-xs text-indigo-500 font-medium mb-1">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                                </svg>
                                                Opinión sobre el proceso
                                            </span>
                                            <p class="text-gray-900">
                                                @if($feedbackPaso === 1) ¿Cómo fue su experiencia usando la aplicación?
                                                @elseif($feedbackPaso === 2) ¿Encontró algo confuso durante el proceso?
                                                @elseif($feedbackPaso === 3) ¿Qué cambiaría o mejoraría?
                                                @elseif($feedbackPaso === 4) ¿Las preguntas del formulario fueron claras?
                                                @elseif($feedbackPaso === 5) ¿Pudo completar el proceso sin ayuda?
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Campo de respuesta --}}
                                <div class="p-4">
                                    @if($feedbackPaso === 1)
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach(['muy_buena' => 'Muy buena', 'buena' => 'Buena', 'mala' => 'Mala', 'muy_mala' => 'Muy mala'] as $val => $lbl)
                                                <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors
                                                    {{ $fbExperiencia === $val ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50' }}">
                                                    <input type="radio" wire:model.live="fbExperiencia" value="{{ $val }}" class="text-indigo-600 border-gray-300">
                                                    <span class="text-sm text-gray-700">{{ $lbl }}</span>
                                                </label>
                                            @endforeach
                                        </div>

                                    @elseif($feedbackPaso === 2)
                                        <div class="flex gap-3">
                                            @foreach(['si' => 'Sí', 'no' => 'No'] as $val => $lbl)
                                                <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors flex-1
                                                    {{ $fbAlgoConfuso === $val ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50' }}">
                                                    <input type="radio" wire:model.live="fbAlgoConfuso" value="{{ $val }}" class="text-indigo-600 border-gray-300">
                                                    <span class="text-sm text-gray-700">{{ $lbl }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if($fbAlgoConfuso === 'si')
                                            <textarea wire:model="fbConfusoDetalle" rows="3"
                                                class="mt-3 w-full text-base border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-indigo-500 resize-none"
                                                placeholder="¿Qué encontró confuso? Descríbalo aquí..."></textarea>
                                        @endif

                                    @elseif($feedbackPaso === 3)
                                        <textarea wire:model="fbQueCambiaria" rows="4"
                                            class="w-full text-base border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-indigo-500 resize-none"
                                            placeholder="Escriba aquí sus sugerencias... (opcional)"></textarea>

                                    @elseif($feedbackPaso === 4)
                                        <div class="flex gap-3">
                                            @foreach(['si' => 'Sí', 'no' => 'No'] as $val => $lbl)
                                                <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors flex-1
                                                    {{ $fbPreguntasClaras === $val ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50' }}">
                                                    <input type="radio" wire:model.live="fbPreguntasClaras" value="{{ $val }}" class="text-indigo-600 border-gray-300">
                                                    <span class="text-sm text-gray-700">{{ $lbl }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if($fbPreguntasClaras === 'no')
                                            <textarea wire:model="fbClarasDetalle" rows="3"
                                                class="mt-3 w-full text-base border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-indigo-500 resize-none"
                                                placeholder="¿Cuál pregunta no fue clara? ¿Por qué?"></textarea>
                                        @endif

                                    @elseif($feedbackPaso === 5)
                                        <div class="flex gap-3">
                                            @foreach(['si' => 'Sí', 'no' => 'No'] as $val => $lbl)
                                                <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors flex-1
                                                    {{ $fbSinAyuda === $val ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50' }}">
                                                    <input type="radio" wire:model.live="fbSinAyuda" value="{{ $val }}" class="text-indigo-600 border-gray-300">
                                                    <span class="text-sm text-gray-700">{{ $lbl }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if($fbSinAyuda === 'no')
                                            <textarea wire:model="fbSinAyudaDetalle" rows="3"
                                                class="mt-3 w-full text-base border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-indigo-500 resize-none"
                                                placeholder="¿Qué dificultad tuvo? ¿Quién le ayudó?"></textarea>
                                        @endif
                                    @endif

                                    @error('fb')
                                        <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                                    @enderror

                                    <button wire:click="avanzarFeedback" type="button"
                                        class="mt-4 w-full flex items-center justify-center gap-2 px-5 py-3 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white font-semibold rounded-xl shadow-sm transition-colors">
                                        Guardar y continuar
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @else
                            {{-- Feedback completado: Evidencias + Finalizar --}}
                            <div class="space-y-6">
                                <div class="text-center">
                                    <div class="mx-auto w-14 h-14 bg-success-100 rounded-full flex items-center justify-center mb-4">
                                        <svg class="w-7 h-7 text-success-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-1">¡Listo!</h3>
                                    <p class="text-sm text-gray-500">Puede adjuntar evidencias y enviar sus descargos</p>
                                </div>

                                {{-- Evidencias --}}
                                <div class="border border-gray-200 rounded-xl overflow-hidden">
                                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                            </svg>
                                            <span class="font-medium text-gray-700">Adjuntar evidencias</span>
                                            <span class="text-xs text-gray-500">(opcional)</span>
                                        </div>
                                    </div>
                                    <div class="p-4">
                                        <p class="text-sm text-gray-600 mb-4">
                                            Puede adjuntar documentos, fotos u otros archivos que respalden sus respuestas.
                                        </p>
                                        <div class="space-y-3">
                                            <input type="file" wire:model="archivosEvidencia" multiple
                                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.xls,.xlsx"
                                                class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 cursor-pointer" />
                                            <p class="text-xs text-gray-500">Formatos: PDF, Word, Excel, imágenes. Máximo 10MB por archivo.</p>
                                            @if (!empty($archivosEvidencia))
                                                <div class="mt-3 space-y-2">
                                                    <p class="text-xs font-medium text-gray-700">Archivos seleccionados:</p>
                                                    @foreach ($archivosEvidencia as $index => $archivo)
                                                        @if ($archivo)
                                                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg text-sm">
                                                                <span class="text-gray-700 truncate flex-1">{{ $archivo->getClientOriginalName() }}</span>
                                                                <button type="button" wire:click="eliminarArchivo({{ $index }})"
                                                                    class="ml-2 text-danger-600 hover:text-danger-700">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                            @error('archivosEvidencia.*')
                                                <p class="text-sm text-danger-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                @error('finalizacion')
                                    <div class="p-3 bg-danger-50 border border-danger-200 rounded-xl">
                                        <p class="text-sm text-danger-700">{{ $message }}</p>
                                    </div>
                                @enderror

                                <button wire:click="finalizarDescargos" type="button"
                                    class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-success-600 hover:bg-success-700 active:bg-success-800 text-white font-semibold rounded-xl shadow-sm transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    Enviar Descargos
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </main>
        </div>
    </div>

    {{-- Loading --}}
    <div wire:loading.delay wire:target="guardarRespuesta, finalizarDescargos, iniciarDiligencia, archivosEvidencia"
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl p-5 flex items-center gap-4 mx-4">
            <svg class="animate-spin h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                    stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
            <span class="font-medium text-gray-700">Procesando...</span>
        </div>
    </div>

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('descargosFinalizados', () => {
                // El feedback modal se maneja por Livewire
            });
        });
    </script>
</div>
