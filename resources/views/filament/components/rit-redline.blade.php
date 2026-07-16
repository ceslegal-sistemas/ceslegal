@php
    /**
     * Redline del RIT Mejorado: verde = agregado, rojo (tachado) = eliminado,
     * amarillo = modificado (con el detalle exacto de palabras dentro).
     * Espera $cambios = App\Services\RitDiffService::compararDocumentos(...).
     */
    $cambios = $cambios ?? [];
    $hayCambios = collect($cambios)->contains(fn ($c) => $c['tipo'] !== 'igual');
@endphp

@verbatim
<style>
.rl-wrap{max-height:65vh;overflow-y:auto;padding:.25rem .25rem .25rem 0}
.rl-legend{display:flex;flex-wrap:wrap;gap:.75rem;margin:0 0 1rem;padding:.65rem .9rem;border-radius:.6rem;
    background:rgba(0,0,0,.025);border:1px solid rgba(0,0,0,.06);position:sticky;top:0;z-index:1}
html.dark .rl-legend{background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.08)}
.rl-legend-item{display:flex;align-items:center;gap:.4rem;font-size:.75rem;color:#57534e}
html.dark .rl-legend-item{color:#a8a29e}
.rl-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.rl-p{font-size:.85rem;line-height:1.75;margin:0 0 .85rem;color:#44403c}
html.dark .rl-p{color:#d6d3d1}
.rl-add{color:#15803d;text-decoration:underline;text-decoration-color:rgba(21,128,61,.5);text-underline-offset:2px}
html.dark .rl-add{color:#4ade80;text-decoration-color:rgba(74,222,128,.5)}
.rl-del{color:#b91c1c;text-decoration:line-through;text-decoration-color:rgba(185,28,28,.6);opacity:.75}
html.dark .rl-del{color:#f87171;text-decoration-color:rgba(248,113,113,.6)}
.rl-mod{background:rgba(234,179,8,.08);border-left:3px solid #eab308;border-radius:.4rem;padding:.6rem .8rem;margin:0 0 .85rem}
html.dark .rl-mod{background:rgba(234,179,8,.07)}
.rl-mod p{margin:0;font-size:.85rem;line-height:1.75;color:#44403c}
html.dark .rl-mod p{color:#d6d3d1}
.rl-empty{text-align:center;padding:2rem 1rem;color:#78716c;font-size:.85rem}
html.dark .rl-empty{color:#a8a29e}
</style>
@endverbatim

<div class="rl-wrap">
    <div class="rl-legend">
        <span class="rl-legend-item"><span class="rl-dot" style="background:#22c55e"></span>Agregado</span>
        <span class="rl-legend-item"><span class="rl-dot" style="background:#ef4444"></span>Eliminado</span>
        <span class="rl-legend-item"><span class="rl-dot" style="background:#eab308"></span>Modificado</span>
    </div>

    @if(! $hayCambios)
        <p class="rl-empty">No se detectaron diferencias entre el documento original y el mejorado.</p>
    @else
        @foreach($cambios as $c)
            @if($c['tipo'] === 'igual')
                <p class="rl-p">{{ $c['texto'] }}</p>
            @elseif($c['tipo'] === 'agregado')
                <p class="rl-p"><span class="rl-add">{{ $c['texto'] }}</span></p>
            @elseif($c['tipo'] === 'eliminado')
                <p class="rl-p"><span class="rl-del">{{ $c['texto'] }}</span></p>
            @elseif($c['tipo'] === 'modificado')
                <div class="rl-mod">
                    <p>
                        @foreach($c['palabras'] as $p)
                            @if($p['tipo'] === 'agregado')<span class="rl-add">{{ $p['texto'] }}</span>@elseif($p['tipo'] === 'eliminado')<span class="rl-del">{{ $p['texto'] }}</span>@else{{ $p['texto'] }}@endif
                        @endforeach
                    </p>
                </div>
            @endif
        @endforeach
    @endif
</div>
