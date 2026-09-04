{{--
    Pop-up "insistente" pedido por el jefe, ADEMÁS del banner ya existente
    (dashboard-sugerencias-rit-notice.blade.php, que se conserva sin
    cambios). No guarda ningún estado de "ya lo cerré" a propósito - debe
    reaparecer en cada carga del Dashboard mientras siga pendiente, según
    lo pedido explícitamente ("insistente"). Solo para rol 'cliente' (ver
    condición en dashboard.blade.php).
--}}
<style>
    .sugrit-modal-overlay {
        position: fixed; inset: 0; z-index: 100;
        background: rgba(15, 3, 6, .55);
        display: flex; align-items: center; justify-content: center;
        padding: 1rem;
    }
    .sugrit-modal-card {
        background: #1c0f12;
        border: 1px solid rgba(225, 29, 72, .35);
        border-radius: 1rem;
        max-width: 460px;
        width: 100%;
        padding: 1.75rem 1.5rem 1.5rem;
        box-shadow: 0 20px 60px rgba(0,0,0,.4);
        position: relative;
    }
    html:not(.dark) .sugrit-modal-card {
        background: #fff;
        border-color: rgba(225, 29, 72, .25);
    }
    .sugrit-modal-close {
        position: absolute; top: .9rem; right: .9rem;
        width: 28px; height: 28px; border-radius: 999px;
        display: flex; align-items: center; justify-content: center;
        color: #94a3b8; background: transparent; border: none; cursor: pointer;
        transition: background .15s;
    }
    .sugrit-modal-close:hover { background: rgba(148,163,184,.15); }
    .sugrit-modal-icon {
        width: 44px; height: 44px; border-radius: 999px;
        background: rgba(225, 29, 72, .15);
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 1rem;
    }
    .sugrit-modal-title {
        font-size: 1.0625rem; font-weight: 700; margin: 0 0 .5rem;
        color: #f1f5f9;
    }
    html:not(.dark) .sugrit-modal-title { color: #111827; }
    .sugrit-modal-body {
        font-size: .875rem; line-height: 1.55; color: #cbd5e1; margin: 0 0 .875rem;
    }
    html:not(.dark) .sugrit-modal-body { color: #374151; }
    .sugrit-modal-footer {
        font-size: .75rem; line-height: 1.5; color: #64748b;
        border-top: 1px solid rgba(225,29,72,.18); padding-top: .75rem; margin: 0 0 1.25rem;
    }
    .sugrit-modal-actions { display: flex; justify-content: flex-end; }
    .sugrit-modal-cta {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .625rem 1.25rem; border-radius: .5rem;
        background: #e11d48; color: #fff;
        font-size: .8125rem; font-weight: 600;
        text-decoration: none; transition: background .15s;
    }
    .sugrit-modal-cta:hover { background: #be123c; }
</style>

<div x-data="{ open: true }" x-show="open" x-cloak
     class="sugrit-modal-overlay" x-on:keydown.escape.window="open = false">
    <div class="sugrit-modal-card" x-on:click.outside="open = false">
        <button type="button" class="sugrit-modal-close" x-on:click="open = false" aria-label="Cerrar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div class="sugrit-modal-icon">
            <lord-icon src="https://cdn.lordicon.com/xjsqfzte.json" trigger="loop" delay="600" stroke="bold"
                colors="primary:#fb7185,secondary:#fecdd3"
                style="width:24px;height:24px">
            </lord-icon>
        </div>

        <h2 class="sugrit-modal-title">
            {{ $totalSugerencias === 1
                ? 'Hay una actualización legal que aplica a su Reglamento Interno'
                : "Hay {$totalSugerencias} actualizaciones legales que aplican a su Reglamento Interno" }}
        </h2>

        <p class="sugrit-modal-body">
            Salió normativa nueva y ya identificamos los cambios puntuales que le corresponden a su RIT.
            ¿Desea actualizarlo?
        </p>

        <p class="sugrit-modal-footer">
            Recuerde: una vez actualizado, debe socializar los cambios con sus trabajadores para que sepan
            qué cambió.
        </p>

        <div class="sugrit-modal-actions">
            <a href="{{ url('/empresa/mi-reglamento-interno') }}" class="sugrit-modal-cta">
                Revisar y actualizar
            </a>
        </div>
    </div>
</div>
