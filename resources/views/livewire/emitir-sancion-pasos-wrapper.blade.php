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
], key('emitir-sancion-pasos-' . $procesoId))
