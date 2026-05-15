{{--
    Tarjeta de Potestad Disciplinaria según el RIT
    Variables esperadas: $autoridadRit (array), $opcionesSancion (array)
    Paleta: marco blanco/negro, colores solo en los tipos de sanción
--}}
@php
    $autoridad = $autoridadRit   ?? [];
    $opciones  = $opcionesSancion ?? [];

    $labels = [
        'llamado_atencion' => ['label' => 'Llamado de Atención',     'accent' => '#60a5fa'],
        'suspension'       => ['label' => 'Suspensión Laboral',       'accent' => '#fbbf24'],
        'terminacion'      => ['label' => 'Terminación de Contrato',  'accent' => '#f87171'],
    ];

    $filas = [];
    foreach ($labels as $key => $config) {
        if (!array_key_exists($key, $opciones)) continue;
        $texto = $autoridad[$key] ?? 'No especificado en el RIT';
        $filas[] = [
            'label'  => $config['label'],
            'accent' => $config['accent'],
            'texto'  => $texto,
            'vacio'  => ($texto === 'No especificado en el RIT'),
        ];
    }

    $todoVacio = !empty($filas) && count(array_filter($filas, fn($f) => !$f['vacio'])) === 0;
@endphp

<style>
:root {
    --esp-label:      rgba(0,0,0,0.45);
    --esp-title:      rgba(17,24,39,0.90);
    --esp-muted:      rgba(0,0,0,0.40);
    --esp-body:       rgba(17,24,39,0.78);
    --esp-row-border: rgba(0,0,0,0.08);
    --esp-card-bg:    rgba(0,0,0,0.025);
    --esp-border:     rgba(0,0,0,0.10);
    --esp-left:       rgba(0,0,0,0.18);
    --esp-shimmer:    rgba(0,0,0,0.05);
}
html.dark {
    --esp-label:      rgba(255,255,255,0.30);
    --esp-title:      rgba(255,255,255,0.88);
    --esp-muted:      rgba(255,255,255,0.38);
    --esp-body:       rgba(255,255,255,0.75);
    --esp-row-border: rgba(255,255,255,0.06);
    --esp-card-bg:    rgba(255,255,255,0.035);
    --esp-border:     rgba(255,255,255,0.08);
    --esp-left:       rgba(255,255,255,0.20);
    --esp-shimmer:    rgba(255,255,255,0.10);
}
.esp-card {
    border-radius: 14px;
    border: 1px solid var(--esp-border);
    border-left: 3px solid var(--esp-left);
    background: linear-gradient(135deg, var(--esp-card-bg) 0%, transparent 100%);
    overflow: hidden;
    position: relative;
}
.esp-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--esp-shimmer), transparent);
}
.esp-label-top {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--esp-label);
    margin: 0 0 4px;
}
.esp-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--esp-row-border);
}
.esp-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.esp-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 5px;
}
.esp-text-title { color: var(--esp-title); font-size:15px; font-weight:800; line-height:1.2; margin:0; }
.esp-text-muted  { color: var(--esp-muted); font-size:12px; margin:4px 0 0; line-height:1.5; font-style:italic; }
.esp-text-body   { color: var(--esp-body);  font-size:13px; margin:0; line-height:1.5; }
.esp-text-empty  { color: var(--esp-muted); font-size:13px; margin:0; line-height:1.5; font-style:italic; }
</style>

<div class="esp-card">
    <div style="padding: 16px 18px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:{{ count($filas) ? '14px' : '0' }};">
            <lord-icon
                src="https://cdn.lordicon.com/jdgfsfzr.json"
                trigger="loop" delay="1600" stroke="bold"
                colors="primary:rgba(255,255,255,0.80),secondary:rgba(255,255,255,0.40)"
                style="width:36px;height:36px;flex-shrink:0;margin-top:-2px">
            </lord-icon>
            <div style="flex:1;min-width:0;">
                <p class="esp-label-top">Potestad disciplinaria</p>
                <p class="esp-text-title">Quién puede autorizar según el RIT</p>
                @if($todoVacio)
                    <p class="esp-text-muted">
                        El RIT no detalla potestades disciplinarias para estos tipos de sanción.
                        Verifique el reglamento interno directamente.
                    </p>
                @endif
            </div>
        </div>

        {{-- Filas por tipo de sanción --}}
        @foreach($filas as $fila)
            <div class="esp-row">
                <span class="esp-dot" style="background:{{ $fila['accent'] }};"></span>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:11px;font-weight:700;color:{{ $fila['accent'] }};margin:0 0 3px;text-transform:uppercase;letter-spacing:0.05em;">
                        {{ $fila['label'] }}
                    </p>
                    <p class="{{ $fila['vacio'] ? 'esp-text-empty' : 'esp-text-body' }}">
                        {{ $fila['texto'] }}
                    </p>
                </div>
            </div>
        @endforeach

    </div>
</div>
