<div class="max-w-4xl mx-auto p-6 bg-white">
    @if ($formularioCompletado)
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
            <svg class="mx-auto h-16 w-16 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h2 class="text-2xl font-bold text-green-800 mb-2">Descargos Completados</h2>
            <p class="text-green-700">Sus descargos han sido registrados exitosamente. Gracias por su participación.</p>
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
                <div>
                    <p class="text-sm text-gray-600">Cargo</p>
                    <p class="font-semibold text-gray-900">{{ $trabajador->area }}</p>
                </div>
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
                                    (Generada a partir de: "{{ Str::limit($pregunta->preguntaPadre->pregunta, 60) }}")
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
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Su respuesta (mínimo {{ $longitudMinimaRespuesta }} caracteres):
                                </label>
                                <textarea wire:model.defer="respuestas.{{ $pregunta->id }}" rows="6"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Escriba su respuesta aquí..." @if ($preguntasProcesadas[$pregunta->id]) disabled @endif></textarea>

                                @error("respuesta_{$pregunta->id}")
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                {{-- Campo para adjuntar archivos --}}
                                @if (!$preguntasProcesadas[$pregunta->id])
                                    <div class="mt-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Adjuntar evidencias (opcional):
                                        </label>
                                        <input type="file" wire:model="archivosTemporales.{{ $pregunta->id }}"
                                            multiple
                                            class="block w-full text-sm text-gray-500
                                                file:mr-4 file:py-2 file:px-4
                                                file:rounded-lg file:border-0
                                                file:text-sm file:font-semibold
                                                file:bg-blue-50 file:text-blue-700
                                                hover:file:bg-blue-100" />
                                        <p class="mt-1 text-xs text-gray-500">
                                            Puede adjuntar documentos, imágenes u otros archivos que respalden su
                                            respuesta
                                        </p>
                                        @error("archivosTemporales.{$pregunta->id}")
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif

                                <div class="mt-3 flex items-center justify-between">
                                    <span class="text-sm text-gray-500">
                                        {{ strlen($respuestas[$pregunta->id] ?? '') }} caracteres
                                    </span>
                                    <button wire:click="guardarRespuesta({{ $pregunta->id }})" type="button"
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors"
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

        {{-- Botón para finalizar --}}
        @if ($totalPreguntas > 0 && !$preguntaSiguiente)
            <div class="mt-8 bg-gray-50 border border-gray-200 rounded-lg p-6">
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

    {{-- Loading spinner --}}
    <div wire:loading class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 flex flex-col items-center">
            <svg class="animate-spin h-10 w-10 text-blue-600 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                    stroke-width="4">
                </circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
            <p class="text-gray-700 font-medium">Procesando...</p>
        </div>
    </div>
</div>

@script
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
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
                    html: 'Sus descargos han sido registrados exitosamente.<br><br>Gracias por su participación.',
                    confirmButtonText: 'Cerrar',
                    confirmButtonColor: '#10B981',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
            }, 500);
        });
    </script>
@endscript
