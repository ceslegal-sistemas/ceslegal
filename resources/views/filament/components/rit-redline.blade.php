@php
    /**
     * Redline del RIT Mejorado, con filtro por categoría (pedido de Andrés
     * Sarmiento, reunión 2026-10-05: el "todo el documento con lo agregado
     * en verde" se rechazó por feo e inútil para el trabajador - ahora se
     * ve SOLO lo agregado, SOLO lo eliminado, o SOLO lo modificado, nunca
     * el documento completo con resaltado).
     * Espera $cambios = App\Services\RitDiffService::compararDocumentos(...).
     * Opcional: $pista = frase que se muestra cuando hay muchos cambios.
     *
     * Rediseño 2026-10-08 (corregido): el texto se lee CORRIDO, como en el
     * visor de documento de "Mi Reglamento Interno" (serif, interlineado
     * amplio, sin tarjetas ni barras de color) - la pestaña activa ya dice si
     * es agregado/eliminado, no hace falta marcar cada párrafo. Las listas
     * largas se muestran de a 10 con "Ver más".
     *
     * Solo presentación: el diff sigue siendo línea a línea; aquí se unen las
     * frases que la página de origen partió en dos líneas y se omiten los
     * menús web y los párrafos vacíos (el texto completo sigue disponible en
     * "Ver el Reglamento completo").
     */
    $cambios = $cambios ?? [];

    $parrafos = fn (string $tipo): array => \App\Support\TextoAnexoLegal::parrafosDeLineas(
        collect($cambios)->where('tipo', $tipo)->pluck('texto')->all()
    );

    $agregados = $parrafos('agregado');
    $eliminados = $parrafos('eliminado');
    $modificados = collect($cambios)->where('tipo', 'modificado')->values();
    $tramo = 10;
@endphp

@verbatim
<style>
.rl-tabs{display:flex;flex-wrap:wrap;gap:.5rem;margin:0 0 1.1rem}
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
html.dark .rl-hint{color:#94a3b8}
/* Mismo tratamiento tipográfico que el visor de documento (.rit-text) */
.rl-p{font-family:'Georgia','Times New Roman',serif;font-size:.875rem;line-height:1.9;color:#292524;
    margin:0 0 .85rem;word-break:break-word}
html.dark .rl-p{color:#cbd5e1}
.rl-p-del{color:#78716c;text-decoration:line-through;text-decoration-color:rgba(220,38,38,.55)}
html.dark .rl-p-del{color:#94a3b8}
.rl-add{background:rgba(22,163,74,.14);border-radius:.2rem;padding:0 .1rem}
.rl-del{background:rgba(220,38,38,.12);color:#b91c1c;text-decoration:line-through;border-radius:.2rem;padding:0 .1rem}
html.dark .rl-del{color:#fca5a5}
.rl-mas{display:flex;justify-content:center;margin-top:1rem}
.rl-empty{text-align:center;padding:2rem 1rem;color:#78716c;font-size:.85rem}
html.dark .rl-empty{color:#94a3b8}
</style>
@endverbatim

<div x-data="{ filtro: 'agregado', visibles: { agregado: {{ $tramo }}, eliminado: {{ $tramo }}, modificado: {{ $tramo }} } }">
    <div class="rl-tabs">
        <button type="button" @click="filtro = 'agregado'" class="rl-tab" :class="filtro === 'agregado' ? 'rl-tab-activo' : ''">
            Agregado <span class="rl-tab-count">{{ count($agregados) }}</span>
        </button>
        <button type="button" @click="filtro = 'eliminado'" class="rl-tab" :class="filtro === 'eliminado' ? 'rl-tab-activo' : ''">
            Eliminado <span class="rl-tab-count">{{ count($eliminados) }}</span>
        </button>
        <button type="button" @click="filtro = 'modificado'" class="rl-tab" :class="filtro === 'modificado' ? 'rl-tab-activo' : ''">
            Modificado <span class="rl-tab-count">{{ $modificados->count() }}</span>
        </button>
    </div>

    <div x-show="filtro === 'agregado'">
        @if(empty($agregados))
            <p class="rl-empty">No se agregó nada nuevo.</p>
        @else
            @if(($pista ?? '') !== '' && count($agregados) > 30)
                <p class="rl-hint">{{ $pista }}</p>
            @endif
            @foreach($agregados as $texto)
                <p class="rl-p" @if($loop->index >= $tramo) x-show="{{ $loop->index }} < visibles.agregado" style="display:none" @endif>{{ $texto }}</p>
            @endforeach
            @if(count($agregados) > $tramo)
                <div class="rl-mas" x-show="visibles.agregado < {{ count($agregados) }}">
                    <button type="button" class="rit-btn rit-btn-secondary" @click="visibles.agregado += {{ $tramo }}">Ver más</button>
                </div>
            @endif
        @endif
    </div>

    <div x-show="filtro === 'eliminado'" style="display:none">
        @if(empty($eliminados))
            <p class="rl-empty">No se eliminó nada.</p>
        @else
            @foreach($eliminados as $texto)
                <p class="rl-p rl-p-del" @if($loop->index >= $tramo) x-show="{{ $loop->index }} < visibles.eliminado" style="display:none" @endif>{{ $texto }}</p>
            @endforeach
            @if(count($eliminados) > $tramo)
                <div class="rl-mas" x-show="visibles.eliminado < {{ count($eliminados) }}">
                    <button type="button" class="rit-btn rit-btn-secondary" @click="visibles.eliminado += {{ $tramo }}">Ver más</button>
                </div>
            @endif
        @endif
    </div>

    <div x-show="filtro === 'modificado'" style="display:none">
        @if($modificados->isEmpty())
            <p class="rl-empty">No se modificó nada.</p>
        @else
            @foreach($modificados as $c)
                <p class="rl-p" @if($loop->index >= $tramo) x-show="{{ $loop->index }} < visibles.modificado" style="display:none" @endif>
                    @foreach($c['palabras'] as $p)
                        @if($p['tipo'] === 'agregado')<span class="rl-add">{{ $p['texto'] }}</span>@elseif($p['tipo'] === 'eliminado')<span class="rl-del">{{ $p['texto'] }}</span>@else{{ $p['texto'] }}@endif
                    @endforeach
                </p>
            @endforeach
            @if($modificados->count() > $tramo)
                <div class="rl-mas" x-show="visibles.modificado < {{ $modificados->count() }}">
                    <button type="button" class="rit-btn rit-btn-secondary" @click="visibles.modificado += {{ $tramo }}">Ver más</button>
                </div>
            @endif
        @endif
    </div>
</div>
