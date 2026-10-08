{{--
    Lista de variables de un Texto Configurable, con botones que insertan el
    marcador en la posición del cursor del campo "Texto". Espera
    $variables = [marcador => descripción] (ConfiguracionTexto::variablesDe()).
    Al insertar se dispara el evento 'input' para que Livewire/Filament tome el
    cambio (el campo es un wire:model).
--}}
<div x-data="{
    insertar(marcador) {
        const area = $el.closest('form').querySelector('textarea');
        if (!area) { return; }
        const ini = area.selectionStart ?? area.value.length;
        const fin = area.selectionEnd ?? area.value.length;
        area.value = area.value.slice(0, ini) + marcador + area.value.slice(fin);
        area.selectionStart = area.selectionEnd = ini + marcador.length;
        area.dispatchEvent(new Event('input', { bubbles: true }));
        area.focus();
    }
}" style="display:flex;flex-direction:column;gap:.6rem">
    @foreach($variables as $marcador => $descripcion)
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
            <button type="button" x-on:click="insertar('{{ $marcador }}')"
                style="font-family:ui-monospace,monospace;font-size:.8rem;font-weight:700;padding:.3rem .75rem;border-radius:999px;cursor:pointer;
                       color:#be123c;background:rgba(225,29,72,.08);border:1px solid rgba(225,29,72,.25)">
                {{ $marcador }}
            </button>
            <span style="font-size:.85rem;opacity:.8">{{ $descripcion }}</span>
        </div>
    @endforeach
</div>
