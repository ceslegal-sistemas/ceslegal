{{--
    "Temas que cubre su Reglamento" - vitrina de solo lectura sobre la
    taxonomía de 27 temas ya clasificada automáticamente para este RIT
    (TemaClasificadorService). Cero llamadas nuevas a IA: reutiliza
    temasNormativos() (con resumen_simple ya generado para el quiz de
    comprensión del trabajador). Si la clasificación todavía no corrió
    (proceso asíncrono), la sección simplemente no se muestra.

    Corrección 2026-09-28: el primer rediseño usaba @svg() mal (pasaba un
    'style=...' donde este proyecto siempre pasa el NOMBRE de una clase,
    dejando el ícono sin tamaño/color - se veía roto) e inventaba badges y
    un ícono repetido idéntico en cada tarjeta. Pedido explícito del usuario
    tras verlo demasiado plano: "que sea más atractivo... no que solo vea
    información sin sentido". Esta versión reusa .rit-viewer/.rit-viewer-label
    y .rit-badge/.rit-badge-ia (ya establecidos), y toma prestada la
    elevación al pasar el mouse de rit-opcion-cards.blade.php (.rop-card:hover
    - mismo patrón real ya probado en el wizard del RIT), rotando 3 lord-icon
    YA VERIFICADOS en este proyecto (mismos usados en otras partes del RIT,
    todos temáticamente de documento/reglas) en vez de repetir uno solo.

    Variable esperada: $reglamento (App\Models\ReglamentoInterno)
--}}
@php
    $temas = $reglamento->temasNormativos()->orderBy('nombre')->get();
    $iconosPorIndice = ['hmpomorl.json', 'fikcyfpp.json', 'edcgvlnw.json'];
@endphp

@if($temas->isNotEmpty())
    <style>
        .rit-tema-card{position:relative;border-radius:.75rem;padding:.9rem 1rem .85rem;
            background:rgba(225,29,72,.09);border:1px solid rgba(225,29,72,.22);
            transition:transform .18s,box-shadow .18s}
        html:not(.dark) .rit-tema-card{background:rgba(225,29,72,.04);border-color:rgba(225,29,72,.14)}
        .rit-tema-card:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(225,29,72,.10)}
        .rit-tema-ico{width:20px;height:20px;flex-shrink:0;margin-bottom:.4rem}
        .rit-tema-nombre{margin:0;font-size:.8125rem;font-weight:700;color:#f1f5f9}
        html:not(.dark) .rit-tema-nombre{color:#1c1917}
        .rit-tema-resumen{margin:.3rem 0 0;font-size:.75rem;color:#94a3b8;line-height:1.45}
        html:not(.dark) .rit-tema-resumen{color:#57534e}
        @media(prefers-reduced-motion:reduce){.rit-tema-card:hover{transform:none}}
    </style>

    <div class="rit-viewer" style="margin-top:1.25rem">
        <div class="rit-viewer-header" style="gap:.5rem">
            <span class="rit-viewer-label">Temas que cubre su Reglamento</span>
            <span class="rit-badge rit-badge-ia">{{ $temas->count() }}</span>
        </div>
        <div class="rit-viewer-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:.85rem">
                @foreach($temas as $tema)
                    <div class="rit-tema-card">
                        <lord-icon class="rit-tema-ico" src="https://cdn.lordicon.com/{{ $iconosPorIndice[$loop->index % 3] }}"
                            trigger="hover" colors="primary:#e11d48,secondary:#fb923c"></lord-icon>
                        <p class="rit-tema-nombre">{{ $tema->nombre }}</p>
                        @if(filled($tema->pivot->resumen_simple))
                            <p class="rit-tema-resumen">{{ $tema->pivot->resumen_simple }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
