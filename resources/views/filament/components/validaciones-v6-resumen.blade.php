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

    // Cada verificación: título en lenguaje simple (nada de "motor"/"V6"/nombres
    // técnicos), el campo que decide si está bien o mal, y qué listas del JSON
    // contienen los hallazgos a mostrar.
    $verificaciones = [
        'ponderacion_evidencia' => [
            'titulo' => 'Fuerza de las pruebas', 'icon' => 'heroicon-o-scale',
            'campo' => 'peso_global', 'buenos' => ['MUY_ALTO', 'ALTO'], 'malos' => ['BAJO', 'NULO'],
            'listas' => [],
        ],
        'resolucion_conflictos' => [
            'titulo' => 'Contradicciones entre las pruebas', 'icon' => 'heroicon-o-arrows-right-left',
            'campo' => 'impacto', 'buenos' => ['BAJO', 'NULO'], 'malos' => ['ALTO'],
            'listas' => ['conflictos_pendientes' => null],
        ],
        'congruencia_juridica' => [
            'titulo' => 'Coherencia del caso', 'icon' => 'heroicon-o-link',
            'campo' => 'nivel_riesgo', 'buenos' => ['BAJO'], 'malos' => ['ALTO'],
            'listas' => ['incongruencias' => null],
        ],
        // 'explicabilidad' se calcula igual (ejecutarValidacionesV6 sigue
        // corriendo los 8) pero NO se muestra aquí: audita si el propio
        // razonamiento de la IA es rastreable, un concepto de control de
        // calidad interno del modelo - no es información que una persona de
        // Recursos Humanos pueda usar para decidir algo. Queda solo en
        // $proceso->validaciones_v6 por si CES Legal quiere revisarlo.
        'simulacion_judicial' => [
            'titulo' => 'Resistencia ante una revisión judicial', 'icon' => 'heroicon-o-building-library',
            'campo' => 'probabilidad_resistencia_judicial', 'buenos' => ['MUY_PROBABLE', 'PROBABLE'], 'malos' => ['IMPROBABLE', 'MUY_IMPROBABLE'],
            'listas' => ['debilidades' => null, 'riesgos' => null],
        ],
        'precedentes_internos' => [
            'titulo' => 'Consistencia con casos anteriores', 'icon' => 'heroicon-o-archive-box',
            'campo' => 'nivel_consistencia', 'buenos' => ['ALTO', 'SIN_PRECEDENTE'], 'malos' => ['INCONSISTENTE', 'BAJO'],
            'listas' => ['alertas' => null],
        ],
        'uniformidad_disciplinaria' => [
            'titulo' => 'Trato igualitario frente a casos similares', 'icon' => 'heroicon-o-users',
            'campo' => 'uniformidad', 'buenos' => ['ALTA'], 'malos' => ['BAJA'],
            'listas' => ['riesgos_discriminacion' => null, 'inconsistencias' => null],
        ],
        // 'calidad_documental' tampoco se muestra por el mismo motivo:
        // revisa ortografía/formato/consistencia del texto generado, no
        // riesgo legal ni algo accionable para quien decide la sanción.
    ];

    $textoDeItem = function ($item): string {
        if (is_array($item)) {
            $texto = $item['descripcion'] ?? $item['detalle'] ?? $item['nota'] ?? implode(' - ', array_filter(array_map('strval', $item)));
        } else {
            $texto = (string) $item;
        }
        // Defensa: si a pesar de la instrucción del prompt se cuela sintaxis
        // técnica (snake_case, corchetes, comillas de array/JSON), se limpia
        // para que no le llegue a Recursos Humanos texto tipo código.
        $texto = preg_replace('/\b[a-z][a-z0-9]*(_[a-z0-9]+)+\b/', ' esto ', $texto);
        $texto = str_replace(["['", "']", '["', '"]', "[", "]"], '', $texto);
        return trim(preg_replace('/\s+/', ' ', $texto));
    };

    $maxHallazgosV6 = 3;

    // Estado de cada verificación: 'ok' (verde) / 'atencion' (ámbar, no es
    // claramente bueno ni malo) / 'riesgo' (rojo). Nunca se muestra el valor
    // crudo del JSON (ALTO/MUY_PROBABLE/SIN_PRECEDENTE/etc.) - solo el ícono
    // y, si hace falta, los hallazgos en español simple.
    $filas = [];
    foreach ($verificaciones as $clave => $meta) {
        $r = $resultados[$clave] ?? null;
        $fallo = !is_array($r) || isset($r['error']);
        $valor = $fallo ? null : ($r[$meta['campo']] ?? null);

        $hallazgos = [];
        if (!$fallo) {
            foreach (array_keys($meta['listas']) as $listaKey) {
                foreach (($r[$listaKey] ?? []) as $item) {
                    $hallazgos[] = $textoDeItem($item);
                }
            }
        }

        if ($fallo) {
            $estadoFila = 'na';
        } elseif (in_array($valor, $meta['malos'], true) || count($hallazgos) > 0) {
            $estadoFila = 'riesgo';
        } elseif (in_array($valor, $meta['buenos'], true)) {
            $estadoFila = 'ok';
        } else {
            $estadoFila = 'atencion';
        }

        $totalHallazgos = count($hallazgos);
        $filas[] = [
            'titulo'     => $meta['titulo'],
            'icon'       => $meta['icon'],
            'estado'     => $estadoFila,
            'hallazgos'  => array_slice($hallazgos, 0, $maxHallazgosV6),
            'ocultos'    => max(0, $totalHallazgos - $maxHallazgosV6),
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
