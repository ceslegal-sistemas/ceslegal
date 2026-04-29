@include('filament.components.pinfo-styles')

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
            style="width:28px;height:28px;color:#6366f1;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
        </svg>
        <p class="pt-title">Su empresa en el Reglamento</p>
    </div>

    <p class="pt-body">
        Los datos de su empresa aparecerán en el encabezado oficial del documento.
        Ya cargamos la información del registro — solo confirme o complete lo que falte.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

        <div class="pt-bullet">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;color:#6366f1;flex-shrink:0;margin-top:2px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />
                <circle cx="12" cy="12" r="9" stroke-width="1.5" />
            </svg>
            <span>La <strong>actividad económica</strong> define los riesgos laborales específicos que debe
                cubrir el RIT.</span>
        </div>

        <div class="pt-bullet">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;color:#6366f1;flex-shrink:0;margin-top:2px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />
                <circle cx="12" cy="12" r="9" stroke-width="1.5" />
            </svg>
            <span>Si tiene varias sedes, el reglamento <strong>aplica a todas</strong> — solo indique cuántos
                trabajadores hay en cada una.</span>
        </div>

    </div>

    <p class="pt-footer">
        Tiempo estimado: 2 minutos. — Los campos con asterisco (*) son obligatorios.
    </p>

</div>
