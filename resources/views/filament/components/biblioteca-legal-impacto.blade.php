@php
    /**
     * Trazabilidad de impacto de un DocumentoLegal (2026-09-07): responde a
     * "si este documento generó cambios ya aprobados en el RIT de un
     * cliente y luego resulta que era el documento equivocado (o se
     * desactiva), ¿quién se vio afectado?". No revierte nada - solo hace
     * visible el radio de impacto para que el abogado decida manualmente
     * qué corregir. Ver memoria: rit-anexo-completo-vs-quirurgico.md.
     */
    $sugerencias = $documento->sugerencias()
        ->withoutGlobalScopes()
        ->with(['empresa', 'resueltoPor'])
        ->latest()
        ->get()
        ->groupBy('empresa_id');

    $estadoClase = fn(string $estado) => match ($estado) {
        'aprobada' => 'bli-estado-aprobada',
        'rechazada' => 'bli-estado-rechazada',
        default => 'bli-estado-pendiente',
    };
@endphp

<style>
/* Bug real reportado por el usuario (2026-09-07): los colores de texto
   estaban fijos para modo claro (ej. #1e293b) - en modo oscuro (el
   default de este panel) el texto quedaba casi invisible sobre el fondo
   oscuro del modal. */
.bli-intro{padding:.75rem 1rem;border-radius:.5rem;background:rgba(217,119,6,.08);border:1px solid rgba(217,119,6,.25);font-size:.8125rem;color:#92400e;margin-bottom:1rem}
html.dark .bli-intro{color:#fbbf24}
.bli-vacio{font-size:.8125rem;color:#64748b;text-align:center;padding:1.5rem 0}
html.dark .bli-vacio{color:#94a3b8}
.bli-grupo{margin-bottom:1.1rem;padding-bottom:1.1rem;border-bottom:1px solid rgba(148,163,184,.2)}
.bli-empresa{font-size:.85rem;font-weight:700;margin:0 0 .5rem;color:#1e293b}
html.dark .bli-empresa{color:#f1f5f9}
.bli-empresa-count{font-weight:400;color:#94a3b8;font-size:.75rem}
.bli-fila{display:flex;align-items:flex-start;gap:.6rem;padding:.4rem 0;font-size:.8125rem}
.bli-badge{flex-shrink:0;padding:.1rem .55rem;border-radius:999px;font-size:.7rem;font-weight:700}
.bli-estado-aprobada{color:#15803d;background:rgba(34,197,94,.12)}
html.dark .bli-estado-aprobada{color:#86efac;background:rgba(34,197,94,.18)}
.bli-estado-rechazada{color:#b91c1c;background:rgba(239,68,68,.12)}
html.dark .bli-estado-rechazada{color:#fca5a5;background:rgba(239,68,68,.18)}
.bli-estado-pendiente{color:#a16207;background:rgba(234,179,8,.12)}
html.dark .bli-estado-pendiente{color:#fde68a;background:rgba(234,179,8,.18)}
.bli-detalle{color:#334155}
html.dark .bli-detalle{color:#e2e8f0}
.bli-meta{color:#94a3b8}
</style>

@if($intro ?? null)
    <div class="bli-intro">{{ $intro }}</div>
@endif

@if($sugerencias->isEmpty())
    <p class="bli-vacio">Este documento todavía no generó ninguna sugerencia de actualización.</p>
@else
    <div style="max-height:55vh;overflow-y:auto">
        @foreach($sugerencias as $empresaId => $grupo)
            @php $empresa = $grupo->first()->empresa; @endphp
            <div class="bli-grupo">
                <p class="bli-empresa">
                    {{ $empresa?->razon_social ?? 'Empresa eliminada' }}
                    <span class="bli-empresa-count">({{ $grupo->count() }} {{ Str::plural('sugerencia', $grupo->count()) }})</span>
                </p>
                @foreach($grupo as $sugerencia)
                    <div class="bli-fila">
                        <span class="bli-badge {{ $estadoClase($sugerencia->estado) }}">
                            {{ ucfirst($sugerencia->estado) }}
                        </span>
                        <div style="flex:1">
                            <span class="bli-detalle">{{ \App\Models\SugerenciaActualizacionRit::TIPOS_CAMBIO[$sugerencia->tipo_cambio] ?? $sugerencia->tipo_cambio }}</span>
                            <span class="bli-meta"> — {{ $sugerencia->created_at->format('d/m/Y') }}</span>
                            @if($sugerencia->estado !== 'pendiente' && $sugerencia->resuelto_en)
                                <span class="bli-meta">, {{ $sugerencia->estado === 'aprobada' ? 'aplicada' : 'rechazada' }} el {{ $sugerencia->resuelto_en->format('d/m/Y') }}@if($sugerencia->resueltoPor) por {{ $sugerencia->resueltoPor->name }}@endif</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endif
