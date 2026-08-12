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
            ])

            <button type="button" wire:click="irAPaso2"
                :disabled="canal !== 'email' || in_array('{{ $validacionesV6Estado }}', ['pendiente', 'procesando'], true) ||
                    ('{{ $validacionesV6Estado }}'
                        === 'completado' && !{{ $riskAcknowledged ? 'true' : 'false' }})"
                class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg border text-sm font-medium transition-colors
                    disabled:opacity-50 disabled:cursor-not-allowed
                    {{ in_array($validacionesV6Estado, ['pendiente', 'procesando'], true) ||
                    ($validacionesV6Estado === 'completado' && !$riskAcknowledged)
                        ? 'bg-gray-100 text-gray-400 border-gray-200'
                        : 'bg-primary-500 text-white hover:bg-primary-600 border-primary-500' }}">
                Continuar a Decisión
            </button>
            {{-- <div class="mt-2 text-sm text-gray-500">
                @if (in_array($validacionesV6Estado, ['pendiente', 'procesando'], true))
                    <span>Las validaciones de riesgo están en proceso. Por favor, espere a que se completen antes de
                        continuar.</span>
                @elseif ($validacionesV6Estado === 'completado' && !$riskAcknowledged)
                    <span>Debe reconocer los riesgos antes de continuar. Por favor, revise los resultados de las
                        validaciones y confirme que entiende los riesgos.</span>
                @endif
            </div> --}}
        </div>

        {{-- <button type="button" wire:click="irAPaso2" @disabled(in_array($validacionesV6Estado, ['pendiente', 'procesando'], true) ||
                    ($validacionesV6Estado === 'completado' && !$riskAcknowledged))>
                Continuar a Decisión &rarr;
            </button>
        </div>  --}}
    @elseif ($paso === 2)
        <div>
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
                    esta decisión, exonerando a LUPE de cualquier consecuencia derivada de la misma.
                </label>
            @endif

            <button type="button" wire:click="irAPaso1">
                &larr; Volver a Análisis
            </button>

            <button type="button" wire:click="confirmarDecision"
                class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg border text-sm font-medium transition-colors
                    disabled:opacity-50 disabled:cursor-not-allowed
                    {{ !$decision ||
                    (!empty($iaSancionesRecomendadas) &&
                        !in_array($decision, $iaSancionesRecomendadas) &&
                        (strlen(trim($razonDivergencia)) < 5 || !$exoneracionAceptada))
                        ? 'bg-gray-100 text-gray-400 border-gray-200'
                        : 'bg-primary-500 text-white hover:bg-primary-600 border-primary-500' }}"
                @disabled(
                    !$decision ||
                        (!empty($iaSancionesRecomendadas) &&
                            !in_array($decision, $iaSancionesRecomendadas) &&
                            (strlen(trim($razonDivergencia)) < 5 || !$exoneracionAceptada)))>
                Continuar a Autorizar
            </button>

            {{-- <button type="button" wire:click="confirmarDecision" @disabled(
                !$decision ||
                    (!empty($iaSancionesRecomendadas) &&
                        !in_array($decision, $iaSancionesRecomendadas) &&
                        (strlen(trim($razonDivergencia)) < 5 || !$exoneracionAceptada)))>
                Continuar a Autorizar &rarr;
            </button> --}}
        </div>
    @endif
</div>
