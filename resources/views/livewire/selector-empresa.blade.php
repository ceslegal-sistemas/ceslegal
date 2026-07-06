{{-- Selector de empresa activa (abogado de bufete) para el topbar del panel. --}}
@php
    $activa = $activaId ? $empresas->firstWhere('id', $activaId) : null;
    $label = $activa?->razon_social ?? 'Todas las empresas';
@endphp

<div x-data="{ open: false }" @keydown.escape="open = false" class="se-wrap" style="position:relative">
    <button type="button" @click="open = !open"
        class="se-btn"
        style="display:inline-flex;align-items:center;gap:.4rem;font-size:.8rem;font-weight:600;
               padding:.4rem .7rem;border-radius:.6rem;border:1px solid rgba(0,0,0,.1);
               background:rgba(0,0,0,.02);color:#334155;cursor:pointer;max-width:15rem">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" style="width:15px;height:15px;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
        </svg>
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $label }}</span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
             style="width:14px;height:14px;flex-shrink:0;opacity:.6"><path fill-rule="evenodd"
             d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" x-transition
        class="se-menu"
        style="position:absolute;right:0;margin-top:.35rem;min-width:16rem;max-height:20rem;overflow:auto;
               background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:.7rem;
               box-shadow:0 12px 34px rgba(0,0,0,.12);z-index:50;padding:.35rem">
        <button type="button" wire:click="todas" @click="open = false"
            class="se-item" @style(['width:100%;text-align:left;padding:.45rem .6rem;border-radius:.45rem;font-size:.8rem;cursor:pointer;background:transparent', 'font-weight:700' => ! $activaId])>
            Todas las empresas
        </button>
        <div style="height:1px;background:rgba(0,0,0,.06);margin:.3rem 0"></div>
        @foreach ($empresas as $empresa)
            <button type="button" wire:click="seleccionar({{ $empresa->id }})" @click="open = false"
                class="se-item" @style(['width:100%;text-align:left;padding:.45rem .6rem;border-radius:.45rem;font-size:.8rem;cursor:pointer;background:transparent', 'font-weight:700;color:#2563eb' => $activaId === $empresa->id])>
                {{ $empresa->razon_social }}
            </button>
        @endforeach
    </div>

    <style>
        .se-item:hover{background:rgba(37,99,235,.07)!important}
        html.dark .se-btn{background:rgba(255,255,255,.05)!important;border-color:rgba(255,255,255,.12)!important;color:#cbd5e1!important}
        html.dark .se-menu{background:#1e293b!important;border-color:rgba(255,255,255,.1)!important}
        [x-cloak]{display:none!important}
    </style>
</div>
