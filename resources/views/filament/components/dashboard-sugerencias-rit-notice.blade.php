@include('filament.components.pinfo-styles')

<style>
.sugrit-cta {
    display: inline-flex; align-items: center; gap: .5rem;
    padding: .5rem 1.125rem; border-radius: .5rem;
    background: #e11d48; color: #fff;
    font-size: .8125rem; font-weight: 600;
    text-decoration: none; transition: background .15s;
    flex-shrink: 0; white-space: nowrap;
}
.sugrit-cta:hover { background: #be123c; }
</style>

<div class="pt-card" style="border-left-color:#e11d48; margin-bottom:1.5rem;">

    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1.25rem; flex-wrap:wrap;">

        <div style="flex:1; min-width:0;">

            <div style="display:flex; align-items:center; gap:.625rem; margin-bottom:.625rem;">
                <lord-icon src="https://cdn.lordicon.com/xjsqfzte.json" trigger="loop" delay="800" stroke="bold"
                    colors="primary:#fb7185,secondary:#fecdd3" data-pt-icon
                    data-pt-dark="primary:#fb7185,secondary:#fecdd3"
                    data-pt-light="primary:#e11d48,secondary:#f43f5e"
                    style="width:32px;height:32px;flex-shrink:0">
                </lord-icon>
                <p class="pt-title">
                    {{ $totalSugerencias === 1
                        ? 'Hay una actualización legal que aplica a su Reglamento Interno'
                        : "Hay {$totalSugerencias} actualizaciones legales que aplican a su Reglamento Interno" }}
                </p>
            </div>

            <p class="pt-body">
                Salió normativa nueva y ya identificamos los cambios puntuales que le corresponden a su RIT.
                ¿Desea actualizarlo?
            </p>

            <p class="pt-footer" style="border-top-color:rgba(225,29,72,.2);">
                Recuerde: una vez actualizado, debe socializar los cambios con sus trabajadores para que sepan
                qué cambió.
            </p>

        </div>

        <a href="{{ url('/empresa/mi-reglamento-interno') }}" class="sugrit-cta">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                style="width:16px;height:16px;flex-shrink:0">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
            </svg>
            Revisar y actualizar
        </a>

    </div>

</div>
