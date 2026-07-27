{{--
    Resumen de las 8 validaciones V6 (Ponderación de Evidencia, Resolución de
    Conflictos, Congruencia Jurídica, Explicabilidad, Simulación Judicial,
    Precedentes Internos, Uniformidad Disciplinaria, Calidad Documental) que
    corren en segundo plano (EjecutarValidacionesV6Job) después de generar la
    recomendación de sanción. Variables esperadas: $estado (string|null),
    $resultados (array|null), $en (\Carbon\Carbon|null).

    Este contenido se calcula al abrir el modal - si el job todavía no
    terminó, hay que cerrar y volver a abrir "Emitir Sanción" más tarde para
    ver el resultado actualizado (no hace polling en vivo).
--}}
@php
    $estado     = $estado ?? null;
    $resultados = is_array($resultados ?? null) ? $resultados : [];

    $motoresV6 = [
        'ponderacion_evidencia' => [
            'titulo' => 'Ponderación de Evidencia', 'icon' => 'heroicon-o-scale',
            'campo' => 'peso_global', 'buenos' => ['MUY_ALTO', 'ALTO'], 'malos' => ['BAJO', 'NULO'],
            'listas' => [],
        ],
        'resolucion_conflictos' => [
            'titulo' => 'Resolución de Conflictos Probatorios', 'icon' => 'heroicon-o-arrows-right-left',
            'campo' => 'impacto', 'buenos' => ['BAJO', 'NULO'], 'malos' => ['ALTO'],
            'listas' => ['conflictos_pendientes' => 'Conflictos sin resolver'],
        ],
        'congruencia_juridica' => [
            'titulo' => 'Congruencia Jurídica', 'icon' => 'heroicon-o-link',
            'campo' => 'nivel_riesgo', 'buenos' => ['BAJO'], 'malos' => ['ALTO'],
            'listas' => ['incongruencias' => 'Incongruencias detectadas'],
        ],
        'explicabilidad' => [
            'titulo' => 'Explicabilidad', 'icon' => 'heroicon-o-light-bulb',
            'campo' => 'explicable', 'buenos' => [true], 'malos' => [false],
            'listas' => ['fallas_explicabilidad' => 'Fallas de explicabilidad'],
        ],
        'simulacion_judicial' => [
            'titulo' => 'Simulación Judicial', 'icon' => 'heroicon-o-building-library',
            'campo' => 'probabilidad_resistencia_judicial', 'buenos' => ['MUY_PROBABLE', 'PROBABLE'], 'malos' => ['IMPROBABLE', 'MUY_IMPROBABLE'],
            'listas' => ['debilidades' => 'Debilidades', 'riesgos' => 'Riesgos'],
        ],
        'precedentes_internos' => [
            'titulo' => 'Precedentes Internos', 'icon' => 'heroicon-o-archive-box',
            'campo' => 'nivel_consistencia', 'buenos' => ['ALTO'], 'malos' => ['INCONSISTENTE', 'BAJO'],
            'listas' => ['alertas' => 'Alertas'],
        ],
        'uniformidad_disciplinaria' => [
            'titulo' => 'Uniformidad Disciplinaria', 'icon' => 'heroicon-o-users',
            'campo' => 'uniformidad', 'buenos' => ['ALTA'], 'malos' => ['BAJA'],
            'listas' => ['riesgos_discriminacion' => 'Riesgos de discriminación', 'inconsistencias' => 'Inconsistencias'],
        ],
        'calidad_documental' => [
            'titulo' => 'Calidad Documental', 'icon' => 'heroicon-o-document-check',
            'campo' => 'calidad_documental', 'buenos' => ['EXCELENTE', 'BUENA'], 'malos' => ['DEFICIENTE'],
            'listas' => ['errores' => 'Errores', 'advertencias' => 'Advertencias'],
        ],
    ];

    $colorDeValor = function ($valor, array $buenos, array $malos): string {
        if (in_array($valor, $buenos, true)) return '#16a34a';
        if (in_array($valor, $malos, true)) return '#dc2626';
        return '#d97706';
    };

    $textoDeValor = function ($valor): string {
        if (is_bool($valor)) return $valor ? 'Sí' : 'No';
        return (string) ($valor ?? '—');
    };

    $textoDeItem = function ($item): string {
        if (is_string($item)) return $item;
        if (is_array($item)) {
            return $item['descripcion'] ?? $item['detalle'] ?? $item['nota'] ?? implode(' - ', array_filter(array_map('strval', $item)));
        }
        return (string) $item;
    };

    // Cuántos motores señalan algo que requiere atención (para el resumen de arriba).
    $motoresConAlerta = 0;
    foreach ($motoresV6 as $clave => $meta) {
        $r = $resultados[$clave] ?? null;
        if (!is_array($r) || isset($r['error'])) continue;
        $valor = $r[$meta['campo']] ?? null;
        if (in_array($valor, $meta['malos'], true)) $motoresConAlerta++;
        foreach (array_keys($meta['listas']) as $listaKey) {
            if (!empty($r[$listaKey])) { $motoresConAlerta++; break; }
        }
    }
@endphp

@if($estado)
<div class="esa-card" style="margin-top:10px;">
    <div style="padding:14px 18px;">
        <p class="esa-label" style="display:flex;align-items:center;gap:6px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 style="width:15px;height:15px;flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            Validación jurídica adicional (motores V6)
        </p>

        @if(in_array($estado, ['pendiente', 'procesando'], true))
            <p style="font-size:12.5px;color:var(--esa-muted);line-height:1.6;margin:6px 0 0;">
                Corriendo en segundo plano (8 motores de auditoría: evidencia, congruencia, simulación judicial,
                precedentes, uniformidad, calidad documental...). Puede tardar unos minutos - cierre y vuelva a
                abrir "Emitir Sanción" para ver el resultado.
            </p>
        @elseif($estado === 'error')
            <p style="font-size:12.5px;color:var(--esa-muted);line-height:1.6;margin:6px 0 0;">
                No se pudo completar la validación adicional. Esto no bloquea la emisión de la sanción - la
                recomendación principal de arriba sigue siendo válida.
            </p>
        @elseif($estado === 'completado')
            <p style="font-size:12.5px;color:var(--esa-muted);line-height:1.6;margin:6px 0 10px;">
                @if($motoresConAlerta > 0)
                    {{ $motoresConAlerta }} de 8 motores señalan algo para revisar antes de confirmar.
                @else
                    Los 8 motores no encontraron observaciones relevantes.
                @endif
                @if($en)
                    <span style="opacity:.7;">(analizado {{ $en->diffForHumans() }})</span>
                @endif
            </p>

            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach($motoresV6 as $clave => $meta)
                    @php
                        $r = $resultados[$clave] ?? null;
                        $fallo = !is_array($r) || isset($r['error']);
                        $valor = $fallo ? null : ($r[$meta['campo']] ?? null);
                        $color = $fallo ? '#9ca3af' : $colorDeValor($valor, $meta['buenos'], $meta['malos']);
                        $hallazgos = [];
                        if (!$fallo) {
                            foreach ($meta['listas'] as $listaKey => $listaLabel) {
                                foreach (($r[$listaKey] ?? []) as $item) {
                                    $hallazgos[] = ['grupo' => $listaLabel, 'texto' => $textoDeItem($item)];
                                }
                            }
                        }
                    @endphp
                    <details style="border:1px solid {{ $color }}33; border-radius:8px; background:{{ $color }}0a;">
                        <summary style="padding:8px 11px; cursor:pointer; list-style:none; display:flex; align-items:center; gap:8px; font-size:12.5px;">
                            @svg($meta['icon'], '', ['style' => "width:14px;height:14px;flex-shrink:0;color:{$color};"])
                            <span style="flex:1;min-width:0;font-weight:600;color:var(--esa-text);">{{ $meta['titulo'] }}</span>
                            @if($fallo)
                                <span style="font-size:11px;color:#9ca3af;">no disponible</span>
                            @else
                                <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;background:{{ $color }}1f;color:{{ $color }};">
                                    {{ $textoDeValor($valor) }}
                                </span>
                            @endif
                        </summary>
                        @if(!$fallo && !empty($hallazgos))
                            <div style="padding:0 11px 10px 33px;">
                                @foreach($hallazgos as $h)
                                    <p style="font-size:12px;color:var(--esa-text);line-height:1.55;margin:4px 0 0;">
                                        <span style="opacity:.65;font-weight:600;">{{ $h['grupo'] }}:</span> {{ $h['texto'] }}
                                    </p>
                                @endforeach
                            </div>
                        @elseif(!$fallo)
                            <div style="padding:0 11px 10px 33px;">
                                <p style="font-size:12px;color:var(--esa-muted);margin:4px 0 0;">Sin observaciones.</p>
                            </div>
                        @endif
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endif
