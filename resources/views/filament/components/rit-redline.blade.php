@php
    /**
     * Redline del RIT Mejorado, con filtro por categoría (pedido de Andrés
     * Sarmiento, reunión 2026-10-05: el "todo el documento con lo agregado
     * en verde" se rechazó por feo e inútil para el trabajador - ahora se
     * ve SOLO lo agregado, SOLO lo eliminado, o SOLO lo modificado, nunca
     * el documento completo con resaltado).
     * Espera $cambios = App\Services\RitDiffService::compararDocumentos(...).
     *
     * Rediseño 2026-10-08: cada cambio es una tarjeta con barra de color a la
     * izquierda (en vez de todo el texto subrayado), el texto conserva el
     * color normal para poder leerse, las pestañas usan la marca de Lupe y las
     * listas largas se muestran de a 10 con "Ver más" (un RIT con anexos
     * legales extensos llega a cientos de párrafos).
     */
    $cambios = $cambios ?? [];
    // Se descartan los párrafos vacíos (el texto fuente trae saltos de línea
    // sueltos): una tarjeta en blanco no informa nada.
    $conTexto = fn ($c) => trim((string) ($c['texto'] ?? '')) !== '';
    $agregados = collect($cambios)->where('tipo', 'agregado')->filter($conTexto)->values();
    $eliminados = collect($cambios)->where('tipo', 'eliminado')->filter($conTexto)->values();
    $modificados = collect($cambios)->where('tipo', 'modificado')->values();
    $tramo = 10;
@endphp

@verbatim
<style>
.rl-tabs{display:flex;flex-wrap:wrap;gap:.5rem;margin:0 0 1rem}
.rl-tab{display:flex;align-items:center;gap:.45rem;font-size:.8rem;font-weight:600;color:#57534e;
    background:rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.1);border-radius:999px;padding:.5rem 1rem;
    cursor:pointer;transition:background-color .15s,color .15s,border-color .15s}
html.dark .rl-tab{color:#d6d3d1;background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.14)}
.rl-tab:hover{background:rgba(0,0,0,.08)}
html.dark .rl-tab:hover{background:rgba(255,255,255,.1)}
.rl-tab-activo,.rl-tab-activo:hover{background:rgba(225,29,72,.1);border-color:rgba(225,29,72,.28);color:#be123c}
html.dark .rl-tab-activo,html.dark .rl-tab-activo:hover{background:rgba(251,113,133,.18);border-color:rgba(251,113,133,.35);color:#fecdd3}
.rl-tab-count{font-size:.7rem;background:rgba(0,0,0,.08);color:#57534e;border-radius:999px;padding:.05rem .45rem}
html.dark .rl-tab-count{background:rgba(255,255,255,.1);color:#d6d3d1}
.rl-tab-activo .rl-tab-count{background:rgba(225,29,72,.14);color:#be123c}
html.dark .rl-tab-activo .rl-tab-count{background:rgba(251,113,133,.25);color:#fecdd3}
.rl-hint{font-size:.8rem;line-height:1.55;color:#78716c;margin:0 0 1rem}
html.dark .rl-hint{color:#a8a29e}
.rl-list{display:flex;flex-direction:column;gap:.6rem}
.rl-card{border-radius:.8rem;padding:.8rem 1rem;border:1px solid rgba(0,0,0,.07);border-left-width:4px;
    background:rgba(0,0,0,.02);font-size:.86rem;line-height:1.7;color:#292524;margin:0}
html.dark .rl-card{border-color:rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:#e7e5e4}
.rl-card-add{border-left-color:#16a34a}
.rl-card-del{border-left-color:#dc2626;color:#78716c;text-decoration:line-through;text-decoration-color:rgba(220,38,38,.55)}
html.dark .rl-card-del{color:#a8a29e}
.rl-card-mod{border-left-color:#d97706}
.rl-add{background:rgba(22,163,74,.14);border-radius:.25rem;padding:0 .15rem}
.rl-del{background:rgba(220,38,38,.12);color:#b91c1c;text-decoration:line-through;border-radius:.25rem;padding:0 .15rem}
html.dark .rl-del{color:#fca5a5}
.rl-mas{display:flex;justify-content:center;margin-top:.9rem}
.rl-empty{text-align:center;padding:2rem 1rem;color:#78716c;font-size:.85rem}
html.dark .rl-empty{color:#a8a29e}
</style>
@endverbatim

<div x-data="{ filtro: 'agregado', visibles: { agregado: {{ $tramo }}, eliminado: {{ $tramo }}, modificado: {{ $tramo }} } }">
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
            @if($agregados->count() > 30)
                <p class="rl-hint">Son muchos cambios. Si prefieres entenderlos rápido, usa el resumen ejecutivo que aparece más abajo.</p>
            @endif
            <div class="rl-list">
                @foreach($agregados as $c)
                    <p class="rl-card rl-card-add" @if($loop->index >= $tramo) x-show="{{ $loop->index }} < visibles.agregado" style="display:none" @endif>{{ $c['texto'] }}</p>
                @endforeach
            </div>
            @if($agregados->count() > $tramo)
                <div class="rl-mas" x-show="visibles.agregado < {{ $agregados->count() }}">
                    <button type="button" class="rit-btn rit-btn-secondary" @click="visibles.agregado += {{ $tramo }}">Ver más cambios</button>
                </div>
            @endif
        @endif
    </div>

    <div x-show="filtro === 'eliminado'" style="display:none">
        @if($eliminados->isEmpty())
            <p class="rl-empty">No se eliminó nada.</p>
        @else
            <div class="rl-list">
                @foreach($eliminados as $c)
                    <p class="rl-card rl-card-del" @if($loop->index >= $tramo) x-show="{{ $loop->index }} < visibles.eliminado" style="display:none" @endif>{{ $c['texto'] }}</p>
                @endforeach
            </div>
            @if($eliminados->count() > $tramo)
                <div class="rl-mas" x-show="visibles.eliminado < {{ $eliminados->count() }}">
                    <button type="button" class="rit-btn rit-btn-secondary" @click="visibles.eliminado += {{ $tramo }}">Ver más cambios</button>
                </div>
            @endif
        @endif
    </div>

    <div x-show="filtro === 'modificado'" style="display:none">
        @if($modificados->isEmpty())
            <p class="rl-empty">No se modificó nada.</p>
        @else
            <div class="rl-list">
                @foreach($modificados as $c)
                    <p class="rl-card rl-card-mod" @if($loop->index >= $tramo) x-show="{{ $loop->index }} < visibles.modificado" style="display:none" @endif>
                        @foreach($c['palabras'] as $p)
                            @if($p['tipo'] === 'agregado')<span class="rl-add">{{ $p['texto'] }}</span>@elseif($p['tipo'] === 'eliminado')<span class="rl-del">{{ $p['texto'] }}</span>@else{{ $p['texto'] }}@endif
                        @endforeach
                    </p>
                @endforeach
            </div>
            @if($modificados->count() > $tramo)
                <div class="rl-mas" x-show="visibles.modificado < {{ $modificados->count() }}">
                    <button type="button" class="rit-btn rit-btn-secondary" @click="visibles.modificado += {{ $tramo }}">Ver más cambios</button>
                </div>
            @endif
        @endif
    </div>
</div>
