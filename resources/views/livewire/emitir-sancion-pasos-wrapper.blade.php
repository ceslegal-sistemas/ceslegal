<div
    x-data
    x-on:emitir-sancion-paso2-completo.window="
        $wire.$set('mountedTableActionsData.0.tipo_sancion', $event.detail.tipoSancion);
        $wire.$set('mountedTableActionsData.0.razon_divergencia', $event.detail.razonDivergencia);
        $wire.$set('mountedTableActionsData.0.exoneracion_aceptada', $event.detail.exoneracionAceptada);
        $wire.$set('mountedTableActionsData.0.paso_actual', 3);
    "
>
    @livewire('emitir-sancion-pasos', [
        'procesoId' => $procesoId,
        'analisis' => $analisis,
        'esFallback' => $esFallback,
        'opcionesSancion' => $opcionesSancion,
        'iaSancionesRecomendadas' => $iaSancionesRecomendadas,
        'recomendacionFinal' => $recomendacionFinal,
        'autoridadRit' => $autoridadRit,
        'iaRazonesNoRecomendadas' => $iaRazonesNoRecomendadas,
        'validacionesV6Estado' => $validacionesV6Estado,
        'validacionesV6Resultados' => $validacionesV6Resultados,
        'validacionesV6PuntosClave' => $validacionesV6PuntosClave,
        'validacionesV6En' => $validacionesV6En,
        'decision' => $decision ?? null,
    ], key('emitir-sancion-pasos-' . $procesoId))
</div>
