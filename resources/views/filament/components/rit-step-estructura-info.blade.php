@include('filament.components.pinfo-styles')

<div class="pt-card">

    <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.625rem;">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
            style="width:28px;height:28px;color:#6366f1;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
        <p class="pt-title">¿Quién manda en su empresa?</p>
    </div>

    <p class="pt-body">
        El RIT debe especificar qué cargos tienen autoridad para corregir o sancionar empleados.
        Sin esto, cualquier sanción puede impugnarse legalmente.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

        <div class="pt-bullet">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;color:#6366f1;flex-shrink:0;margin-top:2px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />
                <circle cx="12" cy="12" r="9" stroke-width="1.5" />
            </svg>
            <span>Liste los cargos <strong>tal como los llaman internamente</strong> — no necesita usar nombres formales.</span>
        </div>

        <div class="pt-bullet">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;color:#6366f1;flex-shrink:0;margin-top:2px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75" />
                <circle cx="12" cy="12" r="9" stroke-width="1.5" />
            </svg>
            <span>Solo marque <strong>"puede sancionar"</strong> en los cargos que realmente toman esas
                decisiones (gerentes, jefes de área).</span>
        </div>

    </div>

    <p class="pt-footer">
        Tip: Si tiene pocos cargos (p. ej. Gerente y Operario), simplemente agréguelos.
        No necesita un organigrama formal.
    </p>

</div>
