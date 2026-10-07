@php
    /**
     * Redline del RIT Mejorado, con filtro por categoría (pedido de Andrés
     * Sarmiento, reunión 2026-10-05: el "todo el documento con lo agregado
     * en verde" se rechazó por feo e inútil para el trabajador - ahora se
     * ve SOLO lo agregado, SOLO lo eliminado, o SOLO lo modificado, nunca
     * el documento completo con resaltado).
     * Espera $cambios = App\Services\RitDiffService::compararDocumentos(...).
     */
    $cambios = $cambios ?? [];
    $agregados = collect($cambios)->where('tipo', 'agregado')->values();
    $eliminados = collect($cambios)->where('tipo', 'eliminado')->values();
    $modificados = collect($cambios)->where('tipo', 'modificado')->values();
@endphp

@verbatim
<style>
.rl-wrap{max-height:65vh;overflow-y:auto;padding:.25rem .25rem .25rem 0}
.rl-tabs{display:flex;flex-wrap:wrap;gap:.5rem;margin:0 0 1rem;position:sticky;top:0;z-index:1;
    background:var(--rl-tabs-bg,#fff);padding:.25rem 0}
html.dark .rl-tabs{--rl-tabs-bg:#1c1917}
.rl-tab{display:flex;align-items:center;gap:.4rem;font-size:.8rem;font-weight:600;color:#57534e;
    background:rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.08);border-radius:999px;padding:.45rem .9rem;
    cursor:pointer;transition:background-color .15s,color .15s}
html.dark .rl-tab{color:#d6d3d1;background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.1)}
.rl-tab:hover{background:rgba(0,0,0,.08)}
html.dark .rl-tab:hover{background:rgba(255,255,255,.09)}
.rl-tab-activo{background:#292524;color:#fff;border-color:#292524}
html.dark .rl-tab-activo{background:#f5f5f4;color:#1c1917;border-color:#f5f5f4}
.rl-tab-activo:hover{background:#292524}
html.dark .rl-tab-activo:hover{background:#f5f5f4}
.rl-tab-count{font-size:.7rem;background:rgba(255,255,255,.2);border-radius:999px;padding:.05rem .4rem}
.rl-tab:not(.rl-tab-activo) .rl-tab-count{background:rgba(0,0,0,.08);color:#57534e}
html.dark .rl-tab:not(.rl-tab-activo) .rl-tab-count{background:rgba(255,255,255,.08);color:#d6d3d1}
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

<div class="rl-wrap" x-data="{ filtro: 'agregado' }">
    <div class="rl-tabs">
        <button type="button" @click="filtro = 'agregado'" class="rl-tab" :class="filtro === 'agregado' ? 'rl-tab-activo' : ''">
            Agregado <span class="rl-tab-count">{{ $agregados->count() }}</span>
        </button>
        <button type="button" @click="filtro = 'eliminado'" class="rl-tab" :class="filtro === 'eliminado' ? 'rl-tab-activo' : ''">
            Eliminado <span class="rl-tab-count">{{ $eliminados->count() }}</span>
        </button>
        <button type="button" @click="filtro = 'modificado'" class="rl-tab" :class="filtro === 'modificado' ? 'rl-tab-activo' : ''">
            Modificado <span class="rl-tab-count">{{ $modificados->count() }}</span>
        </button>
    </div>

    <div x-show="filtro === 'agregado'">
        @if($agregados->isEmpty())
            <p class="rl-empty">No se agregó nada nuevo.</p>
        @else
            @foreach($agregados as $c)
                <p class="rl-p"><span class="rl-add">{{ $c['texto'] }}</span></p>
            @endforeach
        @endif
    </div>

    <div x-show="filtro === 'eliminado'" style="display:none">
        @if($eliminados->isEmpty())
            <p class="rl-empty">No se eliminó nada.</p>
        @else
            @foreach($eliminados as $c)
                <p class="rl-p"><span class="rl-del">{{ $c['texto'] }}</span></p>
            @endforeach
        @endif
    </div>

    <div x-show="filtro === 'modificado'" style="display:none">
        @if($modificados->isEmpty())
            <p class="rl-empty">No se modificó nada.</p>
        @else
            @foreach($modificados as $c)
                <div class="rl-mod">
                    <p>
                        @foreach($c['palabras'] as $p)
                            @if($p['tipo'] === 'agregado')<span class="rl-add">{{ $p['texto'] }}</span>@elseif($p['tipo'] === 'eliminado')<span class="rl-del">{{ $p['texto'] }}</span>@else{{ $p['texto'] }}@endif
                        @endforeach
                    </p>
                </div>
            @endforeach
        @endif
    </div>
</div>
