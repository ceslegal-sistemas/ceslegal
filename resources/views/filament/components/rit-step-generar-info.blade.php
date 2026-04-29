@include('filament.components.pinfo-styles')

<div class="pt-card" style="border-left-color:#22c55e;padding:.875rem 1.125rem;">

    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">

        <div style="display:flex;align-items:center;gap:.625rem;">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                style="width:22px;height:22px;color:#22c55e;flex-shrink:0">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="pt-title" style="color:#86efac;margin:0;font-size:.8rem;">
                Revise el resumen y confirme — la IA generará <strong>16 capítulos</strong> artículo por artículo
            </p>
        </div>

        <div style="display:flex;gap:.75rem;flex-shrink:0">
            <div style="display:flex;align-items:center;gap:.3rem;font-size:.72rem;color:#94a3b8;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    style="width:13px;height:13px;color:#fbbf24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                No cierre la ventana durante la generación
            </div>
            <div style="display:flex;align-items:center;gap:.3rem;font-size:.72rem;color:#94a3b8;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    style="width:13px;height:13px;color:#93c5fd">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                </svg>
                Descarga .docx al finalizar
            </div>
        </div>

    </div>

</div>
