{{--
    Tarjeta de resumen (icono + título + grilla de campos), mismo lenguaje
    visual que "Mi Reglamento Interno" - reemplaza los Sections genéricos de
    Filament Infolist, que no comparten ese estilo.

    Variables esperadas:
      $icon   string        - src del lord-icon
      $title  string        - título de la tarjeta
      $rows   array         - [['label' => .., 'value' => .., 'full' => bool opcional]]
--}}
<div class="rit-info-card">
    <div class="rit-info-header">
        <lord-icon src="{{ $icon }}" trigger="loop" delay="800" stroke="bold"
            colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
            data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
            data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
            style="width:24px;height:24px;flex-shrink:0">
        </lord-icon>
        <p class="rit-info-title">{{ $title }}</p>
    </div>
    <div class="rit-info-body">
        <div class="rit-info-grid">
            @foreach ($rows as $row)
                <div @if($row['full'] ?? false) class="rit-info-item-full" @endif>
                    <p class="rit-info-label">{{ $row['label'] }}</p>
                    <p class="rit-info-value {{ filled($row['value'] ?? null) ? '' : 'rit-info-value-muted' }}">
                        {{ filled($row['value'] ?? null) ? $row['value'] : '-' }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</div>
