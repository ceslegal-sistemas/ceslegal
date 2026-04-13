@php
    $uid = 'er_' . substr(md5(uniqid()), 0, 8);

    $quienLabels = [
        'empleador' => 'El empleador directamente',
        'supervisor' => 'Supervisor o jefe inmediato',
        'rrhh' => 'Recursos Humanos',
        'compañero' => 'Un compañero de trabajo',
        'cliente' => 'Un cliente o proveedor',
        'otro' => 'Otro',
    ];
    $lugarLabels = [
        'planta' => 'Planta de producción',
        'oficina' => 'Oficina',
        'sede_principal' => 'Sede principal',
        'bodega' => 'Bodega / Almacén',
        'externo' => 'Lugar externo a la empresa',
        'virtual' => 'Entorno virtual / remoto',
        'otro' => $lugar_libre ?? 'Otro',
    ];
    $horarioLabels = ['si' => 'Sí', 'no' => 'No', 'parcial' => 'Parcialmente'];
    $trabajador = $trabajador_id ? \App\Models\Trabajador::find($trabajador_id) : null;

    $tipoDocLabel = match ($trabajador?->tipo_documento ?? '') {
        'CC' => 'C.C.',
        'CE' => 'C.E.',
        'TI' => 'T.I.',
        'PASS' => 'Pasaporte',
        default => $trabajador?->tipo_documento ?? '',
    };
@endphp

@verbatim
    <style>
        /* ── Keyframes ─────────────────────────────────────── */
        @keyframes er-up {
            from {
                opacity: 0;
                transform: translateY(16px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        @keyframes er-pop {
            from {
                opacity: 0;
                transform: scale(.6)
            }

            to {
                opacity: 1;
                transform: scale(1)
            }
        }

        @keyframes er-float-blue {

            0%,
            100% {
                transform: translate(0, 0)
            }

            40% {
                transform: translate(-18px, 14px)
            }

            70% {
                transform: translate(12px, -10px)
            }
        }

        @keyframes er-float-gold {

            0%,
            100% {
                transform: translate(0, 0)
            }

            35% {
                transform: translate(15px, -18px)
            }

            65% {
                transform: translate(-10px, 8px)
            }
        }

        .er-a1 {
            animation: er-up .55s cubic-bezier(.16, 1, .3, 1) both
        }

        .er-a2 {
            animation: er-up .55s .1s cubic-bezier(.16, 1, .3, 1) both
        }

        .er-a3 {
            animation: er-up .55s .2s cubic-bezier(.16, 1, .3, 1) both
        }

        .er-a4 {
            animation: er-up .55s .3s cubic-bezier(.16, 1, .3, 1) both
        }

        .er-icon-pop {
            animation: er-pop .6s .05s cubic-bezier(.34, 1.56, .64, 1) both
        }

        .er-orb-b {
            animation: er-float-blue 13s ease-in-out infinite
        }

        .er-orb-g {
            animation: er-float-gold 16s ease-in-out infinite
        }

        @media(prefers-reduced-motion:reduce) {

            .er-a1,
            .er-a2,
            .er-a3,
            .er-a4,
            .er-icon-pop,
            .er-orb-b,
            .er-orb-g {
                animation: none;
                opacity: .7;
                transform: none
            }

            .er-step,
            .er-doc-field {
                transition: none
            }
        }

        /* ── Hero ──────────────────────────────────────────── */
        .er-hero {
            position: relative;
            overflow: hidden;
            border-radius: 1.25rem;
            padding: 1.75rem 1.5rem 1.625rem;
            background: linear-gradient(150deg, #060f22 0%, #0d1f3c 55%, #060e20 100%);
        }

        @media(min-width:540px) {
            .er-hero {
                padding: 2.25rem 2rem 2rem
            }
        }

        html:not(.dark) .er-hero {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, .07);
            box-shadow: 0 4px 28px rgba(0, 0, 0, .07);
        }

        html:not(.dark) .er-orb-b {
            background: radial-gradient(circle, rgba(215, 75, 10, .22), transparent 70%) !important;
        }

        html:not(.dark) .er-orb-g {
            background: radial-gradient(circle, rgba(190, 130, 10, .26), transparent 70%) !important;
        }

        .er-overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 1;
            background: radial-gradient(ellipse 80% 90% at 50% 50%,
                    rgba(3, 8, 20, .80) 0%, rgba(3, 8, 20, .45) 55%, transparent 100%);
        }

        html:not(.dark) .er-overlay {
            background: radial-gradient(ellipse 75% 85% at 50% 40%,
                    rgba(255, 255, 255, .72) 0%, rgba(255, 255, 255, .38) 55%, transparent 100%);
        }

        /* ── Hero icon ring ────────────────────────────────── */
        .er-icon-ring {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: rgba(201, 168, 76, .13);
            border: 1.5px solid rgba(201, 168, 76, .38);
            margin-bottom: .875rem;
        }

        /* ── Hero typography ───────────────────────────────── */
        .er-hero-label {
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            margin: 0 0 .35rem;
            color: #c9a84c;
            text-shadow: 0 0 16px rgba(201, 168, 76, .6);
        }

        html:not(.dark) .er-hero-label {
            color: #92710d;
            text-shadow: none
        }

        .er-hero-title {
            font-size: 1.1875rem;
            font-weight: 700;
            letter-spacing: -.015em;
            line-height: 1.25;
            margin: 0 0 .5rem;
            color: #f1f5f9;
            text-shadow: 0 2px 20px rgba(0, 0, 0, .8);
        }

        @media(min-width:540px) {
            .er-hero-title {
                font-size: 1.375rem
            }
        }

        html:not(.dark) .er-hero-title {
            color: #0f172a;
            text-shadow: none
        }

        .er-hero-sub {
            font-size: .8125rem;
            line-height: 1.6;
            color: #94a3b8;
            margin: 0 0 1.25rem;
        }

        html:not(.dark) .er-hero-sub {
            color: #475569
        }

        /* ── Bullet items ──────────────────────────────────── */
        .er-bullets {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            margin-bottom: 1.25rem;
            text-align: left;
        }

        .er-bullet {
            display: flex;
            align-items: flex-start;
            gap: .625rem;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: .625rem;
            padding: .6rem .875rem;
            font-size: .8rem;
            color: #cbd5e1;
            line-height: 1.5;
        }

        html:not(.dark) .er-bullet {
            background: rgba(79, 70, 229, .04);
            border-color: rgba(79, 70, 229, .12);
            color: #374151;
        }

        .er-bullet-ico {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            margin-top: 1px;
            opacity: .75
        }

        .er-bullet strong {
            color: #e2e8f0;
            font-weight: 600
        }

        html:not(.dark) .er-bullet strong {
            color: #111827
        }

        /* ── Worker card inside hero ───────────────────────── */
        .er-worker {
            display: flex;
            align-items: center;
            gap: .75rem;
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: .875rem;
            padding: .75rem 1rem;
            text-align: left;
            margin-bottom: .875rem;
        }

        html:not(.dark) .er-worker {
            background: rgba(99, 102, 241, .05);
            border-color: rgba(99, 102, 241, .18);
        }

        .er-worker-ico {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            flex-shrink: 0;
            background: rgba(99, 102, 241, .15);
            border: 1px solid rgba(99, 102, 241, .28);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a5b4fc;
        }

        html:not(.dark) .er-worker-ico {
            background: rgba(79, 70, 229, .1);
            border-color: rgba(79, 70, 229, .2);
            color: #4f46e5;
        }

        .er-worker-name {
            font-size: .9375rem;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0 0 .15rem;
            line-height: 1.25;
        }

        html:not(.dark) .er-worker-name {
            color: #0f172a
        }

        .er-worker-meta {
            font-size: .775rem;
            color: #64748b;
            margin: 0;
            line-height: 1.4;
        }

        /* ── Status badge ──────────────────────────────────── */
        .er-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            padding: .4rem .9rem;
            border-radius: 2rem;
            border: 1px solid;
        }

        .er-badge.pending {
            background: rgba(245, 158, 11, .11);
            border-color: rgba(245, 158, 11, .32);
            color: #fde68a;
        }

        .er-badge.done {
            background: rgba(34, 197, 94, .11);
            border-color: rgba(34, 197, 94, .32);
            color: #86efac;
        }

        html:not(.dark) .er-badge.pending {
            background: rgba(245, 158, 11, .08);
            border-color: rgba(245, 158, 11, .28);
            color: #92400e;
        }

        html:not(.dark) .er-badge.done {
            background: rgba(34, 197, 94, .08);
            border-color: rgba(34, 197, 94, .28);
            color: #166534;
        }

        /* ── Section divider ───────────────────────────────── */
        .er-rule {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1rem;
        }

        .er-rule-line {
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, .08)
        }

        html:not(.dark) .er-rule-line {
            background: #e5e7eb
        }

        .er-rule-txt {
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            white-space: nowrap;
            color: #475569;
        }

        html:not(.dark) .er-rule-txt {
            color: #9ca3af
        }

        /* ── Document summary ──────────────────────────────── */
        .er-doc {
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, .09);
            overflow: hidden;
        }

        html:not(.dark) .er-doc {
            border-color: rgba(0, 0, 0, .08);
            box-shadow: 0 1px 6px rgba(0, 0, 0, .05);
        }

        /* Cada sección del documento */
        .er-section {
            padding: 1rem 1.125rem;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
        }

        .er-section:last-child {
            border-bottom: none
        }

        html:not(.dark) .er-section {
            border-bottom-color: rgba(0, 0, 0, .06)
        }

        /* Grid para secciones en 2 col */
        .er-sec-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .er-sec-grid .er-section {
            border-bottom: none;
            border-right: 1px solid rgba(255, 255, 255, .07);
        }

        .er-sec-grid .er-section:last-child {
            border-right: none
        }

        html:not(.dark) .er-sec-grid .er-section {
            border-right-color: rgba(0, 0, 0, .06)
        }

        @media(max-width:500px) {
            .er-sec-grid {
                grid-template-columns: 1fr
            }

            .er-sec-grid .er-section {
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, .07)
            }

            html:not(.dark) .er-sec-grid .er-section {
                border-bottom-color: rgba(0, 0, 0, .06)
            }

            .er-sec-grid .er-section:last-child {
                border-bottom: none
            }
        }

        /* Encabezado de sección */
        .er-sec-header {
            display: flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: .5rem;
        }

        .er-sec-ico {
            width: 13px;
            height: 13px;
            flex-shrink: 0;
            opacity: .6;
        }

        .er-sec-label {
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #64748b;
            margin: 0;
        }

        html:not(.dark) .er-sec-label {
            color: #9ca3af
        }

        /* Valor principal */
        .er-sec-val {
            font-size: .9rem;
            font-weight: 500;
            color: #e2e8f0;
            margin: 0;
            line-height: 1.55;
        }

        .er-sec-val.empty {
            color: #475569;
            font-style: italic;
            font-weight: 400
        }

        html:not(.dark) .er-sec-val {
            color: #111827
        }

        html:not(.dark) .er-sec-val.empty {
            color: #9ca3af
        }

        /* Sub-valor */
        .er-sec-sub {
            font-size: .775rem;
            color: #64748b;
            margin: .25rem 0 0;
            line-height: 1.4;
        }

        html:not(.dark) .er-sec-sub {
            color: #6b7280
        }

        /* Descripción con fade */
        .er-desc-text {
            white-space: pre-line;
            font-size: .875rem;
            line-height: 1.65;
        }

        /* Tags (evidencias / testigos) */
        .er-tag {
            display: inline-block;
            font-size: .7rem;
            font-weight: 500;
            border-radius: .3rem;
            padding: .1rem .45rem;
            margin: .15rem .15rem 0 0;
            background: rgba(99, 102, 241, .14);
            color: #a5b4fc;
        }

        html:not(.dark) .er-tag {
            background: rgba(79, 70, 229, .09);
            color: #4338ca
        }

        /* Color accent bar por sección (borde izquierdo) */
        .er-section[data-color] {
            border-left: 3px solid var(--sc, rgba(99, 102, 241, .5));
        }
    </style>
@endverbatim

<div style="display:flex;flex-direction:column;gap:1.125rem;padding:.25rem 0;">

    {{-- ══════════════════ HERO ══════════════════ --}}
    <div class="er-hero er-a1" style="text-align:center;">

        {{-- Orbs CSS only — sin canvas ni luciérnagas --}}
        <div style="position:absolute;inset:0;pointer-events:none;overflow:hidden;">
            <div class="er-orb-b"
                style="position:absolute;width:240px;height:240px;top:-60px;right:-40px;border-radius:50%;background:radial-gradient(circle,rgba(30,58,138,.5),transparent 70%);filter:blur(24px);">
            </div>
            <div class="er-orb-g"
                style="position:absolute;width:180px;height:180px;bottom:-45px;left:-35px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,.22),transparent 70%);filter:blur(22px);">
            </div>
        </div>
        <div class="er-overlay"></div>

        <div style="position:relative;z-index:2;">

            {{-- Ícono --}}
            <div class="er-icon-ring er-icon-pop">
                <lord-icon src="https://cdn.lordicon.com/edcgvlnw.json" trigger="loop" delay="600" stroke="bold"
                    colors="primary:#c9a84c,secondary:#f59e0b,tertiary:#fef3c7" data-pt-icon
                    data-pt-dark="primary:#c9a84c,secondary:#f59e0b,tertiary:#fef3c7"
                    data-pt-light="primary:#92710d,secondary:#b45309,tertiary:#fde68a"
                    style="width:40px;height:40px;flex-shrink:0">
                </lord-icon>
            </div>

            <p class="er-hero-label">Paso 6 de 6</p>
            <h2 class="er-hero-title">Revisión y envío</h2>
            <p class="er-hero-sub">
                Verifique el resumen del expediente, genere la descripción jurídica con IA
                y programe la audiencia de descargos.
            </p>

            {{-- Bullets --}}
            <div class="er-bullets er-a2" style="max-width:520px;margin-left:auto;margin-right:auto;">
                <div class="er-bullet">
                    <lord-icon src="https://cdn.lordicon.com/fikcyfpp.json"
                        trigger="loop" delay="500" stroke="bold"
                        colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                        data-pt-icon
                        data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                        data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                        style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
                    </lord-icon>
                    <span>Revise que los datos del resumen sean <strong>correctos y completos</strong> antes de continuar.</span>
                </div>
                <div class="er-bullet">
                    <lord-icon src="https://cdn.lordicon.com/vgwutnhw.json"
                        trigger="loop" delay="500" stroke="bold"
                        colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                        data-pt-icon
                        data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                        data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                        style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
                    </lord-icon>
                    <span>La <strong>descripción jurídica</strong> la redacta la IA — puede editarla antes de crear el proceso.</span>
                </div>
                <div class="er-bullet">
                    <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json"
                        trigger="loop" delay="500" stroke="bold"
                        colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                        data-pt-icon
                        data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                        data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                        style="width:20px;height:20px;flex-shrink:0;margin-top:1px">
                    </lord-icon>
                    <span>Al crear el proceso se enviará <strong>automáticamente la citación</strong> al correo del trabajador con el enlace de la audiencia virtual.</span>
                </div>
            </div>

            {{-- Trabajador --}}
            @if ($trabajador)
                <div class="er-worker er-a3" style="max-width:480px;margin:0 auto .875rem;">
                    {{-- <div class="er-worker-ico">
                    <svg style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                    </svg>
                </div> --}}
                    <lord-icon src="https://cdn.lordicon.com/kdduutaw.json" trigger="loop" delay="500" stroke="bold"
                        state="hover-looking-around" colors="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                        data-pt-icon data-pt-dark="primary:#a5b4fc,secondary:#818cf8,tertiary:#e2e8f0"
                        data-pt-light="primary:#4f46e5,secondary:#6366f1,tertiary:#c7d2fe"
                        style="width:25px;height:25px;flex-shrink:0">
                    </lord-icon>
                    <div style="min-width:0;flex:1">
                        <p class="er-worker-name">{{ $trabajador->nombre_completo }}</p>
                        <p class="er-worker-meta">{{ $trabajador->cargo ?? 'Sin cargo' }} &nbsp;·&nbsp;
                            {{ $tipoDocLabel }} {{ $trabajador->numero_documento }}</p>
                    </div>
                </div>
            @endif

            {{-- Badge estado --}}
            @if ($chat_listo)
                <span class="er-badge done er-a3">
                    <svg style="width:11px;height:11px;flex-shrink:0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Descripción generada · Listo para enviar
                </span>
            @else
                <span class="er-badge pending er-a3">
                    <svg style="width:11px;height:11px;flex-shrink:0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    En revisión · Genere la descripción jurídica
                </span>
            @endif

        </div>
    </div>

    {{-- ══════════════════ RESUMEN DEL EXPEDIENTE ══════════════════ --}}
    <div class="er-a4">
        <div class="er-rule">
            <div class="er-rule-line"></div>
            <span class="er-rule-txt">Resumen del expediente</span>
            <div class="er-rule-line"></div>
        </div>

        <div class="er-doc">

            {{-- Hechos --}}
            <div class="er-section" data-color style="--sc:#a78bfa;background:rgba(167,139,250,.04)">
                <div class="er-sec-header">
                    <svg class="er-sec-ico" style="color:#a78bfa" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <p class="er-sec-label">Hechos reportados</p>
                </div>
                @if ($descripcion)
                    <p class="er-sec-val er-desc-text">{{ $descripcion }}</p>
                @else
                    <p class="er-sec-val empty">Sin descripción registrada</p>
                @endif
            </div>

            {{-- Cuándo/dónde + Quién reporta --}}
            <div class="er-sec-grid" style="border-bottom:1px solid rgba(255,255,255,.07);">
                <div class="er-section" data-color style="--sc:#34d399">
                    <div class="er-sec-header">
                        <svg class="er-sec-ico" style="color:#34d399" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        <p class="er-sec-label">Cuándo y dónde</p>
                    </div>
                    <p class="er-sec-val {{ !$fecha ? 'empty' : '' }}">
                        {{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : 'Fecha no indicada' }}
                        @if ($hora)
                            &nbsp;·&nbsp; {{ \Carbon\Carbon::parse($hora)->format('g:i A') }}
                        @endif
                    </p>
                    <p class="er-sec-sub">
                        {{ $lugarLabels[$lugar_tipo] ?? ($lugar_tipo ?: '—') }}
                        @if ($en_horario)
                            &nbsp;·&nbsp; {{ $horarioLabels[$en_horario] ?? $en_horario }}
                        @endif
                    </p>
                </div>

                <div class="er-section" data-color style="--sc:#60a5fa">
                    <div class="er-sec-header">
                        <svg class="er-sec-ico" style="color:#60a5fa" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        <p class="er-sec-label">Reportado por</p>
                    </div>
                    <p class="er-sec-val {{ !$quien_reporta ? 'empty' : '' }}">
                        {{ $quienLabels[$quien_reporta] ?? ($quien_reporta ?: 'No indicado') }}
                    </p>
                </div>
            </div>

            {{-- Evidencias + Testigos --}}
            <div class="er-sec-grid">
                <div class="er-section" data-color style="--sc:#fb923c">
                    <div class="er-sec-header">
                        <svg class="er-sec-ico" style="color:#fb923c" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                        </svg>
                        <p class="er-sec-label">Evidencias</p>
                    </div>
                    @if ($tiene_evidencias === 'si')
                        <p class="er-sec-val">Sí, con archivos adjuntos</p>
                    @elseif($tiene_evidencias === 'no')
                        <p class="er-sec-val empty">Sin evidencia registrada</p>
                    @else
                        <p class="er-sec-val empty">No indicado</p>
                    @endif
                </div>

                <div class="er-section" data-color style="--sc:#f472b6">
                    <div class="er-sec-header">
                        <svg class="er-sec-ico" style="color:#f472b6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                        </svg>
                        <p class="er-sec-label">Testigos</p>
                    </div>
                    @if ($hubo_testigos === 'si' && !empty($testigos))
                        @foreach ($testigos as $t)
                            @if (!empty($t['nombre']))
                                <span
                                    class="er-tag">{{ $t['nombre'] }}{{ !empty($t['cargo']) ? ' — ' . $t['cargo'] : '' }}</span>
                            @endif
                        @endforeach
                    @elseif($hubo_testigos === 'no')
                        <p class="er-sec-val empty">Sin testigos</p>
                    @else
                        <p class="er-sec-val empty">No indicado</p>
                    @endif
                </div>
            </div>

        </div>
    </div>

</div>
