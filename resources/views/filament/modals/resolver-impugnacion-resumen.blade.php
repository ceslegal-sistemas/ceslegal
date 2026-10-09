@include('filament.modals.partials.impugnacion-estilos')
<div>
    <div class="rit-hero" style="padding:1.1rem 1.3rem;">
        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
        <div style="position:relative;z-index:2">
            <span class="rit-badge rit-badge-ia">Decisión final · {{ $impugnacion->radicado() }}</span>
            <h1 class="rit-title">Usted resuelve esta impugnación</h1>
            <p class="rit-sub">Sanción impugnada: <strong>{{ $sancionOriginal }}</strong>. Revise lo que alegó el trabajador, considere la sugerencia de la IA y emita la resolución.</p>
        </div>
    </div>

    <p class="imv-sec">Lo que alega el trabajador</p>
    <div class="rit-viewer" style="margin-top:0">
        <div class="rit-viewer-body" style="max-height:14rem">
            <p class="imv-texto">{{ $impugnacion->motivos_impugnacion ?: 'No se expresaron motivos.' }}</p>
        </div>
    </div>

    @include('filament.modals.partials.impugnacion-ia', ['impugnacion' => $impugnacion])
</div>
