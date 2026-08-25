<div>
    @if ($paso === 1)
        <div>
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

            <div class="mt-4">
                <x-filament::button
                    type="button"
                    wire:click="irAPaso2"
                    color="primary"
                    icon="heroicon-o-arrow-right"
                    icon-position="after"
                    class="w-full justify-center py-3 text-base"
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
    @elseif ($paso === 2)
        <div>
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
                @include('filament.components.emitir-sancion-exoneracion-aviso', [
                    'tipoSeleccionado' => $decision,
                    'iaRazonesNoRecomendadas' => $iaRazonesNoRecomendadas,
                ])
                <textarea wire:model="razonDivergencia"
                    placeholder="Razón por la cual se elige esta sanción en lugar de las recomendadas por la IA"></textarea>
                <label>
                    <input type="checkbox" wire:model="exoneracionAceptada">
                    Confirmo que entiendo las recomendaciones jurídicas emitidas por la IA, que aun así decido aplicar
                    una sanción diferente, y que asumo completamente la responsabilidad jurídica, laboral y judicial de
                    esta decisión, exonerando a LUPE Legal de cualquier consecuencia derivada de la misma.
                </label>
            @endif

            <div class="mt-6 flex items-center gap-4">
                <x-filament::button
                    type="button"
                    wire:click="irAPaso1"
                    color="gray"
                    icon="heroicon-o-arrow-left"
                    class="py-3"
                >
                    Volver
                </x-filament::button>

                <x-filament::button
                    type="button"
                    wire:click="confirmarDecision"
                    color="primary"
                    icon="heroicon-o-arrow-right"
                    icon-position="after"
                    class="flex-1 justify-center py-3 text-base"
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
    @endif
</div>
