@include('filament.components.pinfo-styles')

<style>
.rit-bar-title  { color: #86efac }
html:not(.dark) .rit-bar-title  { color: #065f46 }
.rit-bar-hint   { color: #94a3b8 }
html:not(.dark) .rit-bar-hint   { color: #374151 }
.rit-bar-hint strong { color: #e2e8f0 }
html:not(.dark) .rit-bar-hint strong { color: #111827 }
</style>

<div class="pt-card" style="border-left-color:#22c55e;padding:.875rem 1.125rem;">

    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">

        <div style="display:flex;align-items:center;gap:.625rem;">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                style="width:22px;height:22px;color:#22c55e;flex-shrink:0">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="rit-bar-title" style="margin:0;font-size:.8rem;font-weight:600;">
                Revise el resumen y confirme — la IA generará <strong>16 capítulos</strong> artículo por artículo
            </p>
        </div>

        <div style="display:flex;gap:.875rem;flex-shrink:0">
            <div class="rit-bar-hint" style="display:flex;align-items:center;gap:.3rem;font-size:.72rem;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    style="width:13px;height:13px;color:#fbbf24;flex-shrink:0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                <strong>No cierre</strong>&nbsp;la ventana durante la generación
            </div>
            <div class="rit-bar-hint" style="display:flex;align-items:center;gap:.3rem;font-size:.72rem;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    style="width:13px;height:13px;color:#93c5fd;flex-shrink:0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                Descarga <strong>.docx</strong>&nbsp;al finalizar
            </div>
        </div>

    </div>

</div>
