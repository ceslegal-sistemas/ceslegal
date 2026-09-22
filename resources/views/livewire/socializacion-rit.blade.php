@php
    $pasos = ['documento' => 1, 'datos' => 2, 'foto' => 3, 'presentacion_rit' => 4, 'aceptacion' => 5];
    $pasoActual = $pasos[$etapa] ?? null;
@endphp

<div class="min-h-screen bg-gray-50 sm:bg-gray-100 sm:py-8 sm:px-4">
    @include('filament.components.lupe-hero-styles')
    <div class="sm:max-w-xl sm:mx-auto">
        <div class="bg-white sm:rounded-2xl sm:shadow-lg sm:border sm:border-gray-200 min-h-screen sm:min-h-0 sm:overflow-hidden">
            <header class="bg-white border-b border-gray-200 sticky top-0 z-10 sm:static sm:rounded-t-2xl">
                <div class="px-4 sm:px-6 py-4">
                    <h1 class="text-base font-semibold text-gray-900">Reglamento Interno de Trabajo</h1>
                    <p class="text-xs text-gray-500">{{ $empresa->razon_social }}</p>
                </div>
                @if ($pasoActual)
                    <div class="px-4 sm:px-6 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 bg-gray-200 rounded-full h-2">
                                <div class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                                    style="width: {{ ($pasoActual / count($pasos)) * 100 }}%">
                                </div>
                            </div>
                            <span class="text-xs font-medium text-gray-600 tabular-nums">{{ $pasoActual }}/{{ count($pasos) }}</span>
                        </div>
                    </div>
                @endif
            </header>

            <main class="px-4 sm:px-6 py-6">
                @if ($etapa === 'sin_rit')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-none">Sin Reglamento</span>
                            <h1 class="rit-title">Esta empresa aún no tiene un Reglamento Interno para socializar</h1>
                        </div>
                    </div>
                @elseif ($etapa === 'ya_acepto')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-sub">
                                <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold" colors="primary:#86efac,secondary:#86efac" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
                                Ya registrado
                            </span>
                            <h1 class="rit-title">Ya aceptaste el Reglamento Interno vigente</h1>
                        </div>
                    </div>
                @elseif ($etapa === 'documento')
                    <div class="space-y-5">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 mb-1">Identifícate</h2>
                            <p class="text-sm text-gray-500">Ingresa tu documento de identidad para comenzar.</p>
                        </div>

                        <div>
                            <select id="rit-tipo-documento" wire:model="tipoDocumento" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                <option value="CC">Cédula de Ciudadanía</option>
                                <option value="CE">Cédula de Extranjería</option>
                                <option value="TI">Tarjeta de Identidad</option>
                                <option value="PASS">Pasaporte</option>
                            </select>
                            @error('tipoDocumento') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            {{-- Pedido explícito del usuario (2026-09-22): el campo dejaba escribir
                                 letras y sin límite de longitud. Pasaporte SÍ puede ser alfanumérico,
                                 por eso el filtro solo aplica a CC/CE/TI. --}}
                            <input type="text" id="rit-numero-documento" wire:model="numeroDocumento" placeholder="Número de documento"
                                inputmode="numeric" maxlength="15" autocomplete="off"
                                oninput="var t=document.getElementById('rit-tipo-documento'); if (t && t.value !== 'PASS') { this.value = this.value.replace(/[^0-9]/g, ''); }"
                                class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                            @error('numeroDocumento') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <button wire:click="buscarTrabajador" wire:loading.attr="disabled" wire:target="buscarTrabajador" type="button"
                            class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold rounded-xl shadow-sm transition-colors">
                            <svg wire:loading wire:target="buscarTrabajador" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            <span wire:loading.remove wire:target="buscarTrabajador">Continuar</span>
                            <span wire:loading wire:target="buscarTrabajador">Buscando...</span>
                        </button>
                    </div>
                @elseif ($etapa === 'datos')
                    <div class="space-y-5">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 mb-1">Tus datos</h2>
                            <p class="text-sm text-gray-500">Completa la información que falte para continuar.</p>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <input type="text" wire:model="nombres" placeholder="Nombres" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                @error('nombres') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <input type="text" wire:model="apellidos" placeholder="Apellidos" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                @error('apellidos') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <select wire:model="genero" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                    <option value="">Género</option>
                                    <option value="masculino">Masculino</option>
                                    <option value="femenino">Femenino</option>
                                    <option value="otro">Otro</option>
                                </select>
                                @error('genero') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <input type="text" wire:model="cargo" placeholder="Cargo" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                @error('cargo') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <input type="email" wire:model="email" placeholder="Correo (opcional)" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                @error('email') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <input type="text" wire:model="telefono" placeholder="Teléfono (opcional)" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                @error('telefono') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <button wire:click="guardarDatos" wire:loading.attr="disabled" wire:target="guardarDatos" type="button"
                            class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold rounded-xl shadow-sm transition-colors">
                            <svg wire:loading wire:target="guardarDatos" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            <span wire:loading.remove wire:target="guardarDatos">Continuar</span>
                            <span wire:loading wire:target="guardarDatos">Guardando...</span>
                        </button>
                    </div>
                @elseif ($etapa === 'foto')
                    @include('livewire.partials.foto-simple-captura')
                @elseif ($etapa === 'presentacion_rit')
                    <div class="space-y-4">
                        @if ($esPrimeraAceptacion)
                            <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                                <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                                <div style="position:relative;z-index:2">
                                    <span class="rit-badge rit-badge-ia">Reglamento Interno</span>
                                    <h1 class="rit-title">Conoce el Reglamento Interno de {{ $empresa->razon_social }}</h1>
                                </div>
                            </div>
                            <div class="prose max-w-none text-sm whitespace-pre-line border border-gray-200 rounded-xl p-4 max-h-96 overflow-y-auto">
                                {{ $ritActivoTextoCompleto }}
                            </div>
                        @else
                            <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                                <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                                <div style="position:relative;z-index:2">
                                    <span class="rit-badge rit-badge-warning">Actualización</span>
                                    <h1 class="rit-title">Esto cambió en el Reglamento Interno</h1>
                                </div>
                            </div>
                            @include('filament.components.rit-redline', ['cambios' => $cambiosRit])
                            {{-- Pedido explicito del spec (seccion 6): opcion de ver el
                                 texto completo, no solo el diff, si el trabajador lo prefiere. --}}
                            <details class="text-sm">
                                <summary class="cursor-pointer text-primary-600 font-medium">Ver el Reglamento completo</summary>
                                <div class="prose max-w-none whitespace-pre-line border border-gray-200 rounded-xl p-4 mt-2 max-h-96 overflow-y-auto">
                                    {{ $ritActivoTextoCompleto }}
                                </div>
                            </details>
                        @endif

                        <button type="button" wire:click="$set('etapa', 'aceptacion')"
                            class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white font-semibold rounded-xl shadow-sm transition-colors">
                            Continuar
                        </button>
                    </div>
                @elseif ($etapa === 'aceptacion')
                    <div class="space-y-5">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 mb-1">Última confirmación</h2>
                            <p class="text-sm text-gray-500">Confirma que leíste y entendiste el Reglamento.</p>
                        </div>

                        <label class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
                            <input type="checkbox" wire:model="declaracionAceptada" class="mt-0.5 rounded border border-gray-300 text-primary-600 focus:ring-2 focus:ring-primary-500 flex-shrink-0">
                            <span class="text-sm text-gray-700">Declaro que leí y entendí el Reglamento Interno de Trabajo de {{ $empresa->razon_social }}.</span>
                        </label>
                        @error('declaracionAceptada') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror

                        <button wire:click="aceptarReglamento" wire:loading.attr="disabled" wire:target="aceptarReglamento" type="button"
                            class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold rounded-xl shadow-sm transition-colors">
                            <svg wire:loading wire:target="aceptarReglamento" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            <span wire:loading.remove wire:target="aceptarReglamento">Aceptar</span>
                            <span wire:loading wire:target="aceptarReglamento">Guardando...</span>
                        </button>
                    </div>
                @elseif ($etapa === 'completado')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-sub">
                                <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold" colors="primary:#86efac,secondary:#86efac" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
                                Registro exitoso
                            </span>
                            <h1 class="rit-title">¡Gracias!</h1>
                            <p class="rit-sub">Tu registro y aceptación del Reglamento Interno quedaron guardados.</p>
                        </div>
                    </div>
                @endif
            </main>
        </div>
    </div>

    {{-- Loading: mismo patron que formulario-descargos.blade.php --}}
    <div wire:loading.delay wire:target="buscarTrabajador, guardarDatos, guardarFotoSimple, aceptarReglamento"
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl p-5 flex items-center gap-4 mx-4">
            <svg class="animate-spin h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="font-medium text-gray-700">Procesando...</span>
        </div>
    </div>
</div>
