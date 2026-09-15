{{--
    Aviso prominente (distinto de la tarjeta pasiva de clasificacion-gravedad-
    resultado.blade.php) para cuando la IA marca categoria_riesgo_legal =
    'posible_acoso_o_violencia' - ver IADescargoService::clasificarIncidente().
    Contenido FIJO, no generado por IA a propósito: un texto legal sensible no
    debe depender de que el modelo lo redacte bien cada vez.
--}}
<style>
.arla-card {
    background: rgba(220,38,38,.10);
    border: 2px solid rgba(220,38,38,.35);
    border-radius: .875rem;
    padding: 1.125rem 1.25rem;
    margin: .75rem 0;
}
.arla-title { display:flex; align-items:center; gap:.5rem; font-size:.9375rem; font-weight:800; color:#fca5a5; margin:0 0 .5rem; }
.arla-body { font-size:.875rem; line-height:1.65; color:#fecaca; margin:0 0 .5rem; }
.arla-justificacion { font-size:.8125rem; line-height:1.6; color:#fca5a5; font-style:italic; margin:0 0 .875rem; padding-left:.75rem; border-left:2px solid rgba(220,38,38,.3); }
html:not(.dark) .arla-card { background:rgba(220,38,38,.06); border-color:rgba(220,38,38,.3); }
html:not(.dark) .arla-title { color:#b91c1c; }
html:not(.dark) .arla-body { color:#7f1d1d; }
html:not(.dark) .arla-justificacion { color:#991b1b; }
</style>

<div class="arla-card">
    <p class="arla-title">
        <svg style="width:22px;height:22px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
        </svg>
        Este caso requiere manejo especial
    </p>
    <p class="arla-body">
        La descripción sugiere una posible falta de naturaleza sensible (contacto no consentido,
        connotación sexual, violencia o amenaza). Mantenga presunción de inocencia total: no
        prejuzgue los hechos ni use lenguaje que dé por cierta una conclusión. Considere obtener
        asesoría legal adicional especializada antes de continuar con este proceso.
    </p>
    @if(!empty($justificacion))
        <p class="arla-justificacion">{{ $justificacion }}</p>
    @endif
</div>
