<div class="max-w-4xl mx-auto p-6 bg-white">
    @if ($formularioCompletado)
        <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-8 text-center shadow-lg">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-6">
                <svg class="h-12 w-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-green-800 mb-4">Descargos Completados</h2>
            <p class="text-green-700 mb-6">Sus descargos han sido registrados exitosamente.</p>

            <div class="bg-white border border-green-200 rounded-xl p-5 text-left max-w-md mx-auto">
                <h3 class="font-semibold text-gray-800 mb-3 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    ¿Qué sigue ahora?
                </h3>
                <ul class="text-gray-600 text-sm space-y-2">
                    <li class="flex items-start">
                        <svg class="w-4 h-4 mr-2 mt-0.5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        Su caso será estudiado cuidadosamente.
                    </li>
                    <li class="flex items-start">
                        <svg class="w-4 h-4 mr-2 mt-0.5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        Se analizarán todas las evidencias y argumentos presentados.
                    </li>
                    <li class="flex items-start">
                        <svg class="w-4 h-4 mr-2 mt-0.5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        A la mayor brevedad posible se le informará el resultado de la investigación.
                    </li>
                </ul>
            </div>

            <p class="text-gray-500 text-sm mt-6">Gracias por su participación en este proceso.</p>
        </div>
    @elseif ($mostrarAdvertencia)
        {{-- Pantalla de espera mientras el usuario acepta iniciar --}}
        <div class="bg-white border border-gray-300 rounded-lg shadow-sm p-8">
            <div class="text-center mb-6">
                <svg class="mx-auto h-12 w-12 text-blue-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h2 class="text-2xl font-semibold text-gray-800 mb-2">Información Importante</h2>
            </div>

            <div class="bg-blue-50 rounded-lg p-6 mb-6 text-left border-l-4 border-blue-500">
                <p class="text-gray-800 mb-4">
                    Antes de comenzar, tenga en cuenta lo siguiente:
                </p>

                <ul class="space-y-2 text-gray-700 mb-4">
                    <li class="flex items-start">
                        <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Dispondrá de <strong>45 minutos</strong> para completar todas las preguntas</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                        <span>El cronómetro iniciará al hacer clic en el botón</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Prepare sus respuestas y evidencias con anticipación</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Mantenga esta ventana abierta durante todo el proceso</span>
                    </li>
                </ul>
            </div>

            <div class="text-center">
                <button
                    wire:click="iniciarDiligencia"
                    type="button"
                    class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium text-base rounded-lg transition-colors shadow-sm"
                    onclick="return confirm('¿Confirma que desea iniciar la diligencia? El cronómetro comenzará inmediatamente.')">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                    Iniciar Diligencia
                </button>

                <p class="text-sm text-gray-500 mt-3">
                    Puede cerrar esta ventana si necesita más tiempo para prepararse
                </p>
            </div>
        </div>
    @else
        {{-- Información del trabajador --}}
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Descargos - Proceso Disciplinario</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Trabajador</p>
                    <p class="font-semibold text-gray-900">{{ $trabajador->nombre_completo }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Cargo</p>
                    <p class="font-semibold text-gray-900">{{ $trabajador->cargo }}</p>
                </div>
                {{-- <div>
                    <p class="text-sm text-gray-600">Area</p>
                    <p class="font-semibold text-gray-900">{{ $trabajador->area }}</p>
                </div> --}}
                <div>
                    <p class="text-sm text-gray-600">Codigo del Proceso</p>
                    <p class="font-semibold text-gray-900">{{ $proceso->codigo }}</p>
                </div>
            </div>
        </div>

        {{-- Hechos del proceso --}}
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-3">Hechos del Proceso</h3>
            <p class="text-gray-700 whitespace-pre-line">{!! strip_tags($proceso->hechos) !!}</p>
        </div>

        {{-- Timer de 45 minutos --}}
        @if (!$formularioCompletado && $diligencia->primer_acceso_en)
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6" wire:poll.10s="verificarTiempo">
                <div class="flex items-center">
                    <svg class="h-6 w-6 text-yellow-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-yellow-800">
                            Tiempo restante para completar:
                            <span class="font-bold text-lg" id="timer">
                                {{ gmdate('i:s', $this->timer) }}
                            </span>
                        </p>
                        <p class="text-xs text-yellow-700">
                            Tiene 45 minutos desde su primer acceso para completar todos los descargos.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <p class="text-red-800 font-medium">{{ session('error') }}</p>
            </div>
        @endif

        {{-- Formulario de preguntas --}}
        <div class="space-y-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-800">Preguntas de Descargo</h3>
                <div class="text-sm text-gray-600 bg-gray-100 px-4 py-2 rounded-lg">
                    <span class="font-semibold text-blue-600">{{ $preguntasRespondidas }}</span> de
                    <span class="font-semibold">{{ $totalPreguntas }}</span> respondidas
                </div>
            </div>

            @if ($preguntaSiguiente)
                @php
                    $pregunta = $preguntaSiguiente;
                    $index = $preguntasRespondidas;
                @endphp
                <div class="bg-white border border-gray-300 rounded-lg p-6 shadow-sm
                            {{ $pregunta->es_generada_por_ia ? 'border-l-4 border-l-purple-500' : 'border-l-4 border-l-blue-500' }}"
                    wire:key="pregunta-{{ $pregunta->id }}">

                    {{-- Encabezado de la pregunta --}}
                    <div class="flex items-start mb-4">
                        <div class="flex-shrink-0">
                            <span
                                class="inline-flex items-center justify-center h-8 w-8 rounded-full
                                       {{ $pregunta->es_generada_por_ia ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}
                                       font-semibold text-sm">
                                {{ $index + 1 }}
                            </span>
                        </div>
                        <div class="ml-3 flex-1">
                            @if ($pregunta->es_generada_por_ia)
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 mb-2">
                                    <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z">
                                        </path>
                                    </svg>
                                    Pregunta generada por IA
                                </span>
                            @endif
                            <p class="text-lg text-gray-800 font-medium">{{ $pregunta->pregunta }}</p>

                            @if ($pregunta->preguntaPadre)
                                <p class="text-sm text-gray-500 mt-1">
                                    (Generada a partir de: "{{ Str::limit($pregunta->preguntaPadre->pregunta, 150) }}")
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Campo de respuesta --}}
                    <div class="mt-4">
                        @if ($preguntasProcesadas[$pregunta->id])
                            {{-- Respuesta ya guardada --}}
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <svg class="h-5 w-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-sm font-semibold text-green-800">Respuesta guardada</span>
                                </div>
                                <p class="text-gray-700 whitespace-pre-line">{{ $respuestas[$pregunta->id] }}</p>
                            </div>
                        @else
                            {{-- Campo para responder --}}
                            <div x-data="{ focused: false, charCount: {{ strlen($respuestas[$pregunta->id] ?? '') }} }" class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Su respuesta (mínimo {{ $longitudMinimaRespuesta }} caracteres):
                                </label>
                                <div class="relative">
                                    <textarea
                                        wire:model.defer="respuestas.{{ $pregunta->id }}"
                                        rows="6"
                                        x-on:focus="focused = true"
                                        x-on:blur="focused = false"
                                        x-on:input="charCount = $event.target.value.length"
                                        x-on:keydown.ctrl.enter="$wire.guardarRespuesta({{ $pregunta->id }})"
                                        x-on:keydown.meta.enter="$wire.guardarRespuesta({{ $pregunta->id }})"
                                        class="w-full px-4 py-3 border-2 rounded-xl transition-all duration-200 resize-none"
                                        :class="focused ? 'border-blue-500 ring-4 ring-blue-100 shadow-lg' : 'border-gray-300 hover:border-gray-400'"
                                        placeholder="Escriba su respuesta aquí..."
                                        autofocus
                                        @if ($preguntasProcesadas[$pregunta->id]) disabled @endif></textarea>

                                    {{-- Indicador de enfoque activo --}}
                                    <div x-show="focused"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         class="absolute -top-2 left-4 bg-blue-500 text-white text-xs px-2 py-0.5 rounded-full font-medium">
                                        Escribiendo...
                                    </div>
                                </div>

                                @error("respuesta_{$pregunta->id}")
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                <div class="mt-3 flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm" :class="charCount >= {{ $longitudMinimaRespuesta }} ? 'text-green-600 font-medium' : 'text-gray-500'">
                                            <span x-text="charCount">{{ strlen($respuestas[$pregunta->id] ?? '') }}</span> caracteres
                                            <span x-show="charCount >= {{ $longitudMinimaRespuesta }}" class="ml-1">&#10003;</span>
                                        </span>
                                        <span class="text-xs text-gray-400 hidden sm:inline-flex items-center gap-1">
                                            <kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded text-gray-600 font-mono text-xs">Ctrl</kbd>
                                            <span>+</span>
                                            <kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded text-gray-600 font-mono text-xs">Enter</kbd>
                                            <span class="ml-1">para guardar</span>
                                        </span>
                                    </div>
                                    <button wire:click="guardarRespuesta({{ $pregunta->id }})" type="button"
                                        class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-medium rounded-xl transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 active:translate-y-0"
                                        @if ($preguntasProcesadas[$pregunta->id]) disabled @endif>
                                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Guardar Respuesta
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                    <svg class="mx-auto h-12 w-12 text-green-500 mb-3" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-green-800 font-medium text-lg">¡Has respondido todas las preguntas!</p>
                    <p class="text-green-700 mt-2">Por favor, haz clic en "Finalizar Descargos" para completar el
                        proceso.</p>
                </div>
            @endif
        </div>

        {{-- Sección para adjuntar evidencias generales --}}
        @if ($totalPreguntas > 0 && !$preguntaSiguiente)
            <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                <div class="flex items-start mb-4">
                    <svg class="h-6 w-6 text-blue-600 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-800 mb-1">Adjuntar evidencias</h3>
                        <p class="text-sm text-gray-600 mb-3">
                            Si desea aportar documentos, imágenes u otros archivos que sustenten su versión de los hechos, puede adjuntarlos aquí.
                        </p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Archivos de evidencia (opcional):
                    </label>
                    <input type="file" wire:model="archivosEvidencia" multiple
                        class="block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-lg file:border-0
                            file:text-sm file:font-semibold
                            file:bg-blue-100 file:text-blue-700
                            hover:file:bg-blue-200" />
                    <p class="mt-2 text-xs text-gray-500">
                        Puede seleccionar múltiples archivos (documentos PDF, imágenes, videos, etc.)
                    </p>
                    @error('archivosEvidencia.*')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    @if (!empty($archivosEvidencia))
                        <div class="mt-3">
                            <p class="text-sm font-medium text-gray-700 mb-2">Archivos seleccionados:</p>
                            <ul class="space-y-1">
                                @foreach ($archivosEvidencia as $index => $archivo)
                                    <li class="text-sm text-gray-600 flex items-center">
                                        <svg class="h-4 w-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        {{ $archivo->getClientOriginalName() }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Botón para finalizar --}}
        @if ($totalPreguntas > 0 && !$preguntaSiguiente)
            <div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-6">
                @error('finalizacion')
                    <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
                        <p class="text-sm text-red-700">{{ $message }}</p>
                    </div>
                @enderror

                <button wire:click="finalizarDescargos" type="button"
                    class="w-full inline-flex justify-center items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-bold text-lg rounded-lg transition-colors">
                    <svg class="h-6 w-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Finalizar Descargos
                </button>
                <p class="text-sm text-gray-600 text-center mt-2">
                    Asegúrese de haber respondido todas las preguntas antes de finalizar
                </p>
            </div>
        @elseif ($totalPreguntas === 0)
        <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
            <svg class="mx-auto h-12 w-12 text-yellow-500 mb-3" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                </path>
            </svg>
            <p class="text-yellow-800 font-medium">No hay preguntas disponibles aún.</p>
        </div>
    @endif
    @endif

    {{-- Loading spinner para guardar respuesta --}}
    <div wire:loading.delay wire:target="guardarRespuesta"
         class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center z-50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl p-8 flex flex-col items-center shadow-2xl transform transition-all max-w-sm mx-4">
            <div class="relative mb-4">
                <div class="w-16 h-16 border-4 border-blue-200 rounded-full"></div>
                <div class="w-16 h-16 border-4 border-blue-600 rounded-full animate-spin absolute top-0 left-0 border-t-transparent"></div>
            </div>
            <p class="text-gray-800 font-semibold text-lg mb-1">Guardando respuesta</p>
            <p class="text-gray-500 text-sm text-center">Por favor espere mientras procesamos su respuesta...</p>
        </div>
    </div>

    {{-- Loading spinner para finalizar descargos --}}
    <div wire:loading.delay wire:target="finalizarDescargos"
         class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center z-50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl p-8 flex flex-col items-center shadow-2xl transform transition-all max-w-sm mx-4">
            <div class="relative mb-4">
                <div class="w-16 h-16 border-4 border-green-200 rounded-full"></div>
                <div class="w-16 h-16 border-4 border-green-600 rounded-full animate-spin absolute top-0 left-0 border-t-transparent"></div>
            </div>
            <p class="text-gray-800 font-semibold text-lg mb-1">Finalizando descargos</p>
            <p class="text-gray-500 text-sm text-center">Estamos registrando sus respuestas y evidencias...</p>
        </div>
    </div>

    {{-- Loading spinner para iniciar diligencia --}}
    <div wire:loading.delay wire:target="iniciarDiligencia"
         class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center z-50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl p-8 flex flex-col items-center shadow-2xl transform transition-all max-w-sm mx-4">
            <div class="relative mb-4">
                <div class="w-16 h-16 border-4 border-yellow-200 rounded-full"></div>
                <div class="w-16 h-16 border-4 border-yellow-500 rounded-full animate-spin absolute top-0 left-0 border-t-transparent"></div>
            </div>
            <p class="text-gray-800 font-semibold text-lg mb-1">Iniciando diligencia</p>
            <p class="text-gray-500 text-sm text-center">Preparando el formulario de descargos...</p>
        </div>
    </div>
</div>

@script
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Mostrar advertencia de inicio al cargar la página por primera vez
        document.addEventListener('DOMContentLoaded', function() {
            @if($mostrarAdvertencia && !$formularioCompletado)
                Swal.fire({
                    icon: 'warning',
                    title: '¡IMPORTANTE!',
                    html: `
                        <div class="text-left">
                            <p class="mb-3"><strong>Al hacer clic en "Iniciar Diligencia", comenzará el cronómetro de 45 minutos.</strong></p>
                            <p class="mb-3">Una vez iniciado el tiempo:</p>
                            <ul class="list-disc list-inside mb-3 text-gray-700">
                                <li>Tendrá <strong>45 minutos</strong> para completar todas las preguntas</li>
                                <li>El cronómetro NO se detendrá</li>
                                <li>Si el tiempo expira, no podrá continuar</li>
                                <li>Asegúrese de tener tiempo suficiente antes de iniciar</li>
                            </ul>
                            <p class="text-red-600 font-semibold">¿Está listo para iniciar la diligencia de descargos?</p>
                        </div>
                    `,
                    confirmButtonText: 'Sí, Iniciar Diligencia',
                    cancelButtonText: 'No, Aún No',
                    confirmButtonColor: '#DC2626',
                    cancelButtonColor: '#6B7280',
                    showCancelButton: true,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    reverseButtons: true,
                    customClass: {
                        popup: 'border-l-4 border-l-red-500'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Usuario aceptó, iniciar la diligencia
                        $wire.call('iniciarDiligencia');
                    } else {
                        // Usuario canceló, mostrar mensaje informativo
                        Swal.fire({
                            icon: 'info',
                            title: 'Diligencia No Iniciada',
                            html: 'El cronómetro no ha iniciado. Puede recargar la página cuando esté listo para comenzar.',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#3B82F6',
                            allowOutsideClick: false
                        });
                    }
                });
            @endif
        });

        $wire.on('preguntasGeneradas', (event) => {
            const count = event.count;
            Swal.fire({
                icon: 'info',
                title: 'Nuevas Preguntas Generadas',
                html: `Se han generado <strong>${count}</strong> nueva(s) pregunta(s) basadas en su respuesta.<br><br>Por favor, respóndalas a continuación.`,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3B82F6',
                customClass: {
                    popup: 'border-l-4 border-l-purple-500'
                }
            });
        });

        $wire.on('descargosFinalizados', () => {
            setTimeout(() => {
                Swal.fire({
                    icon: 'success',
                    title: '¡Descargos Completados!',
                    html: `
                        <div class="text-left">
                            <p class="mb-4 text-gray-700">Sus descargos han sido registrados exitosamente.</p>
                            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
                                <p class="text-blue-800 font-medium mb-2">
                                    <svg class="inline-block w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    ¿Qué sigue?
                                </p>
                                <ul class="text-blue-700 text-sm space-y-2 ml-6 list-disc">
                                    <li>Su caso será estudiado cuidadosamente por el área correspondiente.</li>
                                    <li>Se analizarán todas las evidencias y argumentos presentados.</li>
                                    <li>A la mayor brevedad posible se le informará el resultado de la investigación de los hechos.</li>
                                </ul>
                            </div>
                            <p class="text-gray-600 text-sm text-center">Gracias por su participación en este proceso.</p>
                        </div>
                    `,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#10B981',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    width: '500px'
                });
            }, 500);
        });
    </script>
@endscript
