@include('filament.components.pinfo-styles')

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
            style="width:28px;height:28px;color:#6366f1;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
        </svg>
        <p class="pt-title">Seguridad y normas de convivencia</p>
    </div>

    <p class="pt-body">
        La ley exige que el RIT incluya las normas de seguridad y salud en el trabajo.
        Además, las reglas de conducta <strong>previenen conflictos antes de que ocurran</strong>.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

        <div class="pt-bullet">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;color:#6366f1;flex-shrink:0;margin-top:2px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />
                <circle cx="12" cy="12" r="9" stroke-width="1.5" />
            </svg>
            <span>Si aún no tiene el SG-SST implementado, indique <strong>"En proceso"</strong>
                — es la respuesta más honesta y la más común en empresas medianas.</span>
        </div>

        <div class="pt-bullet">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;color:#6366f1;flex-shrink:0;margin-top:2px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />
                <circle cx="12" cy="12" r="9" stroke-width="1.5" />
            </svg>
            <span>La <strong>política de prevención de acoso sexual (Ley 2365/2024)</strong> se incluye
                automáticamente en el texto generado.</span>
        </div>

    </div>

    <p class="pt-footer">
        Pequeñas reglas (celular, uniforme, confidencialidad) evitan grandes conflictos laborales.
    </p>

</div>
