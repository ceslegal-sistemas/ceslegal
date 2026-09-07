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

    $estadoColor = fn(string $estado) => match ($estado) {
        'aprobada' => '#15803d',
        'rechazada' => '#b91c1c',
        default => '#a16207',
    };
    $estadoBg = fn(string $estado) => match ($estado) {
        'aprobada' => 'rgba(34,197,94,.1)',
        'rechazada' => 'rgba(239,68,68,.1)',
        default => 'rgba(234,179,8,.1)',
    };
@endphp

@if($intro ?? null)
    <div style="padding:.75rem 1rem;border-radius:.5rem;background:rgba(217,119,6,.08);border:1px solid rgba(217,119,6,.2);font-size:.8125rem;color:#92400e;margin-bottom:1rem">
        {{ $intro }}
    </div>
@endif

@if($sugerencias->isEmpty())
    <p style="font-size:.8125rem;color:#64748b;text-align:center;padding:1.5rem 0">Este documento todavía no generó ninguna sugerencia de actualización.</p>
@else
    <div style="max-height:55vh;overflow-y:auto">
        @foreach($sugerencias as $empresaId => $grupo)
            @php $empresa = $grupo->first()->empresa; @endphp
            <div style="margin-bottom:1.1rem;padding-bottom:1.1rem;border-bottom:1px solid rgba(148,163,184,.2)">
                <p style="font-size:.85rem;font-weight:700;margin:0 0 .5rem;color:#1e293b">
                    {{ $empresa?->razon_social ?? 'Empresa eliminada' }}
                    <span style="font-weight:400;color:#94a3b8;font-size:.75rem">({{ $grupo->count() }} {{ Str::plural('sugerencia', $grupo->count()) }})</span>
                </p>
                @foreach($grupo as $sugerencia)
                    <div style="display:flex;align-items:flex-start;gap:.6rem;padding:.4rem 0;font-size:.8125rem">
                        <span style="flex-shrink:0;padding:.1rem .55rem;border-radius:999px;font-size:.7rem;font-weight:700;color:{{ $estadoColor($sugerencia->estado) }};background:{{ $estadoBg($sugerencia->estado) }}">
                            {{ ucfirst($sugerencia->estado) }}
                        </span>
                        <div style="flex:1">
                            <span style="color:#334155">{{ \App\Models\SugerenciaActualizacionRit::TIPOS_CAMBIO[$sugerencia->tipo_cambio] ?? $sugerencia->tipo_cambio }}</span>
                            <span style="color:#94a3b8"> — {{ $sugerencia->created_at->format('d/m/Y') }}</span>
                            @if($sugerencia->estado !== 'pendiente' && $sugerencia->resuelto_en)
                                <span style="color:#94a3b8">, {{ $sugerencia->estado === 'aprobada' ? 'aplicada' : 'rechazada' }} el {{ $sugerencia->resuelto_en->format('d/m/Y') }}@if($sugerencia->resueltoPor) por {{ $sugerencia->resueltoPor->name }}@endif</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endif
