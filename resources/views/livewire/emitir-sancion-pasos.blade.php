<div>
    @if ($paso === 1)
        {{--
            wire:key REQUERIDO: sin esto, el morph de Livewire (sin keys)
            intenta reutilizar/reordenar nodos entre este bloque y el de
            $paso===2 (que comparten el mismo partial emitir-sancion-analisis
            pero con modoDecision distinto, produciendo árboles similares
            pero no idénticos) - causaba contenido duplicado y secciones
            que desaparecían al hacer "Volver" (bug real reportado por el
            usuario, confirmado leyendo vendor/livewire/livewire/dist/livewire.js:
            sin key, el algoritmo cae en un "lookahead" que compara nodos por
            posición/igualdad en vez de por identidad). Mismo patrón ya usado
            en este proyecto para el mismo problema: formulario-descargos.blade.php
            usa wire:key="feedback-paso-" + el número de paso, ver línea 1221.
        --}}
        <div class="space-y-6" wire:key="emitir-sancion-paso-{{ $paso }}">
            @if ($esFallback)
                @include('filament.components.emitir-sancion-ia-error')
            @else
                @include('filament.components.emitir-sancion-analisis', [
                    'analisis' => $analisis,
                    'recomendacion' => $recomendacionFinal,
                    'opcionesSancion' => $opcionesSancion,
                    'iaSancionesRecomendadas' => $iaSancionesRecomendadas,
                    'modoDecision' => false,
                ])
            @endif

            @include('filament.components.validaciones-v6-resumen', [
                'estado' => $validacionesV6Estado,
                'resultados' => $validacionesV6Resultados,
                'en' => $validacionesV6En,
                'puntosClave' => $validacionesV6PuntosClave,
                'onRiskOpen' => true,
                'riskAcknowledged' => $riskAcknowledged,
            ])

            <div>
                {{--
                    Cancelar aquí es NECESARIO: el Cancelar nativo de Filament está
                    oculto mientras paso_actual < 3 (Task 1), así que sin esto no
                    habría ninguna forma de cerrar el modal desde el Paso 1. Usa
                    Alpine close() (la misma función que usa el Cancelar nativo,
                    definida en el x-data del propio modal - confirmado leyendo
                    vendor/filament/actions/src/StaticAction.php y
                    vendor/filament/support/resources/views/components/modal/index.blade.php),
                    NO $wire.$set(...): $wire aquí resolvería al componente hijo, no
                    al padre, así que no cerraría nada - Alpine sí cruza el límite
                    del componente Livewire anidado porque sus cadenas de scope son
                    del DOM, no de Livewire.
                --}}
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:gap-4">
                    <x-filament::button
                        type="button"
                        x-on:click="close()"
                        color="gray"
                        class="justify-center py-3 sm:justify-start"
                    >
                        Cancelar
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        wire:click="irAPaso2"
                        color="primary"
                        icon="heroicon-o-arrow-right"
                        icon-position="after"
                        class="justify-center py-3 text-base sm:flex-1"
                        :disabled="in_array($validacionesV6Estado, ['pendiente', 'procesando'], true)"
                    >
                        Continuar a Decisión
                    </x-filament::button>
                </div>
                <div class="mt-2 text-sm text-gray-500">
                    @if (in_array($validacionesV6Estado, ['pendiente', 'procesando'], true))
                        <span>Las validaciones de riesgo están en proceso. Por favor, espere a que se completen antes de
                            continuar.</span>
                    @endif
                </div>
            </div>
        </div>
    @elseif ($paso === 2)
        <div class="space-y-6" wire:key="emitir-sancion-paso-{{ $paso }}">
            @if (!$decision)
                <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-400 dark:border-amber-600 space-y-3">
                    <div class="flex items-start gap-3">
                        <lord-icon src="https://cdn.lordicon.com/hmpomorl.json" trigger="loop" delay="500" stroke="bold" colors="primary:#d97706,secondary:#fbbf24" style="width:36px;height:36px;flex-shrink:0;margin-top:2px"></lord-icon>
                        <div>
                            <p class="font-semibold text-amber-900 dark:text-amber-100 text-base">Falta elegir la sanción</p>
                            <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">Seleccione una opción abajo (<strong>"Aplicar esta sanción"</strong> o una de las alternativas) para continuar.</p>
                        </div>
                    </div>
                </div>
            @endif

            @include('filament.components.emitir-sancion-analisis', [
                'analisis' => $analisis,
                'recomendacion' => $recomendacionFinal,
                'opcionesSancion' => $opcionesSancion,
                'iaSancionesRecomendadas' => $iaSancionesRecomendadas,
                'modoDecision' => true,
            ])

            @if (!empty($autoridadRit))
                @include('filament.components.emitir-sancion-potestad', [
                    'autoridadRit' => $autoridadRit,
                    'opcionesSancion' => $opcionesSancion,
                ])
            @endif

            @if ($decision && !empty($iaSancionesRecomendadas) && !in_array($decision, $iaSancionesRecomendadas))
                @php
                    $razonCompleta = strlen(trim($razonDivergencia)) >= 5;
                @endphp
                <div class="space-y-4">
                    @include('filament.components.emitir-sancion-exoneracion-aviso', [
                        'tipoSeleccionado' => $decision,
                        'iaRazonesNoRecomendadas' => $iaRazonesNoRecomendadas,
                    ])
                    <div class="space-y-3">
                        <x-filament::input.wrapper>
                            <textarea
                                wire:model.live.debounce.500ms="razonDivergencia"
                                rows="3"
                                placeholder="Razón por la cual se elige esta sanción en lugar de las recomendadas por la IA"
                                class="fi-input block w-full resize-y border-none bg-white/0 py-1.5 text-base text-gray-950 transition duration-75 placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-500 sm:text-sm sm:leading-6"
                            ></textarea>
                        </x-filament::input.wrapper>

                        <label class="flex items-start gap-x-3 text-sm text-gray-600 dark:text-gray-300">
                            <x-filament::input.checkbox wire:model.live="exoneracionAceptada" class="mt-1 shrink-0" />
                            <span>
                                Confirmo que entiendo las recomendaciones jurídicas emitidas por la IA, que aun así
                                decido aplicar una sanción diferente, y que asumo completamente la responsabilidad
                                jurídica, laboral y judicial de esta decisión, exonerando a LUPE Legal de cualquier
                                consecuencia derivada de la misma.
                            </span>
                        </label>

                        {{--
                            Checklist visible de lo que falta para poder continuar - antes el
                            botón quedaba deshabilitado sin ninguna pista de por qué (bug real
                            reportado: "no entiendo porque no deja continuar"). wire:model.live
                            arriba asegura que esto (y el :disabled del botón) se recalculen en
                            cada tecla/clic, no solo en el próximo request de otro elemento.
                        --}}
                        @unless ($razonCompleta && $exoneracionAceptada)
                            <ul class="space-y-1 text-sm">
                                <li class="flex items-center gap-2 {{ $razonCompleta ? 'text-success-600 dark:text-success-400' : 'text-gray-500 dark:text-gray-400' }}">
                                    <x-filament::icon
                                        :icon="$razonCompleta ? 'heroicon-o-check-circle' : 'heroicon-o-minus-circle'"
                                        class="h-4 w-4 shrink-0"
                                    />
                                    Escriba la razón (mínimo 5 caracteres)
                                </li>
                                <li class="flex items-center gap-2 {{ $exoneracionAceptada ? 'text-success-600 dark:text-success-400' : 'text-gray-500 dark:text-gray-400' }}">
                                    <x-filament::icon
                                        :icon="$exoneracionAceptada ? 'heroicon-o-check-circle' : 'heroicon-o-minus-circle'"
                                        class="h-4 w-4 shrink-0"
                                    />
                                    Marque la casilla de confirmación
                                </li>
                            </ul>
                        @endunless
                    </div>
                </div>
            @endif

            <div>
                {{-- flex-col en móvil: "Continuar a Autorizar" es un texto largo y con
                     "Volver" al lado quedaba apretado en pantallas angostas (podía
                     partirse en 2 líneas dentro del botón). En sm+ vuelven a la fila. --}}
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:gap-4">
                    <x-filament::button
                        type="button"
                        x-on:click="close()"
                        color="gray"
                        class="justify-center py-3 sm:justify-start"
                    >
                        Cancelar
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        wire:click="irAPaso1"
                        color="gray"
                        icon="heroicon-o-arrow-left"
                        class="justify-center py-3 sm:justify-start"
                    >
                        Volver
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        wire:click="confirmarDecision"
                        color="primary"
                        icon="heroicon-o-arrow-right"
                        icon-position="after"
                        class="justify-center py-3 text-base sm:flex-1"
                        :disabled="!$decision ||
                            (!empty($iaSancionesRecomendadas) &&
                                !in_array($decision, $iaSancionesRecomendadas) &&
                                (strlen(trim($razonDivergencia)) < 5 || !$exoneracionAceptada))"
                    >
                        Continuar a Autorizar
                    </x-filament::button>
                </div>
                <div class="mt-2 text-sm text-gray-500">
                    @if (!$decision)
                        <span>Debe seleccionar una sanción antes de continuar.</span>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
