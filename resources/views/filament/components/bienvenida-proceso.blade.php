@php
    $uid    = 'bv_' . substr(md5(uniqid()), 0, 8);
    $nombre = auth()->user()?->name ?? 'usuario';

    /* Embers (light mode) — golden sparks rising from bottom */
    $emberColors = ['200,60,5','230,90,10','255,130,20','180,45,0','240,110,15','210,70,5'];
    $embers = [];
    for ($i = 0; $i < 48; $i++) {
        $c  = $emberColors[array_rand($emberColors)];
        $sz = round(mt_rand(15, 45) / 10, 1);  // 1.5 – 4.5 px
        $embers[] = [
            'x'     => mt_rand(2, 98),
            'sz'    => $sz,
            'c'     => $c,
            'g'     => (int)($sz * mt_rand(25, 50) / 10),
            'dur'   => round(mt_rand(35, 75) / 10, 1), // rise duration s
            'del'   => round(mt_rand(0,  90) / 10, 1), // delay s
            'drift' => mt_rand(-40, 40),                // horizontal drift px
        ];
    }

    /* Fireflies — generated server-side for deterministic layout */
    $ffColors = ['201,168,76','255,235,120','255,255,200','190,215,255','245,195,255','255,210,90'];
    $fireflies = [];
    for ($i = 0; $i < 52; $i++) {
        $c  = $ffColors[array_rand($ffColors)];
        $sz = round(mt_rand(20, 60) / 10, 1);   // 2 – 6 px
        $g  = (int)($sz * mt_rand(35, 65) / 10); // glow radius
        $fireflies[] = [
            'x'   => mt_rand(2, 97),
            'y'   => mt_rand(4, 96),
            'sz'  => $sz,
            'g'   => $g,
            'c'   => $c,
            'tw'  => round(mt_rand(18, 50) / 10, 1), // twinkle duration s
            'del' => round(mt_rand(0,  60) / 10, 1), // animation delay s
            'dr'  => round(mt_rand(70, 160) / 10, 1), // drift duration s
        ];
    }
@endphp

@verbatim
    <style>
        /* ── Keyframes ───────────────────────────────── */
        @keyframes bv-up {
            from {
                opacity: 0;
                transform: translateY(20px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        @keyframes bv-pop {
            from {
                opacity: 0;
                transform: scale(.55)
            }

            to {
                opacity: 1;
                transform: scale(1)
            }
        }

        @keyframes bv-glow {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(201, 168, 76, .5)
            }

            65% {
                box-shadow: 0 0 0 14px rgba(201, 168, 76, 0)
            }
        }

        .bv-a1 {
            animation: bv-up .6s cubic-bezier(.16, 1, .3, 1) both
        }

        .bv-a2 {
            animation: bv-up .6s .12s cubic-bezier(.16, 1, .3, 1) both
        }

        .bv-a3 {
            animation: bv-up .6s .24s cubic-bezier(.16, 1, .3, 1) both
        }

        .bv-icon-ring {
            animation: bv-pop .7s .08s cubic-bezier(.34, 1.56, .64, 1) both, bv-glow 3s 1.2s ease-in-out infinite
        }

        /* ── Hero — mobile first ─────────────────────── */
        .bv-hero {
            position: relative;
            overflow: hidden;
            border-radius: 1.125rem;
            padding: 2rem 1.25rem 1.75rem;
            text-align: center;
            background: linear-gradient(155deg, #060f22 0%, #091830 50%, #060e20 100%);
        }

        @media(min-width:540px) {
            .bv-hero {
                border-radius: 1.375rem;
                padding: 2.75rem 2.25rem 2.5rem;
            }
        }

        html:not(.dark) .bv-hero {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, .06);
            box-shadow: 0 4px 24px rgba(0, 0, 0, .06);
        }

        html:not(.dark) .bv-hero-orb-blue {
            /* Orb azul → brasa naranja en light */
            background: radial-gradient(circle, rgba(220,80,10,.28), transparent 70%) !important;
        }

        html:not(.dark) .bv-hero-orb-gold {
            background: radial-gradient(circle, rgba(201,140,20,.32), transparent 70%) !important;
        }

        /* Glow de fuego en la base del hero (light) */
        html:not(.dark) .bv-fire-base {
            display: block;
            background: radial-gradient(ellipse 85% 55% at 50% 100%,
                rgba(255,110,20,.22) 0%,
                rgba(255,160,40,.10) 50%,
                transparent 100%);
        }
        .bv-fire-base { display: none; position: absolute; inset: 0; pointer-events: none; z-index: 0; }

        /* ── Hero title — mobile first ───────────────── */
        .bv-title {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -.02em;
            line-height: 1.25;
            margin: 0 0 .75rem;
        }

        @media(min-width:540px) {
            .bv-title {
                font-size: 1.625rem;
            }
        }

        /* ── Subtitle max-width — only on larger screens */
        .bv-subtitle-wrap {
            max-width: none;
        }

        @media(min-width:540px) {
            .bv-subtitle-wrap {
                max-width: 420px;
                margin-left: auto;
                margin-right: auto;
            }
        }

        /* ── Divider ─────────────────────────────────── */
        .bv-rule {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: .875rem;
        }

        .bv-rule-line {
            flex: 1;
            height: 1px;
        }

        .bv-rule-label {
            font-size: .625rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* ── Process grid — 1 col mobile, 2 col desktop ─ */
        .bv-process-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: .625rem;
        }

        @media(min-width:560px) {
            .bv-process-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: .75rem;
            }
        }

        /* ── Step item ───────────────────────────────── */
        .bv-step-item {
            border-radius: 1rem;
            padding: .875rem 1rem;
            cursor: default;
            transition: transform .35s cubic-bezier(.16,1,.3,1), box-shadow .35s ease;
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
            background: rgba(255, 255, 255, .055);
            border: 1px solid rgba(255, 255, 255, .1);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            display: flex;
            align-items: flex-start;
            gap: .875rem;
        }

        /* top-edge color bar */
        .bv-step-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2.5px;
            background: var(--sc, #c9a84c);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .28s ease;
            z-index: 1;
        }

        .bv-step-item:hover::before {
            transform: scaleX(1);
        }

        /* glare / shimmer layer */
        .bv-step-item::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: radial-gradient(circle at var(--mx,50%) var(--my,50%), rgba(255,255,255,.13) 0%, transparent 62%);
            opacity: 0;
            transition: opacity .3s;
            pointer-events: none;
            z-index: 0;
        }

        .bv-step-item:hover::after { opacity: 1; }

        html:not(.dark) .bv-step-item {
            background: rgba(255, 255, 255, .82);
            border-color: rgba(0, 0, 0, .08);
            box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
        }

        html:not(.dark) .bv-step-item::after {
            background: radial-gradient(circle at var(--mx,50%) var(--my,50%), rgba(201,168,76,.12) 0%, transparent 62%);
        }

        /* ── Step number badge ───────────────────────── */
        .bv-step-num {
            width: 32px;
            height: 32px;
            border-radius: .5rem;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .875rem;
            font-weight: 700;
            background: var(--ib, rgba(201, 168, 76, .12));
            border: 1px solid var(--ibc, rgba(201, 168, 76, .25));
            color: var(--sc, #c9a84c);
            margin-top: .1rem;
        }

        /* ── Step tag ────────────────────────────────── */
        .bv-step-tag {
            display: inline-block;
            font-size: .575rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--sc, #c9a84c);
            opacity: .8;
            margin-bottom: .2rem;
        }

        /* ── Color tokens — dark default ─────────────── */
        .t-h {
            color: #f1f5f9
        }

        .t-s {
            color: #94a3b8
        }

        .t-m {
            color: #cbd5e1
        }

        .t-gold {
            color: #c9a84c
        }

        .t-ct {
            color: #f1f5f9;
            font-size: .875rem;
            font-weight: 600;
            margin: 0 0 .2rem;
            line-height: 1.3
        }

        .t-cb {
            color: #94a3b8;
            font-size: .8rem;
            margin: 0;
            line-height: 1.5
        }

        .t-dl {
            color: #475569
        }

        .bv-rule-line-c {
            background: rgba(255, 255, 255, .08)
        }

        html:not(.dark) .t-h {
            color: #0f172a
        }

        html:not(.dark) .t-s {
            color: #64748b
        }

        html:not(.dark) .t-m {
            color: #334155
        }

        html:not(.dark) .t-gold {
            color: #92710d
        }

        html:not(.dark) .t-ct {
            color: #0f172a
        }

        html:not(.dark) .t-cb {
            color: #475569
        }

        html:not(.dark) .t-dl {
            color: #94a3b8
        }

        html:not(.dark) .bv-rule-line-c {
            background: #e2e8f0
        }

        /* ── Next hint ───────────────────────────────── */
        .bv-next-hint {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-top: .875rem;
            padding: .75rem 1rem;
            border-radius: .875rem;
            background: rgba(201, 168, 76, .07);
            border: 1px solid rgba(201, 168, 76, .18);
            font-size: .8125rem;
            color: #94a3b8;
            line-height: 1.5;
        }

        html:not(.dark) .bv-next-hint {
            background: rgba(146, 113, 13, .06);
            border-color: rgba(146, 113, 13, .18);
            color: #475569;
        }

        .bv-next-hint strong {
            color: #c9a84c;
            font-weight: 600;
        }

        html:not(.dark) .bv-next-hint strong {
            color: #92710d;
        }

        /* ── Hero overlay (dark / light) ────────────── */
        .bv-hero-overlay {
            position: absolute; inset: 0; pointer-events: none; z-index: 1;
            background: radial-gradient(ellipse 75% 85% at 50% 50%,
                rgba(3,8,20,.84) 0%, rgba(3,8,20,.52) 50%, transparent 100%);
        }
        html:not(.dark) .bv-hero-overlay {
            /* velo blanco sutil — preserva el mármol y da contraste al texto */
            background: radial-gradient(ellipse 72% 80% at 50% 45%,
                rgba(255,255,255,.68) 0%, rgba(255,255,255,.35) 55%, transparent 100%);
        }
        /* Canvas: misma opacidad en ambos modos */
        html:not(.dark) .bv-canvas-el  { opacity: .45 !important; }

        /* ── Embers (light mode only) ───────────────── */
        @keyframes bv-ember-rise {
            0%   { transform: translateY(0)     translateX(0)                  scale(1);   opacity: 0;   }
            8%   { opacity: .92; }
            80%  { opacity: .45; }
            100% { transform: translateY(-320px) translateX(var(--drift, 20px)) scale(.2); opacity: 0;   }
        }
        .bv-ember {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            bottom: -4px;
            will-change: transform, opacity;
            animation: bv-ember-rise var(--dur, 5s) var(--del, 0s) ease-in infinite;
        }
        /* Embers only in light mode; fireflies only in dark */
        html.dark    .bv-ember   { display: none; }
        html:not(.dark) .bv-firefly { opacity: 0 !important; }

        /* ── Title shadow adaptive ───────────────────── */
        .bv-title-shadow { text-shadow: 0 2px 24px rgba(0,0,0,.88), 0 1px 4px rgba(0,0,0,.65); }
        html:not(.dark) .bv-title-shadow { text-shadow: 0 1px 12px rgba(180,80,10,.2), 0 2px 4px rgba(0,0,0,.08); }

        /* ── Firefly twinkle & drift ─────────────────── */
        @keyframes bv-twinkle {
            0%, 100% { opacity: .04; transform: scale(.3);  }
            45%, 55% { opacity: 1;   transform: scale(1.3); }
        }
        @keyframes bv-drift {
            0%, 100% { transform: translate(0, 0); }
            30%      { transform: translate(10px, -16px); }
            65%      { transform: translate(-8px, -10px); }
        }
        .bv-firefly {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            will-change: transform, opacity;
            animation:
                bv-twinkle var(--tw) var(--del) ease-in-out infinite,
                bv-drift   var(--dr) var(--del) ease-in-out infinite;
        }

        /* ── Orb float animations ────────────────────── */
        @keyframes bv-float-blue {
            0%   { transform: translate(0,0) scale(1) }
            25%  { transform: translate(-22px, 18px) scale(1.08) }
            55%  { transform: translate(14px, -14px) scale(.94) }
            80%  { transform: translate(-8px, 22px) scale(1.04) }
            100% { transform: translate(0,0) scale(1) }
        }
        @keyframes bv-float-gold {
            0%   { transform: translate(0,0) scale(1) }
            30%  { transform: translate(18px, -22px) scale(1.12) }
            60%  { transform: translate(-12px, 10px) scale(.88) }
            85%  { transform: translate(8px, -14px) scale(1.06) }
            100% { transform: translate(0,0) scale(1) }
        }
        @keyframes bv-float-extra {
            0%   { transform: translate(0,0) scale(1); opacity:.18 }
            40%  { transform: translate(30px,-20px) scale(1.2); opacity:.28 }
            100% { transform: translate(0,0) scale(1); opacity:.18 }
        }

        .bv-hero-orb-blue {
            animation: bv-float-blue 11s ease-in-out infinite;
        }
        .bv-hero-orb-gold {
            animation: bv-float-gold 14s ease-in-out infinite;
        }
        .bv-hero-orb-extra {
            animation: bv-float-extra 9s 2s ease-in-out infinite;
        }

        @media(prefers-reduced-motion:reduce) {

            .bv-a1,
            .bv-a2,
            .bv-a3,
            .bv-icon-ring,
            .bv-hero-orb-blue,
            .bv-hero-orb-gold,
            .bv-hero-orb-extra,
            .bv-firefly {
                animation: none;
                opacity: .6;
                transform: none
            }

            .bv-step-item {
                transition: none
            }
        }
    </style>
@endverbatim

<div style="display:flex;flex-direction:column;gap:1.375rem;padding:.25rem 0;">

    {{-- ══════════════════════════ HERO ══════════════════════════ --}}
    <div class="bv-hero bv-a1" id="{{ $uid }}_hero">
        <canvas id="{{ $uid }}_canvas" class="bv-canvas-el"
            style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;opacity:.5;"></canvas>

        <div style="position:absolute;inset:0;pointer-events:none;overflow:hidden;">
            <div class="bv-hero-orb-blue"
                style="position:absolute;width:280px;height:280px;top:-70px;right:-50px;border-radius:50%;background:radial-gradient(circle,rgba(30,58,138,.55),transparent 70%);filter:blur(44px);transition:background .4s;">
            </div>
            <div class="bv-hero-orb-gold"
                style="position:absolute;width:200px;height:200px;bottom:-50px;left:-40px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,.25),transparent 70%);filter:blur(40px);transition:background .4s;">
            </div>
            <div class="bv-hero-orb-extra"
                style="position:absolute;width:180px;height:180px;top:30%;left:20%;border-radius:50%;background:radial-gradient(circle,rgba(139,92,246,.22),transparent 70%);filter:blur(50px);">
            </div>

            {{-- ── Embers (light mode) ── --}}
            @foreach ($embers as $e)
                <div class="bv-ember" style="
                    left:{{ $e['x'] }}%;
                    width:{{ $e['sz'] }}px;
                    height:{{ $e['sz'] }}px;
                    background: rgb({{ $e['c'] }});
                    box-shadow: 0 0 {{ $e['g'] }}px {{ $e['g'] }}px rgba({{ $e['c'] }},.6),
                                0 0 {{ $e['g'] * 2 }}px {{ $e['g'] * 3 }}px rgba(255,160,30,.22);
                    --drift: {{ $e['drift'] }}px;
                    --dur:   {{ $e['dur'] }}s;
                    --del:   {{ $e['del'] }}s;
                "></div>
            @endforeach

            {{-- ── Fireflies (dark mode) ── --}}
            @foreach ($fireflies as $f)
                <div class="bv-firefly" style="
                    left:{{ $f['x'] }}%;
                    top:{{ $f['y'] }}%;
                    width:{{ $f['sz'] }}px;
                    height:{{ $f['sz'] }}px;
                    background: rgb({{ $f['c'] }});
                    box-shadow: 0 0 {{ $f['g'] }}px {{ $f['g'] * 2 }}px rgba({{ $f['c'] }},.55),
                                0 0 {{ $f['g'] * 4 }}px {{ $f['g'] * 3 }}px rgba({{ $f['c'] }},.18);
                    --tw: {{ $f['tw'] }}s;
                    --del: {{ $f['del'] }}s;
                    --dr: {{ $f['dr'] }}s;
                "></div>
            @endforeach
        </div>

        {{-- Overlay central: oscuro en dark, blanco suave en light --}}
        <div class="bv-fire-base"></div>
        <div class="bv-hero-overlay"></div>

        <div style="position:relative;z-index:2;">
            <div class="bv-icon-ring"
                style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:50%;background:rgba(201,168,76,.12);border:1.5px solid rgba(201,168,76,.35);margin-bottom:1.125rem;">
                <svg style="width:28px;height:28px;color:#c9a84c" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 4v16M6 8h12M6 8 3 14h6zm12 0-3 6h6zM9 20h6" />
                </svg>
            </div>

            <p class="t-gold"
                style="font-size:.7rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;margin:0 0 .4rem;text-shadow:0 0 18px rgba(201,168,76,.65),0 2px 8px rgba(0,0,0,.7);">
                Proceso Disciplinario Laboral
            </p>

            <h1 class="bv-title t-h bv-title-shadow">Asistente de Gestión Jurídica</h1>

            <div style="display:flex;gap:.5rem;margin-top:1.125rem;flex-wrap:wrap;justify-content:center;">
                <p class="t-m bv-subtitle-wrap" style="font-weight:500;font-size:.875rem;line-height:1.65;margin:0;text-align:center;text-shadow:0 1px 10px rgba(0,0,0,.0);">
                    Bienvenido/a, <strong class="t-gold" style="font-weight:500;">{{ $nombre }}</strong>.
                    Le guiaremos paso a paso para registrar el proceso conforme al
                    <span class="t-gold" style="font-weight:500;">Código Sustantivo del Trabajo</span>.
                </p>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════ 6 PASOS DEL PROCESO ══════════════════════════ --}}
    <div class="bv-a2">
        <div class="bv-rule">
            <div class="bv-rule-line bv-rule-line-c"></div>
            <span class="bv-rule-label t-dl">El proceso completo — 6 pasos</span>
            <div class="bv-rule-line bv-rule-line-c"></div>
        </div>

        <div class="bv-process-grid" id="{{ $uid }}_steps">
            @foreach ([
                ['n'=>'1','sc'=>'#60a5fa','ib'=>'rgba(96,165,250,.12)','ibc'=>'rgba(96,165,250,.25)','tag'=>'Paso 1','title'=>'Datos del trabajador','body'=>'Identifique al trabajador y su cargo. El empleado tendrá 45 minutos en la audiencia y sus respuestas quedarán registradas.'],
                ['n'=>'2','sc'=>'#c9a84c','ib'=>'rgba(201,168,76,.12)','ibc'=>'rgba(201,168,76,.25)','tag'=>'Paso 2','title'=>'Hechos reportados','body'=>'¿Quién reporta el incidente? Describa lo ocurrido — la IA verifica que no falte fecha, lugar ni acción concreta.'],
                ['n'=>'3','sc'=>'#34d399','ib'=>'rgba(52,211,153,.12)','ibc'=>'rgba(52,211,153,.25)','tag'=>'Paso 3','title'=>'Cuándo y dónde','body'=>'Confirme la fecha, hora aproximada, lugar del hecho y si ocurrió dentro del horario laboral.'],
                ['n'=>'4','sc'=>'#a78bfa','ib'=>'rgba(167,139,250,.12)','ibc'=>'rgba(167,139,250,.25)','tag'=>'Paso 4','title'=>'Evidencias','body'=>'¿Existe evidencia? Correos, registros de asistencia, cámaras, documentos, testigos... suba los archivos disponibles.'],
                ['n'=>'5','sc'=>'#fb923c','ib'=>'rgba(251,146,60,.12)','ibc'=>'rgba(251,146,60,.25)','tag'=>'Paso 5','title'=>'Testigos','body'=>'¿Hubo personas que presenciaron el hecho? Registre el nombre y cargo de cada testigo.'],
                ['n'=>'6','sc'=>'#f472b6','ib'=>'rgba(244,114,182,.12)','ibc'=>'rgba(244,114,182,.25)','tag'=>'Paso 6','title'=>'Revisión y envío','body'=>'Revise el resumen completo, previsualice la citación y confirme el envío de los descargos.'],
            ] as $p)
                <div class="bv-step-item"
                    style="--sc:{{ $p['sc'] }};--ib:{{ $p['ib'] }};--ibc:{{ $p['ibc'] }}">
                    <div class="bv-step-num">{{ $p['n'] }}</div>
                    <div style="min-width:0;">
                        <p class="bv-step-tag" style="--sc:{{ $p['sc'] }}">{{ $p['tag'] }}</p>
                        <p class="t-ct">{{ $p['title'] }}</p>
                        <p class="t-cb">{{ $p['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="bv-next-hint bv-a3">
            <svg style="width:16px;height:16px;color:#c9a84c;flex-shrink:0" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Al hacer clic en <strong>Siguiente</strong>, comenzará el <strong>Paso 1</strong>. El proceso completo toma aproximadamente 10 minutos.
        </div>
    </div>

</div>

<script>
    (function() {
        var UID = '{{ $uid }}';
        var hero = document.getElementById(UID + '_hero');
        var canvas = document.getElementById(UID + '_canvas');
        if (!canvas || !hero) return;

        /* ── Canvas setup ───────────────────────────── */
        var ctx = canvas.getContext('2d'),
            raf, pts = [], mouse = { x: -9999, y: -9999 }, tick = 0;

        function resize() {
            canvas.width  = hero.offsetWidth;
            canvas.height = hero.offsetHeight;
        }
        resize();
        window.addEventListener('resize', function() { resize(); pts = []; init(); });

        hero.addEventListener('mousemove', function(e) {
            var r = hero.getBoundingClientRect();
            mouse.x = e.clientX - r.left;
            mouse.y = e.clientY - r.top;
        });
        hero.addEventListener('mouseleave', function() { mouse.x = -9999; mouse.y = -9999; });

        function init() {
            /* ── Particle network ── */
            pts = [];
            for (var i = 0; i < 42; i++) {
                var spd = Math.random() * .45 + .2;
                var ang = Math.random() * Math.PI * 2;
                pts.push({
                    x:     Math.random() * canvas.width,
                    y:     Math.random() * canvas.height,
                    vx:    Math.cos(ang) * spd,
                    vy:    Math.sin(ang) * spd,
                    r:     Math.random() * 1.4 + .4,
                    phase: Math.random() * Math.PI * 2
                });
            }
        }
        init();

        function isDark() { return document.documentElement.classList.contains('dark'); }

        function draw() {
            tick += .016;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            var dark = isDark();

            /* ── Particle network ── */
            var dc = dark ? 'rgba(201,168,76,' : 'rgba(200,90,15,';
            pts.forEach(function(p) {
                /* mouse attraction */
                var mx = mouse.x - p.x, my = mouse.y - p.y;
                var md = Math.sqrt(mx * mx + my * my);
                if (md < 160 && md > 1) { p.vx += (mx / md) * .016; p.vy += (my / md) * .016; }
                var sp = Math.sqrt(p.vx * p.vx + p.vy * p.vy);
                if (sp > 1.2) { p.vx *= .93; p.vy *= .93; }
                if (sp < .15) { p.vx *= 1.07; p.vy *= 1.07; }

                p.x += p.vx; p.y += p.vy;
                if (p.x < 0 || p.x > canvas.width)  p.vx *= -1;
                if (p.y < 0 || p.y > canvas.height)  p.vy *= -1;

                var pa = .45 + .3 * Math.sin(tick * 1.2 + p.phase);
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = dc + pa + ')';
                ctx.fill();
            });

            /* connections */
            for (var a = 0; a < pts.length; a++)
                for (var b = a + 1; b < pts.length; b++) {
                    var dx = pts[a].x - pts[b].x, dy = pts[a].y - pts[b].y;
                    var d  = Math.sqrt(dx * dx + dy * dy);
                    if (d < 110) {
                        ctx.beginPath();
                        ctx.moveTo(pts[a].x, pts[a].y);
                        ctx.lineTo(pts[b].x, pts[b].y);
                        ctx.strokeStyle = dc + (.3 * (1 - d / 110)) + ')';
                        ctx.lineWidth   = .55;
                        ctx.stroke();
                    }
                }

            raf = requestAnimationFrame(draw);
        }
        draw();

        if ('IntersectionObserver' in window)
            new IntersectionObserver(function(e) {
                if (!e[0].isIntersecting) cancelAnimationFrame(raf);
                else draw();
            }).observe(hero);

        /* ── 3D Tilt — desktop (hover capable) only ── */
        var hasHover = window.matchMedia('(hover:hover) and (pointer:fine)').matches;
        if (!hasHover) return;

        document.querySelectorAll('#' + UID + '_steps .bv-step-item').forEach(function(el) {
            el.addEventListener('mousemove', function(e) {
                var r  = el.getBoundingClientRect();
                var rx = ((e.clientY - r.top  - r.height / 2) / (r.height / 2)) * 8;
                var ry = ((r.width  / 2 - (e.clientX - r.left)) / (r.width / 2)) * 8;
                var mx = ((e.clientX - r.left) / r.width  * 100).toFixed(1) + '%';
                var my = ((e.clientY - r.top)  / r.height * 100).toFixed(1) + '%';
                el.style.setProperty('--mx', mx);
                el.style.setProperty('--my', my);
                el.style.transition = 'transform .08s ease, box-shadow .08s ease';
                el.style.transform  = 'perspective(800px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg) scale(1.035)';
                el.style.boxShadow  = '0 22px 50px rgba(0,0,0,.32), 0 0 0 1px rgba(255,255,255,.07)';
            });
            el.addEventListener('mouseleave', function() {
                el.style.transition = 'transform .5s cubic-bezier(.16,1,.3,1), box-shadow .5s ease';
                el.style.transform  = 'perspective(800px) rotateX(0deg) rotateY(0deg) scale(1)';
                el.style.boxShadow  = '';
            });
        });
    })();
</script>
