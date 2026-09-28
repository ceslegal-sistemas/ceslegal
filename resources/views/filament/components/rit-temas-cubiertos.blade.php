{{--
    "Temas que cubre su Reglamento" - vitrina de solo lectura sobre la
    taxonomía de 27 temas ya clasificada automáticamente para este RIT
    (TemaClasificadorService). Cero llamadas nuevas a IA: reutiliza
    temasNormativos() (con resumen_simple ya generado para el quiz de
    comprensión del trabajador). Si la clasificación todavía no corrió
    (proceso asíncrono), la sección simplemente no se muestra.

    Rediseñado (2026-09-28, pedido explícito del usuario: "puede verse mucho
    mejor y más atractivo") - ícono por tema, franja superior de color y
    elevación al pasar el mouse, header con ícono + contador como badge
    (mismo lenguaje que "Listos para sancionar" en proceso-guia.blade.php).

    Variable esperada: $reglamento (App\Models\ReglamentoInterno)
--}}
@php
    $temas = $reglamento->temasNormativos()->orderBy('nombre')->get();
@endphp

@if($temas->isNotEmpty())
    <style>
        .rit-temas-titulo{display:flex;align-items:center;gap:.4rem}
        .rit-temas-count{margin-left:.15rem;background:#e11d48;color:#fff;font-size:.68rem;font-weight:700;
            padding:.1rem .45rem;border-radius:999px;letter-spacing:0}
        .rit-tema-card{position:relative;overflow:hidden;border-radius:.85rem;padding:1rem 1.1rem .95rem;
            background:rgba(225,29,72,.09);border:1px solid rgba(225,29,72,.22);
            transition:transform .15s,box-shadow .15s}
        html:not(.dark) .rit-tema-card{background:rgba(225,29,72,.04);border-color:rgba(225,29,72,.14)}
        .rit-tema-card:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(225,29,72,.12)}
        .rit-tema-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;
            background:linear-gradient(90deg,#e11d48,#f97316)}
        .rit-tema-icono{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;
            background:rgba(225,29,72,.14);margin-bottom:.55rem}
        html:not(.dark) .rit-tema-icono{background:rgba(225,29,72,.1)}
        .rit-tema-nombre{margin:0;font-size:.8125rem;font-weight:700;color:#f1f5f9}
        html:not(.dark) .rit-tema-nombre{color:#1c1917}
        .rit-tema-resumen{margin:.3rem 0 0;font-size:.75rem;color:#94a3b8;line-height:1.45}
        html:not(.dark) .rit-tema-resumen{color:#57534e}
        @media(prefers-reduced-motion:reduce){.rit-tema-card:hover{transform:none}}
    </style>

    <div class="rit-viewer" style="margin-top:1.25rem">
        <div class="rit-viewer-header">
            <span class="rit-viewer-label rit-temas-titulo">
                @svg('heroicon-o-book-open', 'style=width:13px;height:13px;color:#e11d48;flex-shrink:0')
                Temas que cubre su Reglamento
                <span class="rit-temas-count">{{ $temas->count() }}</span>
            </span>
        </div>
        <div class="rit-viewer-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:.85rem">
                @foreach($temas as $tema)
                    <div class="rit-tema-card">
                        <span class="rit-tema-icono">
                            <lord-icon src="https://cdn.lordicon.com/jqqjtvlf.json" trigger="hover" stroke="bold"
                                colors="primary:#e11d48,secondary:#e11d48" style="width:16px;height:16px">
                            </lord-icon>
                        </span>
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
