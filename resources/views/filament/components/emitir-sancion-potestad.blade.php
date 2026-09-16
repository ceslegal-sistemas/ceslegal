{{--
    Tarjeta de Potestad Disciplinaria según el RIT
    Variables esperadas: $autoridadRit (array), $opcionesSancion (array)
    Rediseño .rit-hero (2026-09-16, pedido explícito del usuario de unificar
    todo el modal Emitir Sanción a este lenguaje visual) - los puntos de
    color por tipo de sanción se conservan (funcionales, no decorativos).
--}}
@include('filament.components.lupe-hero-styles')
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
.esp-row { display:flex; align-items:flex-start; gap:12px; padding:10px 0; border-bottom:1px solid rgba(148,163,184,.18); }
.esp-row:last-child { border-bottom:none; padding-bottom:0; }
html:not(.dark) .esp-row { border-bottom-color: rgba(0,0,0,.08); }
.esp-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:5px; }
</style>

<div class="rit-hero" style="padding:1.25rem 1.5rem;">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

        <span class="rit-badge rit-badge-ia">Potestad disciplinaria</span>
        <h1 class="rit-title">Quién puede autorizar según el RIT</h1>
        @if($todoVacio)
            <p class="rit-sub" style="font-style:italic;">
                El RIT no detalla potestades disciplinarias para estos tipos de sanción.
                Verifique el reglamento interno directamente.
            </p>
        @endif

        @if(count($filas))
            <div style="margin-top:1rem;">
                @foreach($filas as $fila)
                    <div class="esp-row">
                        <span class="esp-dot" style="background:{{ $fila['accent'] }};"></span>
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:11px;font-weight:700;color:{{ $fila['accent'] }};margin:0 0 3px;text-transform:uppercase;letter-spacing:0.05em;">
                                {{ $fila['label'] }}
                            </p>
                            <p class="rit-sub" style="{{ $fila['vacio'] ? 'font-style:italic;' : '' }}">
                                {{ $fila['texto'] }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</div>
