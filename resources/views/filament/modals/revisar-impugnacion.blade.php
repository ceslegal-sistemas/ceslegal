<div class="space-y-6">
    {{-- Información General --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
            Información del Proceso
        </h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-500 dark:text-gray-400">Código:</span>
                <span class="font-medium text-gray-900 dark:text-gray-100 ml-2">{{ $proceso->codigo }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Estado:</span>
                <span class="font-medium text-gray-900 dark:text-gray-100 ml-2">{{ ucfirst(str_replace('_', ' ', $proceso->estado)) }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Trabajador:</span>
                <span class="font-medium text-gray-900 dark:text-gray-100 ml-2">{{ $proceso->trabajador->nombre_completo }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Cargo:</span>
                <span class="font-medium text-gray-900 dark:text-gray-100 ml-2">{{ $proceso->trabajador->cargo }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Empresa:</span>
                <span class="font-medium text-gray-900 dark:text-gray-100 ml-2">{{ $proceso->empresa->razon_social }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Sanción Original:</span>
                <span class="font-medium text-gray-900 dark:text-gray-100 ml-2">
                    @php
                        $tipoSancionTexto = match($proceso->tipo_sancion) {
                            'llamado_atencion' => 'Llamado de Atención',
                            'suspension' => 'Suspensión Laboral' . ($proceso->dias_suspension ? " ({$proceso->dias_suspension} días)" : ''),
                            'terminacion' => 'Terminación de Contrato',
                            default => ucfirst(str_replace('_', ' ', $proceso->tipo_sancion ?? 'N/A'))
                        };
                    @endphp
                    {{ $tipoSancionTexto }}
                </span>
            </div>
        </div>
    </div>

    {{-- Información de la Impugnación --}}
    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-700">
        <h3 class="text-lg font-semibold text-purple-900 dark:text-purple-100 mb-3">
            Datos de la Impugnación
        </h3>
        <div class="space-y-3">
            <div>
                <span class="text-gray-500 dark:text-gray-400 text-sm">Fecha de Impugnación:</span>
                <p class="font-medium text-gray-900 dark:text-gray-100">
                    {{ $impugnacion->fecha_impugnacion ? \Carbon\Carbon::parse($impugnacion->fecha_impugnacion)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') : 'No especificada' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Motivos de Impugnación --}}
    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 border border-yellow-200 dark:border-yellow-700">
        <h3 class="text-lg font-semibold text-yellow-900 dark:text-yellow-100 mb-3">
            Motivos de la Impugnación
        </h3>
        <div class="prose prose-sm dark:prose-invert max-w-none">
            <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $impugnacion->motivos_impugnacion ?? 'No se especificaron motivos.' }}</p>
        </div>
    </div>

    {{-- Pruebas Adicionales --}}
    @if($impugnacion->pruebas_adicionales && count($impugnacion->pruebas_adicionales) > 0)
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-700">
            <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-3">
                Pruebas Adicionales Aportadas
            </h3>
            <ul class="space-y-2">
                @foreach($impugnacion->pruebas_adicionales as $prueba)
                    <li class="flex items-center justify-between bg-white dark:bg-gray-800 p-3 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="text-gray-700 dark:text-gray-300">
                                {{ is_array($prueba) ? ($prueba['name'] ?? basename($prueba['path'] ?? $prueba)) : basename($prueba) }}
                            </span>
                        </div>
                        @php
                            $rutaArchivo = is_array($prueba) ? ($prueba['path'] ?? $prueba) : $prueba;
                        @endphp
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($rutaArchivo) }}"
                           target="_blank"
                           class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium">
                            Descargar
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
                Pruebas Adicionales
            </h3>
            <p class="text-gray-500 dark:text-gray-400 italic">
                El trabajador no aportó pruebas adicionales con la impugnación.
            </p>
        </div>
    @endif

    {{-- Análisis Previo (si existe) --}}
    @if($impugnacion->analisis_impugnacion)
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-700">
            <h3 class="text-lg font-semibold text-green-900 dark:text-green-100 mb-3">
                Análisis Previo
            </h3>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $impugnacion->analisis_impugnacion }}</p>
            </div>
            @if($impugnacion->abogado_analisis_id)
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                    Analizado por: {{ $impugnacion->abogadoAnalisis->name ?? 'N/A' }}
                    @if($impugnacion->fecha_analisis_impugnacion)
                        el {{ \Carbon\Carbon::parse($impugnacion->fecha_analisis_impugnacion)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
                    @endif
                </p>
            @endif
        </div>
    @endif
</div>
