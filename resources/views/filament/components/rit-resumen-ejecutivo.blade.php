@php
    /**
     * Resumen ejecutivo de los cambios del RIT (pedido de Andrés Sarmiento,
     * reunión 2026-10-05). Espera $resumen = texto devuelto por
     * App\Services\RitResumenEjecutivoService.
     *
     * La IA responde en markdown ("* **Título:** descripción"). Antes se
     * imprimía tal cual (se veían los asteriscos y un bloque denso); aquí se
     * convierte en una intro + una lista de tarjetas numeradas, con la marca
     * de Lupe. Todo se escapa con e() - nada del texto de la IA entra como HTML.
     */
    $texto = trim((string) ($resumen ?? ''));
    $intro = [];
    $items = [];
    $cierre = [];

    $limpiar = fn (string $s): string => trim(str_replace('**', '', $s));

    foreach (preg_split('/\R/u', $texto) as $linea) {
        $linea = trim($linea);
        if ($linea === '') {
            continue;
        }

        if (preg_match('/^[\*\-\x{2022}]\s+(.*)$/u', $linea, $m)) {
            $contenido = $m[1];
            if (preg_match('/^\*\*(.+?)\*\*\s*:?\s*(.*)$/u', $contenido, $mm)) {
                $items[] = ['titulo' => trim($limpiar($mm[1]), " :\t"), 'desc' => $limpiar($mm[2])];
            } else {
                $items[] = ['titulo' => '', 'desc' => $limpiar($contenido)];
            }
        } elseif (empty($items)) {
            $intro[] = $limpiar($linea);
        } else {
            $cierre[] = $limpiar($linea);
        }
    }
@endphp

@verbatim
<style>
.rs-card{border-radius:1.25rem;padding:1.4rem 1.4rem 1.2rem;border:1px solid rgba(225,29,72,.16);
    background:linear-gradient(150deg,#fff1f2 0%,#fff7ed 55%,#ffffff 100%);box-shadow:0 6px 26px rgba(225,29,72,.08)}
html.dark .rs-card{border-color:rgba(251,113,133,.28);background:linear-gradient(150deg,#1a0f0c 0%,#241319 55%,#170d0a 100%);box-shadow:none}
.rs-intro{font-size:.9rem;line-height:1.7;color:#44403c;margin:.9rem 0 1.1rem}
html.dark .rs-intro{color:#d6d3d1}
.rs-list{display:flex;flex-direction:column;gap:.7rem;margin:0;padding:0;list-style:none}
.rs-item{display:flex;gap:.85rem;align-items:flex-start;padding:.85rem .95rem;border-radius:.9rem;
    background:rgba(255,255,255,.72);border:1px solid rgba(225,29,72,.1)}
html.dark .rs-item{background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.08)}
.rs-num{flex:0 0 auto;width:1.65rem;height:1.65rem;border-radius:999px;display:flex;align-items:center;justify-content:center;
    font-size:.75rem;font-weight:700;color:#fff;background:linear-gradient(135deg,#e11d48,#f97316);margin-top:.1rem}
.rs-title{margin:0;font-size:.9rem;font-weight:700;line-height:1.4;color:#1c1917}
html.dark .rs-title{color:#fafaf9}
.rs-desc{margin:.25rem 0 0;font-size:.85rem;line-height:1.65;color:#57534e}
html.dark .rs-desc{color:#d6d3d1}
.rs-cierre{font-size:.82rem;line-height:1.6;color:#78716c;margin:1rem 0 0}
html.dark .rs-cierre{color:#a8a29e}
</style>
@endverbatim

<div class="rs-card">
    <span class="rit-badge rit-badge-ia">Resumen ejecutivo</span>

    @foreach($intro as $parrafo)
        <p class="rs-intro">{{ $parrafo }}</p>
    @endforeach

    @if(!empty($items))
        <ol class="rs-list">
            @foreach($items as $i => $item)
                <li class="rs-item">
                    <span class="rs-num">{{ $i + 1 }}</span>
                    <div>
                        @if($item['titulo'] !== '')
                            <p class="rs-title">{{ $item['titulo'] }}</p>
                        @endif
                        @if($item['desc'] !== '')
                            <p class="rs-desc" @if($item['titulo'] === '') style="margin-top:0" @endif>{{ $item['desc'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    @foreach($cierre as $parrafo)
        <p class="rs-cierre">{{ $parrafo }}</p>
    @endforeach
</div>
