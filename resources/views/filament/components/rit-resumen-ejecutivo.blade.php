@php
    /**
     * Resumen ejecutivo de los cambios del RIT (pedido de Andrés Sarmiento,
     * reunión 2026-10-05). Espera $resumen = texto devuelto por
     * App\Services\RitResumenEjecutivoService.
     *
     * La IA responde en markdown ("* **Título:** descripción"). Antes se
     * imprimía tal cual (se veían los asteriscos y un bloque denso); aquí se
     * convierte en una intro + tarjetas con el mismo lenguaje visual de
     * "Temas que cubre su Reglamento" (rit-temas-cubiertos): franja de
     * encabezado .rit-viewer-header con contador y tarjetas tintadas con
     * lord-icon. Requiere documento-viewer-styles incluido en la página.
     * Todo se escapa con e() - nada del texto de la IA entra como HTML.
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
.rs-intro{font-size:.875rem;line-height:1.7;color:#57534e;margin:0 0 1.1rem}
html.dark .rs-intro{color:#cbd5e1}
.rs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:.85rem}
.rs-card{position:relative;border-radius:.75rem;padding:.9rem 1rem .85rem;
    background:rgba(225,29,72,.04);border:1px solid rgba(225,29,72,.14);transition:transform .18s,box-shadow .18s}
html.dark .rs-card{background:rgba(225,29,72,.09);border-color:rgba(225,29,72,.22)}
.rs-card:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(225,29,72,.10)}
.rs-ico{width:20px;height:20px;flex-shrink:0;margin-bottom:.4rem;display:block}
.rs-title{margin:0;font-size:.8125rem;font-weight:700;line-height:1.4;color:#1c1917}
html.dark .rs-title{color:#f1f5f9}
.rs-desc{margin:.3rem 0 0;font-size:.75rem;line-height:1.5;color:#57534e}
html.dark .rs-desc{color:#94a3b8}
.rs-cierre{font-size:.8rem;line-height:1.6;color:#78716c;margin:1rem 0 0}
html.dark .rs-cierre{color:#94a3b8}
@media(prefers-reduced-motion:reduce){.rs-card:hover{transform:none}}
</style>
@endverbatim

@php $iconosPorIndice = ['hmpomorl.json', 'fikcyfpp.json', 'edcgvlnw.json']; @endphp

<div class="rit-viewer" style="margin-top:0">
    <div class="rit-viewer-header" style="gap:.5rem">
        <span class="rit-viewer-label">Resumen ejecutivo</span>
        @if(!empty($items))
            <span class="rit-badge rit-badge-ia">{{ count($items) }}</span>
        @endif
    </div>
    <div class="rit-viewer-body" style="max-height:none">
        @foreach($intro as $parrafo)
            <p class="rs-intro">{{ $parrafo }}</p>
        @endforeach

        @if(!empty($items))
            <div class="rs-grid">
                @foreach($items as $i => $item)
                    <div class="rs-card">
                        <lord-icon class="rs-ico" src="https://cdn.lordicon.com/{{ $iconosPorIndice[$i % 3] }}"
                            trigger="hover" colors="primary:#e11d48,secondary:#fb923c"></lord-icon>
                        @if($item['titulo'] !== '')
                            <p class="rs-title">{{ $item['titulo'] }}</p>
                        @endif
                        @if($item['desc'] !== '')
                            <p class="rs-desc">{{ $item['desc'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @foreach($cierre as $parrafo)
            <p class="rs-cierre">{{ $parrafo }}</p>
        @endforeach
    </div>
</div>
