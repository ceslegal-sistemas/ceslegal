{{--
    Indicador de progreso de una Solicitud de Contrato - muestra los 4
    hitos del proceso a la vez (a diferencia de step-header.blade.php,
    que muestra un solo paso activo de un wizard). Los hitos ya
    superados se marcan como completos, el actual se resalta, los
    futuros quedan atenuados.

    Variables:
      $estadoActual        string      - uno de: pendiente|en_analisis|contrato_generado|finalizado|rechazado
      $solicitudId         int|null    - id de la SolicitudContrato, para armar el enlace de descarga (Task 6)
      $rutaContratoExiste  bool        - si es true, se muestra el botón "Ver Contrato"
--}}
@include('filament.components.pinfo-styles')

@php
    $hitos = [
        'pendiente'         => ['label' => 'Pendiente',         'lord' => 'https://cdn.lordicon.com/abgtphux.json', 'accent' => '#64748b'],
        'en_analisis'       => ['label' => 'En Análisis',       'lord' => 'https://cdn.lordicon.com/edcgvlnw.json', 'accent' => '#f59e0b'],
        'contrato_generado' => ['label' => 'Contrato Generado', 'lord' => 'https://cdn.lordicon.com/wpsdctqb.json', 'accent' => '#0ea5e9'],
        'finalizado'        => ['label' => 'Finalizado',        'lord' => 'https://cdn.lordicon.com/hqkfqrrm.json', 'accent' => '#16a34a'],
    ];
    $ordenHitos = array_keys($hitos);
    $esRechazado = $estadoActual === 'rechazado';
    $indiceActual = $esRechazado ? -1 : array_search($estadoActual, $ordenHitos, true);
@endphp

@once
    <style>
        .scp-track { display: flex; align-items: flex-start; gap: .5rem; margin: 1rem 0 1.25rem; }
        .scp-hito { flex: 1; text-align: center; opacity: .4; transition: opacity .3s; }
        .scp-hito.scp-activo, .scp-hito.scp-completo { opacity: 1; }
        .scp-hito-ic {
            display: inline-flex; align-items: center; justify-content: center;
            width: 44px; height: 44px; border-radius: 999px; margin-bottom: .4rem;
            background: rgba(var(--scp-rgb), .14); border: 2px solid rgba(var(--scp-rgb), .3);
        }
        .scp-hito.scp-activo .scp-hito-ic { border-color: var(--scp-a); box-shadow: 0 0 0 4px rgba(var(--scp-rgb), .18); }
        .scp-hito-label { font-size: .7rem; font-weight: 600; color: #94a3b8; }
        html:not(.dark) .scp-hito-label { color: #64748b; }
        .scp-hito.scp-activo .scp-hito-label, .scp-hito.scp-completo .scp-hito-label { color: var(--scp-a); }
        .scp-rechazado { padding: .75rem 1rem; border-radius: .75rem; background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.3); color: #dc2626; font-weight: 600; font-size: .875rem; }
        .scp-contrato-btn {
            display: inline-flex; align-items: center; gap: .5rem; margin-top: .5rem;
            padding: .6rem 1.1rem; background: #16a34a; color: #fff; border-radius: .6rem;
            font-size: .8125rem; font-weight: 700; text-decoration: none;
        }
    </style>
@endonce

@if ($esRechazado)
    <div class="scp-rechazado">Esta solicitud fue rechazada.</div>
@else
    <div class="scp-track">
        @foreach ($hitos as $clave => $hito)
            @php
                $idx = array_search($clave, $ordenHitos, true);
                $estado = $idx < $indiceActual ? 'scp-completo' : ($idx === $indiceActual ? 'scp-activo' : '');
                $rgb = null;
                $hex = ltrim($hito['accent'], '#');
                if (strlen($hex) === 6) {
                    $rgb = hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
                }
            @endphp
            <div class="scp-hito {{ $estado }}" style="--scp-a:{{ $hito['accent'] }};--scp-rgb:{{ $rgb }};">
                <span class="scp-hito-ic">
                    <lord-icon src="{{ $hito['lord'] }}" trigger="loop" delay="1500" stroke="bold"
                        colors="primary:{{ $hito['accent'] }},secondary:{{ $hito['accent'] }}"
                        style="width:24px;height:24px"></lord-icon>
                </span>
                <div class="scp-hito-label">{{ $hito['label'] }}</div>
            </div>
        @endforeach
    </div>
@endif

@if ($solicitudId && $rutaContratoExiste)
    <a href="{{ route('solicitud-contrato.descargar', ['solicitud' => $solicitudId]) }}"
        target="_blank" class="scp-contrato-btn">
        Ver Contrato
    </a>
@endif
