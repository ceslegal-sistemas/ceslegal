{{--
    Resumen de la revisión de calidad adicional que corre en segundo plano
    (EjecutarValidacionesV6Job) después de generar la recomendación de sanción.
    Sin jerga interna ("motores V6") - solo lo que le interesa a Recursos
    Humanos: ¿esta recomendación se sostiene, o hay algo que revisar antes de
    confirmar? Variables esperadas: $estado (string|null), $resultados
    (array|null), $en (\Carbon\Carbon|null).

    Este contenido se calcula al abrir el modal - si el job todavía no
    terminó, hay que cerrar y volver a abrir "Emitir Sanción" más tarde para
    ver el resultado actualizado (no hace polling en vivo).
--}}
@php
    $estado     = $estado ?? null;
    $resultados = is_array($resultados ?? null) ? $resultados : [];

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
    $numOk = collect($filas)->where('estado', 'ok')->count();
    $numRevisables = collect($filas)->whereIn('estado', ['riesgo', 'atencion'])->count();
@endphp

@if($estado)
<div class="esa-card v6chk-wrap" style="margin-top:10px;">
    <div style="padding:14px 18px;">
        <p class="esa-label">Revisión de calidad de la recomendación</p>

        @if(in_array($estado, ['pendiente', 'procesando'], true))
            <p style="font-size:12.5px;color:var(--esa-muted);line-height:1.6;margin:6px 0 0;">
                Estamos revisando la recomendación con más detalle (coherencia, pruebas, redacción...).
                Puede tardar unos minutos - cierre y vuelva a abrir "Emitir Sanción" para ver el resultado.
            </p>
        @elseif($estado === 'error')
            <p style="font-size:12.5px;color:var(--esa-muted);line-height:1.6;margin:6px 0 0;">
                No se pudo completar esta revisión adicional. Esto no bloquea la emisión de la sanción -
                la recomendación principal de arriba sigue siendo válida.
            </p>
        @elseif($estado === 'completado')
            <div style="display:flex;align-items:center;gap:.625rem;margin:8px 0 12px;">
                <span style="font-size:12px;color:var(--esa-muted);white-space:nowrap;font-weight:600;">
                    {{ $numOk }} de {{ count($filas) }} en orden
                </span>
                <div class="v6chk-track"><div class="v6chk-fill" style="width:{{ round($numOk / max(1, count($filas)) * 100) }}%;"></div></div>
                @if($en)
                    <span style="font-size:11px;color:var(--esa-muted);opacity:.75;white-space:nowrap;">{{ $en->diffForHumans() }}</span>
                @endif
            </div>

            <div style="display:flex;flex-direction:column;gap:5px;">
                @foreach($filas as $fila)
                    @php
                        $color = match($fila['estado']) {
                            'ok' => '#16a34a', 'atencion' => '#d97706', 'riesgo' => '#dc2626', default => '#9ca3af',
                        };
                        $tieneDetalle = !empty($fila['hallazgos']);
                    @endphp
                    <{{ $tieneDetalle ? 'details' : 'div' }} class="v6chk-item" style="border-left-color:{{ $color }};">
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

            @if($numRevisables > 0)
                <p style="font-size:11.5px;color:var(--esa-muted);line-height:1.5;margin:10px 0 0;">
                    Esto es un apoyo adicional, no reemplaza su criterio - puede confirmar la sanción igual si considera que los puntos señalados no cambian la decisión.
                </p>
            @endif
        @endif
    </div>
</div>

<style>
.v6chk-track{flex:1;height:5px;border-radius:3px;background:rgba(0,0,0,.08);overflow:hidden;min-width:60px;}
html.dark .v6chk-track{background:rgba(255,255,255,.1);}
.v6chk-fill{height:100%;border-radius:3px;background:#16a34a;transition:width .5s ease;}
.v6chk-item{border-left:3px solid;border-radius:.5rem;background:rgba(0,0,0,.02);}
html.dark .v6chk-item{background:rgba(255,255,255,.03);}
.v6chk-head{padding:8px 10px;cursor:pointer;list-style:none;display:flex;align-items:center;gap:8px;}
div.v6chk-head{cursor:default;}
.v6chk-item summary::-webkit-details-marker{display:none;}
.v6chk-chevron{transition:transform .15s ease;}
.v6chk-item[open] .v6chk-chevron{transform:rotate(90deg);}
.v6chk-body{padding:0 10px 9px 34px;}
.v6chk-li{font-size:12px;color:var(--esa-text);line-height:1.55;display:flex;gap:6px;margin:3px 0 0;}
</style>
@endif
