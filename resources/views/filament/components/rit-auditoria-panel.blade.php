@php
    /**
     * Panel de salud legal (auditoría) para la vista unificada del cliente.
     * Replica el diseño de admin/auditar-r-i-t para ser consistente y profesional.
     * Usa $this->auditoria, $this->ritMejorado y los métodos refrescarAuditoria,
     * iniciarAuditoriaManual, mantenerRITActual, reintentarMejora, downloadPDFMejorado
     * y la acción aceptarSugerenciasRITAction.
     */
    $a            = $this->auditoria ?? null;
    $rm           = $this->ritMejorado ?? null;
    $secciones    = $a?->secciones ?? [];
    $numTotal     = \App\Services\AuditoriaRITService::getNumSecciones();
    $numDone      = count($secciones);
    $enProceso    = $a && in_array($a->estado, ['pendiente', 'procesando'], true);
    $completada   = $a && $a->estado === 'completado';
    $errorAud     = $a && $a->estado === 'error';
    $score        = $a?->score ?? 0;
    $scoreColor   = $score >= 80 ? '#22c55e' : ($score >= 50 ? '#f59e0b' : '#ef4444');
    $estadoMejora = $a?->estado_mejora;
    $decision     = $a?->decision_mejora;
    $mejorando    = $estadoMejora === 'procesando';
    $mejoraFallo  = $estadoMejora === 'fallido';
    $mejoraLista  = $estadoMejora === 'completado' && $rm;
    $mejoraPend   = $mejoraLista && ! in_array($decision, ['adoptado', 'rechazado'], true);
    $numCorreg    = $mejoraLista ? collect($secciones)->filter(fn($s) => ($s['score'] ?? 100) < 100)->count() : 0;
    // Progreso de la mejora (Capítulo X/Y)
    $capActual = 0; $capTotal = 16;
    if ($mejorando && preg_match('/Cap[ií]tulo\s+(\d+)\s*\/\s*(\d+)/iu', $a->progreso_mejora ?? '', $m)) {
        $capActual = (int) $m[1]; $capTotal = (int) $m[2];
    }
    $pctMejora = $capTotal > 0 ? min(100, round($capActual / $capTotal * 100)) : 0;
@endphp

@verbatim
<style>
.sl-wrap{display:flex;flex-direction:column;gap:1rem}
.sl-viewer{border-radius:1rem;border:1px solid rgba(255,255,255,.09);overflow:hidden}
html:not(.dark) .sl-viewer{border-color:rgba(0,0,0,.08);box-shadow:0 2px 12px rgba(0,0,0,.06)}
.sl-vh{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.125rem;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04)}
html:not(.dark) .sl-vh{background:rgba(0,0,0,.03);border-bottom-color:rgba(0,0,0,.07)}
.sl-vl{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
.sl-vb{padding:1.5rem 1.75rem;background:rgba(0,0,0,.15)}
html:not(.dark) .sl-vb{background:#fafafa}
.sl-ring{width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:800;border:5px solid;flex-shrink:0}
.sl-title{font-size:1rem;font-weight:700;color:#f1f5f9;margin:0 0 .25rem}
html:not(.dark) .sl-title{color:#1c1917}
.sl-muted{font-size:.8125rem;color:#64748b;line-height:1.6}
.sl-sec{border-radius:.875rem;padding:1.125rem 1.25rem;border-left:3px solid;margin-bottom:.625rem;background:rgba(255,255,255,.03)}
html:not(.dark) .sl-sec{background:#fff}
.sl-sec-title{font-size:.875rem;font-weight:600;color:#f1f5f9}
html:not(.dark) .sl-sec-title{color:#1c1917}
.sl-tag{display:inline-flex;font-size:.65rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:.2rem .6rem;border-radius:.375rem}
.sl-tag-ok{background:rgba(34,197,94,.13);color:#86efac} html:not(.dark) .sl-tag-ok{background:rgba(22,163,74,.1);color:#166534}
.sl-tag-warn{background:rgba(245,158,11,.13);color:#fcd34d} html:not(.dark) .sl-tag-warn{background:rgba(217,119,6,.1);color:#92400e}
.sl-tag-danger{background:rgba(239,68,68,.13);color:#fca5a5} html:not(.dark) .sl-tag-danger{background:rgba(220,38,38,.1);color:#991b1b}
.sl-sublabel{font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#64748b;margin-bottom:.375rem}
.sl-li{display:flex;gap:.5rem;font-size:.8rem;color:#94a3b8;line-height:1.5;margin:.2rem 0}
html:not(.dark) .sl-li{color:#475569}
.sl-spin{width:16px;height:16px;flex-shrink:0;animation:slspin .8s linear infinite;color:#fb7185}
html:not(.dark) .sl-spin{color:#e11d48}
@keyframes slspin{to{transform:rotate(360deg)}}
.sl-track{width:100%;height:6px;border-radius:3px;background:rgba(255,255,255,.08);overflow:hidden;margin:.75rem 0}
html:not(.dark) .sl-track{background:rgba(0,0,0,.08)}
.sl-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,#f97316,#fb7185);transition:width .6s cubic-bezier(.4,0,.2,1)}
.sl-btn{display:inline-flex;align-items:center;gap:.5rem;font-size:.8125rem;font-weight:600;padding:.6rem 1.25rem;border-radius:.625rem;border:none;cursor:pointer;transition:all .2s}
.sl-btn-primary{background:linear-gradient(135deg,#e11d48,#f97316);color:#fff;box-shadow:0 2px 8px rgba(251,113,133,.35)}
.sl-btn-primary:hover{opacity:.92;transform:translateY(-1px)}
.sl-btn-ghost{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.16);color:#e2e8f0}
html:not(.dark) .sl-btn-ghost{background:rgba(0,0,0,.04);border-color:rgba(0,0,0,.12);color:#374151}
.sl-mcard{border-radius:1rem;overflow:hidden;border:1.5px solid rgba(251,113,133,.3);background:rgba(255,255,255,.03)}
html:not(.dark) .sl-mcard{background:#fff;border-color:rgba(225,29,72,.2);box-shadow:0 2px 16px rgba(225,29,72,.08)}
.sl-mhead{padding:1rem 1.25rem;background:linear-gradient(135deg,rgba(251,113,133,.12),rgba(225,29,72,.06));border-bottom:1px solid rgba(251,113,133,.15);display:flex;align-items:center;gap:.75rem}
html:not(.dark) .sl-mhead{background:linear-gradient(135deg,rgba(225,29,72,.07),rgba(251,113,133,.03))}
.sl-badge{display:inline-flex;align-items:center;gap:.35rem;font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:.3rem .75rem;border-radius:2rem;background:rgba(34,197,94,.13);color:#86efac}
html:not(.dark) .sl-badge{background:rgba(22,163,74,.1);color:#166534}
</style>
@endverbatim

<div class="sl-wrap" @if($enProceso || $mejorando) wire:poll.2000ms="refrescarAuditoria" @endif>

    {{-- ── Encabezado con lordicon + score ── --}}
    <div style="display:flex;align-items:center;gap:.85rem">
        <lord-icon src="https://cdn.lordicon.com/lecprnjb.json" trigger="loop" delay="1500" stroke="bold"
            colors="primary:#fb7185,secondary:#fb7185" data-pt-dark="primary:#fb7185,secondary:#fb7185"
            data-pt-light="primary:#e11d48,secondary:#f97316" style="width:36px;height:36px;flex-shrink:0"></lord-icon>
        <div style="flex:1;min-width:0">
            <p class="sl-title" style="font-size:1.05rem;margin:0">Salud legal del Reglamento Interno</p>
            <p class="sl-muted" style="margin:.1rem 0 0">Revisión contra el CST, Ley 1010/2006, Ley 2365/2024 y la biblioteca jurídica.</p>
        </div>
        @if($completada)
            <div class="sl-ring" style="border-color:{{ $scoreColor }};color:{{ $scoreColor }}">{{ $score }}</div>
        @endif
    </div>

    {{-- ── Sin auditoría ── --}}
    @if(! $a)
        <div class="sl-viewer"><div class="sl-vb" style="text-align:center">
            <p class="sl-muted" style="margin:0 0 1rem">Aún no se ha auditado este Reglamento Interno.</p>
            <button wire:click="iniciarAuditoriaManual" class="sl-btn sl-btn-primary" style="margin:0 auto">
                <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.2-5.2m1.7-4.05a6.75 6.75 0 11-13.5 0 6.75 6.75 0 0113.5 0z"/></svg>
                Auditar ahora
            </button>
        </div></div>

    {{-- ── En proceso ── --}}
    @elseif($enProceso)
        <div class="sl-viewer"><div class="sl-vb">
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.35rem">
                <svg class="sl-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="40 20"/></svg>
                <span style="font-size:.9rem;color:#fb7185;font-weight:600">Analizando con IA - {{ $numDone }} / {{ $numTotal }} secciones</span>
            </div>
            <div class="sl-track"><div class="sl-fill" style="width:{{ $numTotal ? round($numDone/$numTotal*100) : 0 }}%"></div></div>
            <p class="sl-muted" style="margin:.25rem 0 0">Revisando su reglamento contra la normativa vigente colombiana. Por favor espere…</p>
        </div></div>

    {{-- ── Error ── --}}
    @elseif($errorAud)
        <div class="sl-viewer"><div class="sl-vb">
            <p class="sl-title" style="font-size:.9rem;color:#f87171">No se pudo completar la auditoría</p>
            <p class="sl-muted" style="margin:.25rem 0 1rem">Puede reintentarla en unos segundos.</p>
            <button wire:click="iniciarAuditoriaManual" class="sl-btn sl-btn-primary">Reintentar auditoría</button>
        </div></div>

    {{-- ── Completada ── --}}
    @elseif($completada)
        {{-- Resultado general --}}
        <div class="sl-viewer">
            <div class="sl-vh"><span class="sl-vl">Resultado general</span>
                <span style="font-size:.75rem;color:#64748b">{{ $a->updated_at->format('d/m/Y g:i A') }}</span></div>
            <div class="sl-vb">
                <div style="display:flex;align-items:flex-start;gap:1.5rem">
                    <div class="sl-ring" style="border-color:{{ $scoreColor }};color:{{ $scoreColor }}">{{ $score }}</div>
                    <div style="flex:1;min-width:0">
                        <p class="sl-title">
                            @if($score >= 80) Reglamento jurídicamente actualizado
                            @elseif($score >= 65) Reglamento aprobado - con sugerencias de mejora
                            @elseif($score >= 50) Reglamento con observaciones
                            @else Reglamento requiere revisión urgente @endif
                        </p>
                        @if($a->resumen_general)<p class="sl-muted" style="white-space:pre-line">{{ $a->resumen_general }}</p>@endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Detalle por sección --}}
        <div class="sl-viewer">
            <div class="sl-vh"><span class="sl-vl">Detalle por sección</span>
                <span style="font-size:.75rem;color:#64748b">{{ $numDone }} secciones revisadas</span></div>
            <div class="sl-vb">
                @foreach($secciones as $clave => $sec)
                    @php
                        $calif = $sec['calificacion'] ?? 'Ausente';
                        $bord  = $calif === 'Completo' ? '#22c55e' : ($calif === 'Parcial' ? '#f59e0b' : '#ef4444');
                        $tag   = $calif === 'Completo' ? 'sl-tag-ok' : ($calif === 'Parcial' ? 'sl-tag-warn' : 'sl-tag-danger');
                    @endphp
                    <div class="sl-sec" style="border-color:{{ $bord }}">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:.5rem">
                            <span class="sl-sec-title">{{ $sec['titulo'] ?? $clave }}</span>
                            <div style="display:flex;align-items:center;gap:.5rem">
                                <span style="font-size:.75rem;font-weight:700;color:{{ $bord }}">{{ $sec['score'] ?? 0 }}/100</span>
                                <span class="sl-tag {{ $tag }}">{{ $calif }}</span>
                            </div>
                        </div>
                        @if(!empty($sec['hallazgos']))
                            <p class="sl-sublabel">Hallazgos</p>
                            @foreach($sec['hallazgos'] as $h)<div class="sl-li"><span style="flex-shrink:0;color:#f97316">›</span>{{ $h }}</div>@endforeach
                        @endif
                        @if(!empty($sec['recomendaciones']))
                            <p class="sl-sublabel" style="margin-top:.625rem">Recomendaciones</p>
                            @foreach($sec['recomendaciones'] as $r)<div class="sl-li"><span style="flex-shrink:0;color:#22c55e">→</span>{{ $r }}</div>@endforeach
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── Mejora en proceso ── --}}
        @if($mejorando)
            <div class="sl-mcard"><div style="padding:1.25rem 1.5rem">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
                    <svg class="sl-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="40 20"/></svg>
                    <div style="flex:1"><p class="sl-title" style="font-size:.95rem;color:#fb7185;margin:0">Generando su RIT mejorado</p>
                        <p class="sl-muted" style="margin:.1rem 0 0">{{ $a->progreso_mejora ?: 'Iniciando mejora capítulo por capítulo…' }}</p></div>
                    @if($capActual > 0)<span style="font-size:1.1rem;font-weight:800;color:#fb7185">{{ $capActual }}<span style="font-size:.75rem;color:#94a3b8">/{{ $capTotal }}</span></span>@endif
                </div>
                <div class="sl-track"><div class="sl-fill" style="width:{{ $pctMejora }}%"></div></div>
            </div></div>

        {{-- ── Mejora lista ── --}}
        @elseif($mejoraLista)
            <div class="sl-mcard">
                <div class="sl-mhead">
                    <lord-icon src="https://cdn.lordicon.com/xjsqfzte.json" trigger="loop" delay="1500" stroke="bold"
                        colors="primary:#fb7185,secondary:#fb7185" data-pt-dark="primary:#fb7185,secondary:#fb7185"
                        data-pt-light="primary:#e11d48,secondary:#f97316" style="width:28px;height:28px;flex-shrink:0"></lord-icon>
                    <div style="flex:1;min-width:0">
                        <p class="sl-title" style="font-size:.9rem;margin:0">RIT mejorado generado</p>
                        <p class="sl-muted" style="font-size:.75rem;margin:0">Versión {{ $rm->version }} · {{ $rm->created_at->format('d/m/Y g:i A') }}</p>
                    </div>
                    @if($decision === 'adoptado')<span class="sl-badge">RIT vigente</span>@endif
                </div>
                <div style="padding:1.25rem 1.5rem">
                    <p class="sl-muted" style="margin:0 0 1rem">
                        @if($numCorreg > 0)
                            Se {{ $numCorreg === 1 ? 'ajustó' : 'ajustaron' }} {{ $numCorreg }} {{ $numCorreg === 1 ? 'sección' : 'secciones' }} conforme a los hallazgos de la auditoría y la normativa vigente, conservando el resto de su reglamento.
                        @else
                            Documento actualizado conforme a la auditoría y la normativa vigente, conservando su reglamento.
                        @endif
                    </p>
                    <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
                        <button wire:click="downloadPDFMejorado" class="sl-btn sl-btn-primary">
                            <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            Descargar PDF v{{ $rm->version }}
                        </button>
                        {{ $this->verCambiosRITAction() }}
                    </div>

                    @if($mejoraPend)
                        <div style="border-top:1px dashed rgba(251,113,133,.25);margin-top:1.125rem;padding-top:1.125rem">
                            <p class="sl-title" style="font-size:.9rem">¿Desea utilizar este RIT mejorado?</p>
                            <p class="sl-muted" style="margin:.25rem 0 1rem">Revise el documento. Si lo aprueba, reemplazará su Reglamento Interno vigente; si prefiere conservar el actual, la versión mejorada quedará archivada.</p>
                            <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
                                {{ $this->aceptarSugerenciasRITAction }}
                                <button wire:click="mantenerRITActual" class="sl-btn sl-btn-ghost">Mantener mi RIT actual</button>
                            </div>
                        </div>
                    @elseif($decision === 'rechazado')
                        <p class="sl-muted" style="margin:1rem 0 0">Conservó su RIT actual. La versión mejorada quedó archivada.</p>
                    @endif
                </div>
            </div>

        {{-- ── Mejora falló ── --}}
        @elseif($mejoraFallo)
            <div class="sl-viewer"><div class="sl-vb" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                <p class="sl-muted" style="margin:0;color:#f87171">No se pudo generar el RIT mejorado. Puede reintentarlo.</p>
                <button wire:click="reintentarMejora" class="sl-btn sl-btn-ghost">Reintentar</button>
            </div></div>
        @endif
    @endif
</div>
