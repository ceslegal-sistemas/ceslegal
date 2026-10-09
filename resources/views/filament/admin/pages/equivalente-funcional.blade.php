@php
    $secciones = [
        'todos' => ['Trazabilidad completa', 'Todas las aceptaciones y autorizaciones', 'heroicon-o-shield-check'],
        'sanciones' => ['Sanciones / Incidentes', 'Quién autorizó cada decisión', 'heroicon-o-exclamation-triangle'],
        'rit' => ['Autorización del Reglamento', 'Funcionarios que autorizaron el RIT', 'heroicon-o-document-check'],
        'descargos' => ['Descargos', 'Quién citó y cómo se verificó', 'heroicon-o-chat-bubble-left-right'],
        'trabajadores' => ['Aceptación de trabajadores', 'IP, selfie, quiz y acta', 'heroicon-o-user-group'],
        'videos' => ['Videos del Reglamento', 'Histórico de videos reemplazados', 'heroicon-o-play-circle'],
    ];
    $conteos = $this->getConteos();
@endphp

<x-filament-panels::page>
    @verbatim
    <style>
    .ef-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:.85rem}
    .ef-card{display:flex;align-items:flex-start;gap:.75rem;text-align:left;width:100%;cursor:pointer;
        border-radius:.75rem;padding:.9rem 1rem;background:rgba(225,29,72,.03);border:1px solid rgba(0,0,0,.08);
        transition:transform .18s,box-shadow .18s,border-color .18s,background-color .18s}
    html.dark .ef-card{background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.1)}
    .ef-card:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(225,29,72,.10);border-color:rgba(225,29,72,.3)}
    .ef-card-on,.ef-card-on:hover{background:rgba(225,29,72,.08);border-color:rgba(225,29,72,.45);box-shadow:0 0 0 1px rgba(225,29,72,.25) inset}
    html.dark .ef-card-on{background:rgba(251,113,133,.14);border-color:rgba(251,113,133,.5)}
    .ef-ico{flex-shrink:0;width:2.25rem;height:2.25rem;border-radius:.6rem;display:flex;align-items:center;justify-content:center;
        background:rgba(225,29,72,.1);color:#e11d48}
    .ef-card-on .ef-ico{background:linear-gradient(135deg,#e11d48,#f97316);color:#fff}
    .ef-ico svg{width:1.2rem;height:1.2rem}
    .ef-num{font-size:1.35rem;font-weight:700;line-height:1.1;color:#1c1917}
    html.dark .ef-num{color:#f1f5f9}
    .ef-tit{font-size:.8125rem;font-weight:600;line-height:1.3;color:#292524;margin-top:.15rem}
    html.dark .ef-tit{color:#e2e8f0}
    .ef-sub{font-size:.72rem;line-height:1.35;color:#78716c;margin-top:.1rem}
    html.dark .ef-sub{color:#94a3b8}
    @media(prefers-reduced-motion:reduce){.ef-card:hover{transform:none}}
    </style>
    @endverbatim

    <div class="ef-grid">
        @foreach ($secciones as $clave => [$titulo, $sub, $icono])
            <button type="button" wire:click="cambiarSeccion('{{ $clave }}')"
                class="ef-card {{ $seccion === $clave ? 'ef-card-on' : '' }}" aria-pressed="{{ $seccion === $clave ? 'true' : 'false' }}">
                <span class="ef-ico"><x-filament::icon :icon="$icono" /></span>
                <span>
                    <span class="ef-num" style="display:block">{{ $conteos[$clave] ?? '—' }}</span>
                    <span class="ef-tit" style="display:block">{{ $titulo }}</span>
                    <span class="ef-sub" style="display:block">{{ $sub }}</span>
                </span>
            </button>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
