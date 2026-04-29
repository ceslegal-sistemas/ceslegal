@include('filament.components.pinfo-styles')

<div class="pt-card" style="border-left-color:#22c55e;">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
            style="width:28px;height:28px;color:#22c55e;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
        </svg>
        <p class="pt-title" style="color:#86efac;">¡Casi listo para generar!</p>
    </div>

    <p class="pt-body">
        Revise el resumen de sus respuestas antes de generar el Reglamento.
        Si algo está incorrecto, puede volver al paso correspondiente con el botón "Anterior".
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

        <div class="pt-bullet">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;color:#22c55e;flex-shrink:0;margin-top:2px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />
                <circle cx="12" cy="12" r="9" stroke-width="1.5" />
            </svg>
            <span>La IA redactará el texto completo <strong>artículo por artículo</strong>, basado en el
                Código Sustantivo del Trabajo.</span>
        </div>

        <div class="pt-bullet">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;color:#22c55e;flex-shrink:0;margin-top:2px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />
                <circle cx="12" cy="12" r="9" stroke-width="1.5" />
            </svg>
            <span>Al hacer clic en <strong>"Crear"</strong>, el proceso puede tardar hasta 90 segundos —
                <strong>no cierre la ventana</strong>.</span>
        </div>

    </div>

    <p class="pt-footer">
        Al finalizar, podrá descargar el documento en formato Word (.docx) listo para imprimir y registrar ante el Ministerio de Trabajo.
    </p>

</div>
