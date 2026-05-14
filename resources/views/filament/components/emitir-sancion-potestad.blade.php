{{--
    Tarjeta de Potestad Disciplinaria según el RIT
    Variables esperadas: $autoridadRit (array), $opcionesSancion (array)
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
.esp-card {
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.07);
    overflow: hidden;
    position: relative;
}
.esp-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
}
.esp-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(255,255,255,0.35);
    margin: 0 0 4px;
}
.esp-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.06);
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
</style>

<div class="esp-card"
     style="background: linear-gradient(135deg, rgba(129,140,248,0.09) 0%, rgba(255,255,255,0.015) 100%);
            border-left: 3px solid #818cf8;">
    <div style="padding: 16px 18px;">

        {{-- Header --}}
        <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:{{ count($filas) ? '14px' : '0' }};">
            <lord-icon
                src="https://cdn.lordicon.com/jdgfsfzr.json"
                trigger="loop" delay="1400" stroke="bold"
                colors="primary:#818cf8,secondary:#c7d2fe"
                style="width:38px;height:38px;flex-shrink:0;margin-top:-2px">
            </lord-icon>
            <div style="flex:1;min-width:0;">
                <p class="esp-label">Potestad disciplinaria</p>
                <p style="font-size:16px;font-weight:800;color:#818cf8;line-height:1.2;margin:0;">
                    Quién puede autorizar según el RIT
                </p>
                @if($todoVacio)
                    <p style="font-size:12px;color:rgba(255,255,255,0.40);margin:4px 0 0;line-height:1.5;">
                        El RIT no detalla las potestades disciplinarias para estos tipos de sanción.
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
                    <p style="font-size:11px;font-weight:700;color:{{ $fila['accent'] }};margin:0 0 3px;text-transform:uppercase;letter-spacing:0.04em;">
                        {{ $fila['label'] }}
                    </p>
                    <p style="font-size:13px;color:{{ $fila['vacio'] ? 'rgba(255,255,255,0.35)' : 'rgba(255,255,255,0.72)' }};margin:0;line-height:1.5;font-style:{{ $fila['vacio'] ? 'italic' : 'normal' }};">
                        {{ $fila['texto'] }}
                    </p>
                </div>
            </div>
        @endforeach

    </div>
</div>
