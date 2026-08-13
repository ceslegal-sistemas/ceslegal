{{--
    Resumen de la revisión de calidad adicional que corre en segundo plano
    (EjecutarValidacionesV6Job) después de generar la recomendación de sanción.
    Sin jerga interna ("motores V6") - solo lo que le interesa a Recursos
    Humanos: ¿esta recomendación se sostiene, o hay algo que revisar antes de
    confirmar? Variables esperadas: $estado (string|null), $resultados
    (array|null), $en (\Carbon\Carbon|null), $puntosClave (array|null - lista
    ya consolidada/deduplicada entre motores, ver consolidarHallazgosV6()).

    Mientras el job sigue en curso, este panel se refresca solo cada pocos
    segundos (wire:poll) - no hace falta cerrar y reabrir el modal. El botón
    "Continuar" del modal está bloqueado en el servidor mientras el estado es
    pendiente/procesando (ver ->action() de la acción emitir_sancion en
    ProcesoDisciplinarioResource.php) para que nunca se pueda confirmar la
    recomendación antes de que una posible corrección automática se aplique.
--}}
@php
    $estado      = $estado ?? null;
    $resultados  = is_array($resultados ?? null) ? $resultados : [];
    // Lista ya consolidada/deduplicada por IAAnalisisSancionService::consolidarHallazgosV6()
    // (calculada por el job, no aquí - una llamada más a Gemini no puede hacerse en
    // cada render del modal). Si viene vacía pero SÍ hay hallazgos por motor, es que
    // la consolidación falló o nunca se ejecutó - se cae al detalle por motor sin
    // perder información.
    $puntosClave = is_array($puntosClave ?? null) ? array_values(array_filter($puntosClave)) : [];

    // La clasificación bien/mal por motor y la limpieza de texto viven en
    // IAAnalisisSancionService::evaluarMotoresV6() - única fuente de verdad
    // compartida con EjecutarValidacionesV6Job (que la usa para decidir si
    // corrige la recomendación). Aquí solo se decide cómo mostrarlo.
    $maxHallazgosV6 = 3;

    $filasCrudas = app(\App\Services\IAAnalisisSancionService::class)->evaluarMotoresV6($resultados);

    $filas = [];
    foreach ($filasCrudas as $fila) {
        $totalHallazgos = count($fila['hallazgos']);
        $filas[] = [
            'titulo'    => $fila['titulo'],
            'icon'      => $fila['icon'],
            'estado'    => $fila['estado'],
            'hallazgos' => array_slice($fila['hallazgos'], 0, $maxHallazgosV6),
            'ocultos'   => max(0, $totalHallazgos - $maxHallazgosV6),
        ];
    }
    // Mismo criterio que usa cada fila para decidir si muestra "Sin
    // observaciones" o el detalle desplegable (!$tieneDetalle más abajo, en
    // el foreach) - así el resumen de arriba nunca puede contradecir lo que
    // el cliente ve fila por fila. Antes se contaba por severidad (estado
    // ok vs atencion/riesgo), lo que podía decir "N con observaciones"
    // aunque varias de esas filas mostraran "Sin observaciones" al no tener
    // texto - la severidad igual se ve en el color/ícono de cada fila.
    $numRevisables = collect($filas)->filter(fn($f) => !empty($f['hallazgos']))->count();
    // No cuenta las filas 'na' (motor sin dato disponible - muestran "no
    // disponible", no "Sin observaciones"): si se sumaran aquí, el badge
    // verde diría "sin observaciones" de algo que en realidad no se pudo
    // evaluar.
    $numOk = collect($filas)->filter(fn($f) => empty($f['hallazgos']) && $f['estado'] !== 'na')->count();
@endphp

@if($estado)
{{-- esa-card-soft (definida en emitir-sancion-analisis.blade.php, siempre incluida
     antes de este parcial en el Paso 1 del wizard): mismo look "sección del
     documento" que el resto, en vez de otra tarjeta oscura apilada. --}}
<div class="esa-card v6chk-wrap esa-card-soft" style="margin-top:6px;" @if(in_array($estado, ['pendiente', 'procesando'], true)) wire:poll.4000ms @endif>
    <div style="padding:14px 18px;">
        <p class="esa-label">Revisión de calidad de la recomendación</p>
        <p style="font-size:11px;color:var(--esa-muted);line-height:1.5;margin:2px 0 0;">
            Chequeo automático aparte, no reemplaza los puntos anteriores.
        </p>

        @if(in_array($estado, ['pendiente', 'procesando'], true))
            <div style="display:flex;align-items:center;gap:8px;margin:8px 0 0;">
                <span class="v6chk-spinner" aria-hidden="true"></span>
                <p style="font-size:12.5px;color:var(--esa-muted);line-height:1.6;margin:0;">
                    Estamos revisando la recomendación con más detalle (coherencia, pruebas, redacción...).
                    Suele tardar menos de un minuto - esta ventana se actualiza sola. <strong style="color:var(--esa-text);">Mientras tanto no se puede confirmar la sanción.</strong>
                </p>
            </div>
        @elseif($estado === 'error')
            <p style="font-size:12.5px;color:var(--esa-muted);line-height:1.6;margin:6px 0 0;">
                No se pudo completar esta revisión adicional. Esto no bloquea la emisión de la sanción -
                la recomendación principal de arriba sigue siendo válida.
            </p>
        @elseif($estado === 'completado')
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:10px 0 4px;">
                @if($numOk > 0)
                    <span style="display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:600;color:#16a34a;background:rgba(22,163,74,.1);border-radius:999px;padding:3px 9px;">
                        <svg style="width:12px;height:12px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        {{ $numOk }} sin observaciones
                    </span>
                @endif
                @if($numRevisables > 0)
                    <span style="display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:600;color:#b45309;background:rgba(217,119,6,.1);border-radius:999px;padding:3px 9px;">
                        <svg style="width:12px;height:12px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                        {{ $numRevisables }} con observaciones para revisar (opcional)
                    </span>
                @endif
                @if($en)
                    <span style="font-size:11px;color:var(--esa-muted);opacity:.75;white-space:nowrap;">{{ $en->diffForHumans() }}</span>
                @endif
            </div>
            <p style="font-size:11.5px;color:var(--esa-muted);line-height:1.5;margin:0 0 12px;">
                Esto es un apoyo adicional, no reemplaza su criterio. Puede confirmar la sanción sin necesidad de abrir estos puntos - ábralos abajo solo si quiere ver el detalle.
            </p>

            @if(!empty($puntosClave))
                <div class="v6chk-item" style="border-left-color:#d97706;margin-bottom:8px;">
                    <div class="v6chk-head" style="cursor:default;">
                        <svg style="width:16px;height:16px;flex-shrink:0;color:#d97706;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                        <span style="font-size:12.5px;font-weight:600;color:var(--esa-text);">Puntos a revisar</span>
                    </div>
                    <div class="v6chk-body">
                        @foreach($puntosClave as $punto)
                            <div class="v6chk-li"><span style="flex-shrink:0;color:#d97706;">›</span>{{ $punto }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <details @if(!empty($puntosClave)) {{-- colapsado: ya se mostró el resumen de arriba --}} @else open @endif>
                <summary style="cursor:pointer;font-size:11.5px;color:var(--esa-muted);list-style:none;display:flex;align-items:center;gap:5px;margin:0 0 6px;">
                    <svg style="width:12px;height:12px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 4.707a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L10.586 10 7.293 6.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    Ver detalle por revisión
                </summary>
                <div style="display:flex;flex-direction:column;gap:5px;">
                    @foreach($filas as $fila)
                        @php
                            $color = match($fila['estado']) {
                                'ok' => '#16a34a', 'atencion' => '#d97706', 'riesgo' => '#dc2626', default => '#9ca3af',
                            };
                            $tieneDetalle = !empty($fila['hallazgos']);
                            $esMotorRiesgo = ($onRiskOpen ?? false) && $fila['titulo'] === 'Resistencia ante una revisión judicial';
                        @endphp
                        {{--
                            Esta fila ya no bloquea el avance del wizard - solo se deja
                            registro de auditoría si el cliente decide abrirla (ver
                            acknowledgeRisk() en EmitirSancionPasos.php). Por eso no lleva
                            ningún resalte/pulso: es lectura opcional, igual que las demás.

                            Bug corregido (se mantiene el fix aunque ya no sea obligatoria):
                            wire:click directo sobre un <details> hace que, al llegar la
                            respuesta de Livewire, el morph del DOM borre el atributo "open"
                            (el HTML renderizado en el servidor nunca lo incluye) y la fila
                            se cierra sola justo después de abrirse.

                            Fix de dos partes, ambas necesarias:
                            1) x-on:toggle en vez de wire:click: el toggle nativo del
                               <details> ya ocurrió (100% del lado del cliente) antes de
                               llamar a $wire.acknowledgeRisk(), así que abrir/cerrar ya no
                               depende de que la petición a Livewire tenga éxito o de cuándo
                               vuelva. Solo se llama cuando efectivamente quedó abierto
                               ($event.target.open), no al cerrar.
                            2) wire:ignore.self en el <details>: aunque (1) ya desacopla el
                               toggle de la petición, la petición de todos modos dispara un
                               re-render y ESE morph seguiría pisando "open" si no se
                               protege el elemento. wire:ignore.self congela los atributos
                               propios del <details> (incluido "open") sin congelar sus
                               hijos, que se siguen actualizando con normalidad.
                        --}}
                        <{{ $tieneDetalle ? 'details' : 'div' }} class="v6chk-item" style="border-left-color:{{ $color }};"
                            @if($esMotorRiesgo && $tieneDetalle)
                                wire:ignore.self
                                x-on:toggle="if ($event.target.open) { $wire.acknowledgeRisk() }"
                            @elseif($esMotorRiesgo)
                                wire:click="acknowledgeRisk"
                            @endif
                        >
                            <{{ $tieneDetalle ? 'summary' : 'div' }} class="v6chk-head">
                                @if($fila['estado'] === 'ok')
                                    <svg style="width:16px;height:16px;flex-shrink:0;color:{{ $color }};" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                @elseif($fila['estado'] === 'na')
                                    <svg style="width:16px;height:16px;flex-shrink:0;color:{{ $color }};" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                @elseif($fila['estado'] === 'atencion')
                                    <svg style="width:16px;height:16px;flex-shrink:0;color:{{ $color }};" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                                @else
                                    <svg style="width:16px;height:16px;flex-shrink:0;color:{{ $color }};" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                @endif
                                <span style="flex:1;min-width:0;font-size:12.5px;font-weight:600;color:var(--esa-text);{{ $fila['estado'] === 'ok' ? 'opacity:.8' : '' }}">{{ $fila['titulo'] }}</span>
                                @if($fila['estado'] === 'na')
                                    <span style="font-size:11px;color:var(--esa-muted);">no disponible</span>
                                @elseif(!$tieneDetalle)
                                    <span style="font-size:11px;color:var(--esa-muted);">Sin observaciones</span>
                                @else
                                    <svg class="v6chk-chevron" style="width:13px;height:13px;flex-shrink:0;color:var(--esa-muted);" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 4.707a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L10.586 10 7.293 6.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                @endif
                            </{{ $tieneDetalle ? 'summary' : 'div' }}>
                            @if($tieneDetalle)
                                <div class="v6chk-body">
                                    @foreach($fila['hallazgos'] as $h)
                                        <div class="v6chk-li"><span style="flex-shrink:0;color:{{ $color }};">›</span>{{ $h }}</div>
                                    @endforeach
                                    @if($fila['ocultos'] > 0)
                                        <div class="v6chk-li" style="font-style:italic;opacity:.7;">+{{ $fila['ocultos'] }} observación{{ $fila['ocultos'] > 1 ? 'es' : '' }} adicional{{ $fila['ocultos'] > 1 ? 'es' : '' }}.</div>
                                    @endif
                                </div>
                            @endif
                        </{{ $tieneDetalle ? 'details' : 'div' }}>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
</div>

<style>
.v6chk-item{border-left:3px solid;border-radius:.5rem;background:rgba(0,0,0,.02);}
html.dark .v6chk-item{background:rgba(255,255,255,.03);}
.v6chk-head{padding:8px 10px;cursor:pointer;list-style:none;display:flex;align-items:center;gap:8px;}
div.v6chk-head{cursor:default;}
.v6chk-item summary::-webkit-details-marker{display:none;}
.v6chk-chevron{transition:transform .15s ease;}
.v6chk-item[open] .v6chk-chevron{transform:rotate(90deg);}
.v6chk-body{padding:0 10px 9px 34px;}
.v6chk-li{font-size:12px;color:var(--esa-text);line-height:1.55;display:flex;gap:6px;margin:3px 0 0;}
.v6chk-spinner{flex-shrink:0;width:14px;height:14px;border-radius:50%;border:2px solid rgba(0,0,0,.12);border-top-color:#2563eb;animation:v6chkspin .8s linear infinite;}
html.dark .v6chk-spinner{border-color:rgba(255,255,255,.15);border-top-color:#60a5fa;}
@keyframes v6chkspin{to{transform:rotate(360deg);}}
</style>
@endif
