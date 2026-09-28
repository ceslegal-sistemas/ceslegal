{{--
    "Temas que cubre su Reglamento" - vitrina de solo lectura sobre la
    taxonomía de 27 temas ya clasificada automáticamente para este RIT
    (TemaClasificadorService). Cero llamadas nuevas a IA: reutiliza
    temasNormativos() (con resumen_simple ya generado para el quiz de
    comprensión del trabajador). Si la clasificación todavía no corrió
    (proceso asíncrono), la sección simplemente no se muestra.

    Corrección 2026-09-28: el primer rediseño usaba @svg() mal (pasaba un
    'style=...' donde este proyecto siempre pasa el NOMBRE de una clase,
    dejando el ícono sin tamaño/color - se veía roto) e inventaba un ícono
    de lord-icon repetido idéntico en cada una de las N tarjetas, sin
    aportar información real. Esta versión usa solo lo ya establecido:
    .rit-viewer/.rit-viewer-label (mismo visor que el resto de "Mi
    Reglamento Interno") y .rit-badge/.rit-badge-ia (mismo badge de marca
    ya usado en proceso-guia.blade.php) para el contador.

    Variable esperada: $reglamento (App\Models\ReglamentoInterno)
--}}
@php
    $temas = $reglamento->temasNormativos()->orderBy('nombre')->get();
@endphp

@if($temas->isNotEmpty())
    <style>
        .rit-tema-card{border-radius:.75rem;padding:.85rem 1rem;background:rgba(225,29,72,.09);border:1px solid rgba(225,29,72,.22)}
        html:not(.dark) .rit-tema-card{background:rgba(225,29,72,.04);border-color:rgba(225,29,72,.14)}
        .rit-tema-nombre{margin:0;font-size:.8125rem;font-weight:700;color:#f1f5f9}
        html:not(.dark) .rit-tema-nombre{color:#1c1917}
        .rit-tema-resumen{margin:.3rem 0 0;font-size:.75rem;color:#94a3b8;line-height:1.45}
        html:not(.dark) .rit-tema-resumen{color:#57534e}
    </style>

    <div class="rit-viewer" style="margin-top:1.25rem">
        <div class="rit-viewer-header" style="gap:.5rem">
            <span class="rit-viewer-label">Temas que cubre su Reglamento</span>
            <span class="rit-badge rit-badge-ia">{{ $temas->count() }}</span>
        </div>
        <div class="rit-viewer-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:.75rem">
                @foreach($temas as $tema)
                    <div class="rit-tema-card">
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
