{{--
    Evidencias aportadas por la empresa, visibles para el TRABAJADOR durante
    toda la diligencia de descargos.

    Derecho de contradicción: no se le puede exigir que se defienda de unas
    pruebas que nunca vio. Antes el formulario solo le permitía SUBIR las
    suyas; las del empleador no se le mostraban en ninguna parte.

    Parámetros:
      $evidenciasEmpleador  array de ['nombre','url','extension','esImagen']
      $colapsable           si true, arranca plegado (para no empujar el
                            formulario de preguntas hacia abajo)
--}}
@php
    $colapsable = $colapsable ?? true;
@endphp

@if (!empty($evidenciasEmpleador))
    <div x-data="{ abierto: {{ $colapsable ? 'false' : 'true' }} }"
         class="border border-gray-200 rounded-xl overflow-hidden">

        <button type="button"
                @click="abierto = !abierto"
                class="w-full flex items-center justify-between gap-3 px-4 py-3 bg-primary-50/60 border-b border-gray-200 text-left">
            <span class="flex items-center gap-2 min-w-0">
                <svg class="w-4 h-4 text-primary-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-gray-900">
                        Pruebas aportadas por la empresa ({{ count($evidenciasEmpleador) }})
                    </span>
                    <span class="block text-xs text-gray-500">
                        Revíselas antes de responder. Son las pruebas sobre las que debe pronunciarse.
                    </span>
                </span>
            </span>
            <svg class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform"
                 :class="abierto ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="abierto" x-collapse class="p-4 space-y-3">
            @foreach ($evidenciasEmpleador as $evidencia)
                <div class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl">
                    @if ($evidencia['esImagen'])
                        <a href="{{ $evidencia['url'] }}" target="_blank" rel="noopener"
                           class="flex-shrink-0 w-14 h-14 rounded-lg overflow-hidden border border-gray-200 bg-gray-50">
                            <img src="{{ $evidencia['url'] }}" alt="{{ $evidencia['nombre'] }}"
                                 class="w-full h-full object-cover" loading="lazy">
                        </a>
                    @else
                        <div class="flex-shrink-0 w-14 h-14 rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center">
                            <span class="text-[10px] font-bold uppercase text-gray-500">
                                {{ $evidencia['extension'] ?: 'archivo' }}
                            </span>
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $evidencia['nombre'] }}</p>
                        <a href="{{ $evidencia['url'] }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-700 mt-0.5">
                            Abrir en una pestaña nueva
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                            </svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
