<div
    x-data="{ abierto: false }"
    style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:40;font-family:ui-sans-serif,system-ui,sans-serif;"
>
    <div
        x-show="abierto"
        x-transition
        style="display:none;width:340px;max-height:480px;margin-bottom:0.75rem;border-radius:1rem;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.25);background:var(--apc-bg,#fff);border:1px solid var(--apc-border,rgba(0,0,0,.1));"
    >
        <div style="padding:0.75rem 1rem;background:#E11D48;color:#fff;font-weight:700;font-size:0.875rem;">
            Asistente Lupe Legal
        </div>

        <div style="padding:0.75rem 1rem;max-height:320px;overflow-y:auto;display:flex;flex-direction:column;gap:0.5rem;">
            @forelse($mensajes as $mensaje)
                <div style="align-self:{{ $mensaje['rol'] === 'usuario' ? 'flex-end' : 'flex-start' }};max-width:85%;padding:0.5rem 0.75rem;border-radius:0.75rem;font-size:0.8125rem;line-height:1.5;
                    background:{{ $mensaje['rol'] === 'usuario' ? '#E11D48' : 'rgba(0,0,0,.05)' }};
                    color:{{ $mensaje['rol'] === 'usuario' ? '#fff' : 'inherit' }};">
                    {{ $mensaje['texto'] }}
                </div>
            @empty
                <p style="font-size:0.8125rem;color:rgba(0,0,0,.5);">Pregúntame sobre tu RIT, procesos disciplinarios, empleados o contratos por vencer.</p>
            @endforelse

            @if($enviando)
                <div style="align-self:flex-start;font-size:0.8125rem;color:rgba(0,0,0,.5);">Escribiendo...</div>
            @endif
        </div>

        <form wire:submit.prevent="enviar" style="display:flex;gap:0.5rem;padding:0.75rem;border-top:1px solid rgba(0,0,0,.08);">
            <input
                type="text"
                wire:model="mensajeActual"
                placeholder="Escribe tu pregunta..."
                style="flex:1;padding:0.5rem 0.75rem;border-radius:0.5rem;border:1px solid rgba(0,0,0,.15);font-size:0.8125rem;"
            >
            <button type="submit" style="padding:0.5rem 1rem;border-radius:0.5rem;background:#E11D48;color:#fff;font-size:0.8125rem;font-weight:600;border:none;cursor:pointer;">
                Enviar
            </button>
        </form>
    </div>

    <button
        type="button"
        x-on:click="abierto = !abierto"
        style="width:56px;height:56px;border-radius:50%;background:#E11D48;color:#fff;border:none;box-shadow:0 4px 16px rgba(225,29,72,.4);cursor:pointer;font-size:1.5rem;"
    >
        💬
    </button>
</div>
