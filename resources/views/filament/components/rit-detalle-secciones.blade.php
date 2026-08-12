{{--
    Panel compartido "Detalle por sección" de la auditoría del RIT - análisis de brechas
    (gap analysis) por ítem del checklist (App\Support\RitGoldStandard), no listas sueltas
    de hallazgos/recomendaciones. Usado por la vista unificada del cliente
    (rit-auditoria-panel.blade.php) y por la página admin (auditar-rit.blade.php) para que
    ambas se vean exactamente igual.

    Espera:
      $secciones : array  - $auditoria->secciones (keyed por clave de sección)
      $numDone   : int    - secciones ya revisadas (para el contador del encabezado)

    Auditorías anteriores a este cambio no tienen la clave "items" (solo el formato viejo
    de hallazgos/recomendaciones en listas sueltas); esas se siguen mostrando con el
    formato anterior, sin necesidad de volver a auditar para que la página no se rompa.
--}}
<style>
.gsec-viewer{border-radius:1rem;border:1px solid rgba(255,255,255,.09);overflow:hidden}
html:not(.dark) .gsec-viewer{border-color:rgba(0,0,0,.08);box-shadow:0 2px 12px rgba(0,0,0,.06)}
.gsec-vh{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.125rem;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04)}
html:not(.dark) .gsec-vh{background:rgba(0,0,0,.03);border-bottom-color:rgba(0,0,0,.07)}
.gsec-vl{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
.gsec-vb{padding:1.25rem 1.5rem}
.gsec-card{border-radius:.875rem;padding:1.125rem 1.25rem;border-left:3px solid;margin-bottom:.625rem;background:rgba(255,255,255,.03)}
html:not(.dark) .gsec-card{background:#fff}
.gsec-title{font-size:.875rem;font-weight:600;color:#f1f5f9}
html:not(.dark) .gsec-title{color:#1c1917}
.gsec-tag{display:inline-flex;font-size:.65rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:.2rem .6rem;border-radius:.375rem}
.gsec-tag-ok{background:rgba(34,197,94,.13);color:#86efac} html:not(.dark) .gsec-tag-ok{background:rgba(22,163,74,.1);color:#166534}
.gsec-tag-warn{background:rgba(245,158,11,.13);color:#fcd34d} html:not(.dark) .gsec-tag-warn{background:rgba(217,119,6,.1);color:#92400e}
.gsec-tag-danger{background:rgba(239,68,68,.13);color:#fca5a5} html:not(.dark) .gsec-tag-danger{background:rgba(220,38,38,.1);color:#991b1b}
.gsec-sublabel{font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#64748b;margin-bottom:.375rem}
.gsec-muted{font-size:.8rem;color:#94a3b8;line-height:1.5}
html:not(.dark) .gsec-muted{color:#475569}
.gsec-li{display:flex;gap:.5rem;font-size:.8rem;color:#94a3b8;line-height:1.5;margin:.2rem 0}
html:not(.dark) .gsec-li{color:#475569}
.gsec-track{width:100%;height:5px;border-radius:3px;background:rgba(255,255,255,.08);overflow:hidden}
html:not(.dark) .gsec-track{background:rgba(0,0,0,.08)}
.gsec-fill{height:100%;border-radius:3px;transition:width .6s cubic-bezier(.4,0,.2,1)}
.gsec-item{padding:.5rem 0;border-top:1px solid rgba(255,255,255,.06)}
html:not(.dark) .gsec-item{border-top-color:rgba(0,0,0,.05)}
.gsec-item:first-of-type{border-top:none;padding-top:.25rem}
.gsec-item-head{display:flex;align-items:flex-start;gap:.5rem;font-size:.8125rem;color:#e2e8f0}
html:not(.dark) .gsec-item-head{color:#292524}
.gsec-item-gap{margin:.375rem 0 .25rem 1.375rem;padding:.5rem .75rem;border-radius:.5rem;background:rgba(245,158,11,.06);border-left:2px solid rgba(245,158,11,.4)}
html:not(.dark) .gsec-item-gap{background:rgba(217,119,6,.05)}
.gsec-art{display:inline-block;font-size:.68rem;color:#94a3b8;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);padding:.2rem .55rem;border-radius:.4rem;margin:.15rem .3rem .15rem 0}
html:not(.dark) .gsec-art{color:#57534e;background:rgba(0,0,0,.03);border-color:rgba(0,0,0,.08)}
</style>

<div class="gsec-viewer">
    <div class="gsec-vh">
        <span class="gsec-vl">Detalle por sección</span>
        <span style="font-size:.75rem;color:#64748b">{{ $numDone }} secciones revisadas</span>
    </div>
    <div class="gsec-vb">
        @foreach($secciones as $clave => $sec)
            @php
                $calif = $sec['calificacion'] ?? 'Ausente';
                $bord  = $calif === 'Completo' ? '#22c55e' : ($calif === 'Parcial' ? '#f59e0b' : '#ef4444');
                $tag   = $calif === 'Completo' ? 'gsec-tag-ok' : ($calif === 'Parcial' ? 'gsec-tag-warn' : 'gsec-tag-danger');
                $items = $sec['items'] ?? null;
                $numCubiertos = is_array($items) ? collect($items)->where('estado', 'cubierto')->count() : null;
            @endphp
            <div class="gsec-card" style="border-color:{{ $bord }}">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:.5rem">
                    <span class="gsec-title">{{ $sec['titulo'] ?? $clave }}</span>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        @if(!($sec['seccion_encontrada'] ?? true))
                            <span style="font-size:.7rem;color:#94a3b8;font-style:italic">No encontrado en el RIT</span>
                        @endif
                        <span style="font-size:.75rem;font-weight:700;color:{{ $bord }}">{{ $sec['score'] ?? 0 }}/100</span>
                        <span class="gsec-tag {{ $tag }}">{{ $calif }}</span>
                    </div>
                </div>

                @if(is_array($items) && count($items) > 0)
                    {{-- ── Análisis de brechas: checklist con hallazgo/recomendación junto al ítem ── --}}
                    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.75rem">
                        <span class="gsec-muted" style="white-space:nowrap;font-weight:600">{{ $numCubiertos }} de {{ count($items) }} elementos cubiertos</span>
                        <div class="gsec-track" style="flex:1">
                            <div class="gsec-fill" style="width:{{ round($numCubiertos / count($items) * 100) }}%;background:{{ $bord }}"></div>
                        </div>
                    </div>

                    @foreach($items as $it)
                        @php
                            $esOk = $it['estado'] === 'cubierto';
                            $esWarn = $it['estado'] === 'parcial';
                            $iconColor = $esOk ? '#22c55e' : ($esWarn ? '#f59e0b' : '#ef4444');
                        @endphp
                        <div class="gsec-item">
                            <div class="gsec-item-head">
                                @if($esOk)
                                    <svg style="width:15px;height:15px;flex-shrink:0;margin-top:1px" fill="none" viewBox="0 0 24 24" stroke="{{ $iconColor }}" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @elseif($esWarn)
                                    <svg style="width:15px;height:15px;flex-shrink:0;margin-top:1px" fill="none" viewBox="0 0 24 24" stroke="{{ $iconColor }}" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                @else
                                    <svg style="width:15px;height:15px;flex-shrink:0;margin-top:1px" fill="none" viewBox="0 0 24 24" stroke="{{ $iconColor }}" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                                <span style="{{ $esOk ? 'opacity:.75' : '' }}">{{ $it['item'] }}</span>
                            </div>
                            @if(!$esOk && ($it['hallazgo'] || $it['recomendacion']))
                                <div class="gsec-item-gap">
                                    @if($it['hallazgo'])<div class="gsec-li" style="margin:0 0 .25rem"><span style="flex-shrink:0;color:#f97316">›</span>{{ $it['hallazgo'] }}</div>@endif
                                    @if($it['recomendacion'])<div class="gsec-li" style="margin:0"><span style="flex-shrink:0;color:#22c55e">→</span>{{ $it['recomendacion'] }}</div>@endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                @else
                    {{-- ── Formato anterior (auditorías previas a este cambio, sin checklist por ítem) ── --}}
                    @if(!empty($sec['hallazgos']))
                        <p class="gsec-sublabel">Hallazgos</p>
                        @foreach($sec['hallazgos'] as $h)<div class="gsec-li"><span style="flex-shrink:0;color:#f97316">›</span>{{ $h }}</div>@endforeach
                    @endif
                    @if(!empty($sec['recomendaciones']))
                        <p class="gsec-sublabel" style="margin-top:.625rem">Recomendaciones</p>
                        @foreach($sec['recomendaciones'] as $r)<div class="gsec-li"><span style="flex-shrink:0;color:#22c55e">→</span>{{ $r }}</div>@endforeach
                    @endif
                @endif

                @if(!empty($sec['articulos_referencia']))
                    <div style="margin-top:.75rem">
                        @foreach($sec['articulos_referencia'] as $art)<span class="gsec-art">{{ $art }}</span>@endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
