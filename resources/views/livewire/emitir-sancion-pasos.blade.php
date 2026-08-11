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

            <button
                type="button"
                wire:click="irAPaso2"
                @disabled(
                    in_array($validacionesV6Estado, ['pendiente', 'procesando'], true)
                    || ($validacionesV6Estado === 'completado' && ! $riskAcknowledged)
                )
            >
                Continuar a Decisión &rarr;
            </button>
        </div>
    @elseif ($paso === 2)
        <div>
            <p>PASO 2: Decidir la sanción (contenido real en Task 5)</p>
            <button type="button" wire:click="selectDecision('llamado_atencion')">Elegir llamado_atencion (prueba)</button>
            <button
                type="button"
                wire:click="confirmarDecision"
                @disabled(! $decision)
            >
                Continuar a Autorizar &rarr;
            </button>
        </div>
    @endif
</div>
