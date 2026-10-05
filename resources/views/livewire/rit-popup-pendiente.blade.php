{{-- Livewire exige un único elemento raíz SIEMPRE presente, sin importar
     $mostrar - nunca condicionar el <div> raíz en sí (gotcha ya documentado
     en el proyecto: ver gotcha-livewire-raiz-unica-include-antes-del-div). --}}
<div>
@if($mostrar)
    @include('filament.components.lupe-hero-styles')
    {{-- Bloqueante a propósito (pedido explícito del usuario, 2026-09-28):
         sin botón de cerrar/X, sin click-fuera-para-cerrar, sin tecla Escape -
         la única salida es el botón de abajo. "No puede decir que no lo vio". --}}
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-[9999] p-4">
        <div class="rit-hero w-full max-w-md" style="padding:1.5rem;">
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
