{{-- Livewire exige un único elemento raíz SIEMPRE presente, sin importar
     $mostrar - nunca condicionar el <div> raíz en sí (gotcha ya documentado
     en el proyecto: ver gotcha-livewire-raiz-unica-include-antes-del-div). --}}
<div>
@if($mostrar)
    @include('filament.components.lupe-hero-styles')
    {{-- Bloqueante a propósito (pedido explícito del usuario, 2026-09-28):
         sin botón de cerrar/X, sin click-fuera-para-cerrar, sin tecla Escape -
         la única salida es el botón de abajo. "No puede decir que no lo vio".

         Posicionamiento 100% en CSS inline (NUNCA clases Tailwind como
         "fixed"/"z-[9999]") a propósito: un popup bloqueante no puede
         depender de que alguien haya recompilado public/build/ - si esa
         compilación queda desactualizada (como pasó en producción,
         2026-10-05), las clases de Tailwind simplemente no existen en el
         CSS servido y el popup se renderiza como un bloque normal del
         documento en vez de un overlay de pantalla completa. El estilo
         inline siempre funciona, sin depender de ningún build. --}}
    <div style="position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.65);display:flex;align-items:center;justify-content:center;z-index:999999;padding:1rem;">
        <div class="rit-hero" style="width:100%;max-width:28rem;padding:1.5rem;">
            <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
            <div style="position:relative;z-index:2">
                <span class="rit-badge rit-badge-warning">
                    <lord-icon src="https://cdn.lordicon.com/hmpomorl.json" trigger="loop" delay="500" stroke="bold" colors="primary:#fbbf24,secondary:#fbbf24" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
                    Reglamento Interno pendiente
                </span>

                @if($fase === 'publicacion')
                    <h1 class="rit-title">Todavía faltan trabajadores por confirmar</h1>
                    <p class="rit-sub">
                        Tu Reglamento Interno de Trabajo está en el plazo legal de objeción y aún no todos tus
                        trabajadores han confirmado que fueron informados. Revisa el progreso y comparte el link
                        para continuar.
                    </p>
                @else
                    <h1 class="rit-title">Todavía no culminaste la socialización</h1>
                    <p class="rit-sub">
                        Tu Reglamento Interno de Trabajo sigue en proceso de socialización con tus trabajadores.
                        Revisa quién falta por aceptar y culmina el proceso cuando corresponda.
                    </p>
                @endif

                <div class="rit-actions">
                    <button type="button" wire:click="irAMiReglamento" wire:loading.attr="disabled" wire:target="irAMiReglamento"
                        class="rit-btn rit-btn-cta">
                        Ir a Mi Reglamento Interno
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
</div>
