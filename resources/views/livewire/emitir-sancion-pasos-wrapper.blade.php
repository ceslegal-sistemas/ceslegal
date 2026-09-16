{{--
    El puente Alpine que vivía aquí ($wire.$set(...) crudo al capturar el
    evento de navegador) causaba un bug real (botones duplicados - ver
    ListProcesoDisciplinarios::recibirDecisionSancion() para la causa raíz
    completa): un $wire.$set() en bruto no invalida el formulario cacheado de
    Filament, así que el wizard nunca se ocultaba al llegar al Paso 3. Ahora
    EmitirSancionPasos::confirmarDecision() despacha el mismo evento
    'emitir-sancion-paso2-completo', pero lo captura un listener PHP real
    (#[On(...)]) en la página, que sí dispara la reconstrucción correcta.
--}}
<div>
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
