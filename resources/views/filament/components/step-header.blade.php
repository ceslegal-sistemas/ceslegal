{{--
    Encabezado compacto de paso para los wizards. Da orientación (Paso X de Y),
    progreso (barra de color por paso) y título, manteniendo el lenguaje visual
    del hero de bienvenida (mismo acento por paso) y de las tarjetas de info.

    Variables:
      $step     int          - número del paso actual (1..N)
      $total    int          - total de pasos
      $title    string       - título del paso
      $accent   string       - color hex de acento (coincide con la tarjeta del hero)
      $lord     string|null  - src del lord-icon (opcional)
      $subtitle string|null  - descripción corta (opcional)
--}}
@include('filament.components.pinfo-styles')

@php
    $pct = (int) round(($step / max(1, $total)) * 100);
    $hex = ltrim($accent ?? '#f97316', '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $rgb = hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
    $lord = $lord ?? null;
    $subtitle = $subtitle ?? null;
@endphp

@once
    <style>
        .sh-card {
            position: relative;
            border-radius: 1rem;
            padding: 1rem 1.15rem 1.1rem;
            margin-bottom: .25rem;
            background: linear-gradient(135deg, rgba(var(--sh-rgb), .12), rgba(var(--sh-rgb), .02));
            border: 1px solid rgba(var(--sh-rgb), .22);
        }

        html:not(.dark) .sh-card {
            background: linear-gradient(135deg, rgba(var(--sh-rgb), .10), rgba(255, 255, 255, .65));
            border-color: rgba(var(--sh-rgb), .30);
            box-shadow: 0 2px 10px rgba(0, 0, 0, .04);
        }

        .sh-top {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .sh-ic {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: .7rem;
            flex-shrink: 0;
            background: rgba(var(--sh-rgb), .14);
            border: 1px solid rgba(var(--sh-rgb), .30);
        }

        .sh-meta {
            flex: 1;
            min-width: 0;
        }

        .sh-eyebrow {
            margin: 0 0 .12rem;
            font-size: .625rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--sh-a);
        }

        .sh-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -.01em;
            color: #f1f5f9;
        }

        html:not(.dark) .sh-title {
            color: #0f172a;
        }

        .sh-badge {
            flex-shrink: 0;
            font-size: .75rem;
            font-weight: 700;
            color: var(--sh-a);
            background: rgba(var(--sh-rgb), .12);
            border: 1px solid rgba(var(--sh-rgb), .28);
            border-radius: 999px;
            padding: .15rem .55rem;
        }

        .sh-sub {
            margin: .6rem 0 0;
            font-size: .8rem;
            line-height: 1.5;
            color: #94a3b8;
        }

        html:not(.dark) .sh-sub {
            color: #475569;
        }

        .sh-track {
            margin-top: .8rem;
            height: 6px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(var(--sh-rgb), .14);
        }

        html:not(.dark) .sh-track {
            background: rgba(var(--sh-rgb), .16);
        }

        .sh-fill {
            height: 100%;
            width: var(--sh-pct);
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(var(--sh-rgb), .65), var(--sh-a));
            box-shadow: 0 0 12px rgba(var(--sh-rgb), .5);
            transform-origin: left;
            animation: sh-fill .85s .15s cubic-bezier(.16, 1, .3, 1) both;
        }

        @keyframes sh-fill {
            from { transform: scaleX(0); }
            to   { transform: scaleX(1); }
        }

        @keyframes sh-enter {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: none; }
        }

        .sh-enter {
            animation: sh-enter .5s cubic-bezier(.16, 1, .3, 1) both;
        }

        @media (prefers-reduced-motion: reduce) {
            .sh-enter, .sh-fill { animation: none; }
            .sh-fill { transform: none; }
        }
    </style>
@endonce

<div class="sh-card sh-enter" style="--sh-a:{{ $accent }};--sh-rgb:{{ $rgb }};--sh-pct:{{ $pct }}%;">
    <div class="sh-top">
        @if ($lord)
            <span class="sh-ic">
                <lord-icon src="{{ $lord }}" trigger="loop" delay="800" stroke="bold"
                    colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
                    data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                    style="width:30px;height:30px">
                </lord-icon>
            </span>
        @endif
        <div class="sh-meta">
            <p class="sh-eyebrow">Paso {{ $step }} de {{ $total }}</p>
            <h3 class="sh-title">{{ $title }}</h3>
        </div>
        <span class="sh-badge">{{ $pct }}%</span>
    </div>

    @if ($subtitle)
        <p class="sh-sub">{{ $subtitle }}</p>
    @endif

    <div class="sh-track">
        <div class="sh-fill"></div>
    </div>
</div>
