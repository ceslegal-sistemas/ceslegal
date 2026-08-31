{{--
    Copia de solicitud-contrato-detalles-cargo-ia-boton.blade.php para la
    página autónoma CrearSolicitudContrato, que expone completarConIA() en
    vez de completarDetallesConIA() (método del trait
    CompletaDetallesCargoConIA usado por EditSolicitudContrato - ese trait
    y su partial original NO se tocan, siguen intactos).
--}}
@include('filament.components.lupe-hero-styles')

<div style="border-radius:.75rem;border:1px solid rgba(251,113,133,.25);background:rgba(251,113,133,.06);padding:1rem 1.125rem">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:.6rem">
            <svg style="width:18px;height:18px;color:#fb7185;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
            <div>
                <p style="font-size:.825rem;font-weight:600;margin:0;color:#44403c" class="dark:text-stone-200">Asistente de IA</p>
                <p style="font-size:.75rem;margin:0;color:#78716c" class="dark:text-stone-400">Completa responsabilidades, objeto comercial y manual de funciones a la vez</p>
            </div>
        </div>

        <button
            type="button"
            wire:click="completarConIA"
            wire:loading.attr="disabled"
            wire:target="completarConIA"
            class="rit-btn rit-btn-primary"
        >
            <svg wire:loading.remove wire:target="completarConIA" style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
            <svg wire:loading wire:target="completarConIA" style="width:15px;height:15px;animation:rit-spin 1s linear infinite" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            <span wire:loading.remove wire:target="completarConIA">Completar con IA</span>
            <span wire:loading wire:target="completarConIA">Generando...</span>
        </button>
    </div>
</div>
