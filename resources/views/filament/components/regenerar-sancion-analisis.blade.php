@include('filament.components.lupe-hero-styles')
{{--
    Tarjeta "Gravedad de la falta" para el modal "Re-generar Sanción"
    (Tables\Actions\Action::make('regenerar_sancion') en ProcesoDisciplinarioResource.php).

    Reemplaza el Forms\Components\Section con emojis (🤖🟢🔴⚠️) por la MISMA
    tarjeta .rit-hero/rit-badge usada en el modal "Emitir Sanción"
    (ver "Tarjeta 1: Gravedad de la falta" en emitir-sancion-analisis.blade.php) -
    pedido del usuario 2026-10-14: "nada de emojis ni nada de eso, ponlo como
    el modal de emisión de sanción".

    Variable esperada: $analisis (array) con 'gravedad', 'es_reincidencia', 'justificacion'.
--}}
@php
    $analisis       = $analisis ?? [];
    $gravedad       = $analisis['gravedad'] ?? 'leve';
    $esReincidencia = $analisis['es_reincidencia'] ?? false;
    $justificacion  = $analisis['justificacion'] ?? '';

    $gc = match(true) {
        $gravedad === 'muy_grave' => [
            'label'      => 'Falta Muy Grave',
            'lord'       => 'hmpomorl.json',
            'lordColors' => 'primary:#f87171,secondary:#fca5a5',
            'badge'      => 'rit-badge-danger',
        ],
        $gravedad === 'grave' => [
            'label'      => 'Falta Grave',
            'lord'       => 'hmpomorl.json',
            'lordColors' => 'primary:#fbbf24,secondary:#fde68a',
            'badge'      => 'rit-badge-warning',
        ],
        default => [
            'label'      => 'Falta Leve',
            'lord'       => 'fikcyfpp.json',
            'lordColors' => 'primary:#4ade80,secondary:#86efac',
            'badge'      => 'rit-badge-sub',
        ],
    };
@endphp

<div class="rit-hero" style="padding:1.25rem 1.5rem;">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2;display:flex;align-items:flex-start;gap:12px;">
        <lord-icon
            src="https://cdn.lordicon.com/{{ $gc['lord'] }}"
            trigger="loop" delay="900" stroke="bold"
            colors="{{ $gc['lordColors'] }}"
            style="width:40px;height:40px;flex-shrink:0;margin-top:-2px">
        </lord-icon>

        <div style="flex:1;min-width:0;">
            <span class="rit-badge {{ $gc['badge'] }}">
                Análisis IA - Gravedad de la falta
            </span>

            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:.5rem;">
                <h1 class="rit-title" style="margin:0;">{{ $gc['label'] }}</h1>
                @if($esReincidencia)
                    <span class="esa-badge-reincidencia" style="padding:2px 10px;border-radius:100px;font-size:11px;font-weight:700;background:rgba(239,68,68,0.12);color:#b91c1c;border:1px solid rgba(239,68,68,0.35);">
                        Reincidencia
                    </span>
                @endif
            </div>

            @if($justificacion)
                <p class="rit-sub" style="margin-top:.5rem;">{{ $justificacion }}</p>
            @endif
        </div>
    </div>
</div>
