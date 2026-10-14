{{--
    Tarjeta "Puntos a revisar" (verificación de garantías del debido proceso) -
    extraída de emitir-sancion-analisis.blade.php (2026-10-14, pedido del
    usuario) para poder ubicarla junto a "Revisión de calidad de la
    recomendación" en una fila de 2 columnas (.rit-two-col) en el Paso 1 del
    wizard de Emitir Sanción, sin duplicar el marcado: emitir-sancion-analisis
    sigue incluyendo este mismo partial para el Paso 2 (y cualquier otro
    llamador), donde se ve a ancho completo como siempre.

    Variable esperada: $garantias (array, forma de
    $analisis['verificacion_garantias']).
--}}
@php
    $garantias = $garantias ?? [];
    $gEtiquetas = ['tipicidad' => '¿Es una falta del reglamento?', 'debido_proceso' => '¿Se respetó el debido proceso?', 'inmediatez' => '¿Se actuó a tiempo?', 'non_bis_in_idem' => '¿Ya fue sancionado por lo mismo?', 'proporcionalidad' => '¿La sanción es proporcional?', 'suficiencia_probatoria' => '¿Hay pruebas suficientes?'];
    // Solo se muestran las garantías que NO se cumplen (riesgo / por verificar).
    $gRiesgos = collect($gEtiquetas)->filter(fn($lbl, $k) => isset($garantias[$k]) && ($garantias[$k]['estado'] ?? '') !== 'cumple');
@endphp
<div class="rit-hero" style="padding:1.5rem 1.5rem;">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">
        @if($gRiesgos->isEmpty())
            <span class="rit-badge rit-badge-sub">
                <lord-icon src="https://cdn.lordicon.com/lvrxlmju.json" trigger="loop" delay="500" colors="primary:#86efac,secondary:#86efac" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
                Puntos a revisar
            </span>
            <h1 class="rit-title">Todas las garantías se cumplen</h1>
            <p class="rit-sub">No hay puntos de riesgo identificados en el debido proceso.</p>
        @else
            <span class="rit-badge rit-badge-danger">
                <lord-icon src="https://cdn.lordicon.com/lltgvngb.json" trigger="loop" delay="500" colors="primary:#fca5a5,secondary:#fca5a5" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
                Puntos a revisar
            </span>
            <h1 class="rit-title">{{ $gRiesgos->count() }} de {{ count($gEtiquetas) }} garantías necesita{{ $gRiesgos->count() === 1 ? '' : 'n' }} su atención</h1>
            <p class="rit-sub">
                @foreach($gRiesgos as $gk => $glabel)
                    @php $nota = $garantias[$gk]['nota'] ?? ''; @endphp
                    {{ $glabel }}{{ $nota ? ' - ' . $nota : '' }}{{ !$loop->last ? '. ' : '.' }}
                @endforeach
            </p>
        @endif
    </div>
</div>
