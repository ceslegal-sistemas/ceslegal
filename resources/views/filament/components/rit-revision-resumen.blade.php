@php
    $fmtHora = function ($t) {
        if (!$t) {
            return null;
        }
        foreach (['H:i:s', 'H:i', 'G:i:s', 'G:i'] as $fmt) {
            try {
                return \Carbon\Carbon::createFromFormat($fmt, $t)->format('g:i A');
            } catch (\Throwable $e) {
            }
        }
        return $t;
    };
    $formaPagoLabels = [
        'transferencia' => 'Transferencia bancaria',
        'cheque' => 'Cheque',
        'efectivo' => 'Efectivo',
        'mixto' => 'Mixto',
    ];
    $periodicidadLabels = [
        'mensual' => 'Mensual',
        'quincenal' => 'Quincenal',
        'semanal' => 'Semanal',
        'diario' => 'Diario / jornaleros',
        'destajo' => 'Por obra / destajo',
    ];
    $periodicidadTexto = is_array($periodicidad_pago ?? null)
        ? implode(' · ', array_filter(array_map(fn($p) => $periodicidadLabels[$p] ?? $p, $periodicidad_pago)))
        : $periodicidadLabels[$periodicidad_pago ?? ''] ?? ($periodicidad_pago ?: '-');
    $controlLabels = [
        'biometrico' => 'Reloj biométrico',
        'planilla' => 'Planilla manual',
        'app' => 'App móvil / digital',
        'supervision_rondas' => 'Supervisión directa',
        'sin_control' => 'Sin sistema formal',
    ];
    $sgSstLabels = [
        'si' => 'Sí, implementado',
        'en_proceso' => 'En proceso',
        'no' => 'No',
    ];
    $jornadaSabadoLabel = match ($jornada_sabado ?? 'no') {
        'media_jornada' => 'Media jornada',
        'dia_completo' => 'Jornada completa',
        default => 'No',
    };
    $dominicalesLabels = [
        'no' => 'No',
        'ocasionalmente' => 'Ocasionalmente',
        'regularmente' => 'Regularmente',
    ];
    $riesgosLabels = [
        'ergonomico' => 'Ergonómico',
        'psicosocial' => 'Psicosocial',
        'mecanico' => 'Mecánico',
        'electrico' => 'Eléctrico',
        'publico' => 'Público',
        'alturas' => 'Alturas',
        'quimico' => 'Químico',
        'vial' => 'Vial',
        'fisico' => 'Físico',
        'biologico' => 'Biológico',
        'locativo' => 'Locativo',
        'otro' => 'Otro',
    ];
    $sancionesLabels = [
        'llamado_verbal' => 'Llamado verbal',
        'llamado_escrito' => 'Llamado escrito',
        'suspension_1_8' => 'Suspensión 1-8 días',
        'suspension_1_15' => 'Suspensión 1-15 días',
        'suspension_1_30' => 'Suspensión 1-30 días',
        'suspension_1_40' => 'Suspensión 1-40 días',
        'suspension_1_60' => 'Suspensión 1-60 días',
        'terminacion' => 'Terminación justa causa',
    ];
    $tiposContratoLabels = [
        'indefinido' => 'Término indefinido',
        'fijo' => 'Término fijo',
        'obra' => 'Obra o labor',
        'aprendizaje' => 'Aprendizaje SENA',
    ];
    $totalCargos = count($cargos ?? []);

    $modalidadesJornadaLabels = [
        'jornada_fija_diurna' => 'Jornada fija diurna',
        'turnos_rotativos' => 'Turnos rotativos',
        'turno_nocturno_regular' => 'Turno nocturno fijo',
        'operacion_continua_247' => 'Operación 24/7',
        'jornada_flexible' => 'Horario flexible',
        'teletrabajo' => 'Teletrabajo',
        'trabajo_campo_obra' => 'Trabajo en campo/obra',
        'vigilancia_guardias' => 'Vigilancia / guardias',
        'personalizado' => 'Personalizado',
    ];
    $capitulosRIT = [
        'I' => 'Denominación y Objeto',
        'II' => 'Admisión y Período de Prueba',
        'III' => 'Jornada Ordinaria',
        'IV' => 'Trabajo Suplementario y Festivos',
        'V' => 'Remuneración y Forma de Pago',
        'VI' => 'Vacaciones y Permisos',
        'VII' => 'Licencias Especiales',
        'VIII' => 'Régimen Disciplinario',
        'IX' => 'Escala de Sanciones',
        'X' => 'Reclamos y Procedimientos',
        'XI' => 'Conducta y Comportamiento',
        'XII' => 'Seguridad y Salud (SG-SST)',
        'XIII' => 'Equipos y Bienes',
        'XIV' => 'Comité de Convivencia / Acoso',
        'XV' => 'Protección de Sujetos Especiales',
        'XVI' => 'Disposiciones Finales',
    ];

    /* Embers (modo claro) - chispas de fuego que suben desde la base */
    $emberColors = ['200,60,5', '230,90,10', '255,130,20', '180,45,0', '240,110,15', '210,70,5'];
    $embers = [];
    for ($i = 0; $i < 24; $i++) {
        $c  = $emberColors[array_rand($emberColors)];
        $sz = round(mt_rand(15, 45) / 10, 1);
        $embers[] = [
            'x' => mt_rand(2, 98),
            'sz' => $sz,
            'c' => $c,
            'g' => (int) (($sz * mt_rand(25, 50)) / 10),
            'dur' => round(mt_rand(35, 75) / 10, 1),
            'del' => round(mt_rand(0, 90) / 10, 1),
            'drift' => mt_rand(-40, 40),
        ];
    }

    /* Luciérnagas (modo oscuro) - generadas en servidor para layout estable */
    $ffColors = ['201,168,76', '255,235,120', '255,255,200', '190,215,255', '245,195,255', '255,210,90'];
    $fireflies = [];
    for ($i = 0; $i < 24; $i++) {
        $c  = $ffColors[array_rand($ffColors)];
        $sz = round(mt_rand(20, 60) / 10, 1);
        $g  = (int) (($sz * mt_rand(35, 65)) / 10);
        $fireflies[] = [
            'x' => mt_rand(2, 97),
            'y' => mt_rand(4, 96),
            'sz' => $sz,
            'g' => $g,
            'c' => $c,
            'tw' => round(mt_rand(18, 50) / 10, 1),
            'del' => round(mt_rand(0, 60) / 10, 1),
            'dr' => round(mt_rand(70, 160) / 10, 1),
        ];
    }
@endphp

@verbatim
<style>
    @keyframes rg-up   { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
    @keyframes rg-pop  { from{opacity:0;transform:scale(.6)}       to{opacity:1;transform:none} }
    @keyframes rg-fa   { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-16px,12px)} }
    @keyframes rg-fb   { 0%,100%{transform:translate(0,0)} 50%{transform:translate(14px,-14px)} }

    /* Tipografía: Space Grotesk para display, Inter (heredado del panel) para cuerpo */
    .rg-wrap{ position:relative; max-width:1080px; margin:0 auto; display:flex; flex-direction:column; gap:18px; padding:.25rem 0; }
    .rg-disp{ font-family:'Space Grotesk', ui-sans-serif, system-ui, sans-serif; letter-spacing:-.02em; }

    /* Grilla de tarjetas (web): 3 columnas que se adaptan */
    .rg-grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; }
    .rg-col-full{ grid-column:1/-1; }

    /* ── Tarjeta sólida con levitación (claro/oscuro, igual que Auditar RIT) ── */
    .rg-glass{
        position:relative; z-index:1;
        background:#fff;
        border:1px solid rgba(0,0,0,.07);
        border-radius:24px;
        box-shadow:0 6px 26px rgba(28,25,23,.08);
        transition:box-shadow .2s ease, transform .2s ease;
    }
    html.dark .rg-glass{
        background:rgba(255,255,255,.03);
        border-color:rgba(255,255,255,.08);
        box-shadow:0 10px 30px rgba(0,0,0,.28);
    }
    .rg-card:hover{ box-shadow:0 14px 36px rgba(28,25,23,.12); transform:translateY(-2px) }
    html.dark .rg-card:hover{ box-shadow:0 16px 38px rgba(0,0,0,.36) }

    /* ── Hero ── */
    .rg-hero{ padding:30px 24px; text-align:center; overflow:hidden; }
    html.dark .rg-hero{ background:linear-gradient(150deg,#1a0f0c 0%,#241319 55%,#170d0a 100%); border-color:rgba(255,255,255,.06); }
    .rg-ring{ width:64px;height:64px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;
        background:rgba(201,168,76,.12); border:1.5px solid rgba(201,168,76,.35); margin-bottom:12px; animation:rg-pop .6s .05s cubic-bezier(.34,1.56,.64,1) both; }
    .rg-ring lord-icon{ width:50px;height:50px;flex-shrink:0 }
    .rg-kicker{ font-size:.66rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:#be123c;margin:0 0 6px }
    html.dark .rg-kicker{ color:#fb7185 }
    .rg-title{ font-size:1.35rem;font-weight:700;line-height:1.2;margin:0 0 8px;color:#1c1917 }
    html.dark .rg-title{ color:#f5f5f4 }
    .rg-lead{ font-size:.85rem;line-height:1.55;color:#78716c;margin:0 auto;max-width:36ch }
    html.dark .rg-lead{ color:#a8a29e }
    .rg-em{ color:#be123c;font-weight:600 }
    html.dark .rg-em{ color:#fda4af }

    .rg-stats{ display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:16px }
    .rg-stat{ background:rgba(0,0,0,.025);border:1px solid rgba(0,0,0,.06);border-radius:14px;padding:10px 4px;text-align:center }
    html.dark .rg-stat{ background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.08) }
    .rg-stat b{ display:block;font-family:'Space Grotesk';font-size:1.25rem;font-weight:700;line-height:1;color:#1c1917 }
    html.dark .rg-stat b{ color:#f5f5f4 }
    .rg-stat span{ font-size:.55rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#a8a29e;margin-top:4px;display:block }

    .rg-notes{ display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:18px;text-align:left }
    @media(max-width:720px){ .rg-notes{ grid-template-columns:1fr } }
    .rg-note{ display:flex;gap:10px;align-items:flex-start;background:rgba(0,0,0,.025);border:1px solid rgba(0,0,0,.06);
        border-radius:13px;padding:10px 12px;font-size:.78rem;line-height:1.45;color:#57534e }
    html.dark .rg-note{ background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.07);color:#d6d3d1 }
    .rg-note b{ color:#1c1917 } html.dark .rg-note b{ color:#f5f5f4 }
    .rg-note .ic{ width:18px;height:18px;flex-shrink:0;margin-top:1px }

    /* Section label divider */
    .rg-seclabel{ font-size:.66rem;font-weight:700;letter-spacing:.13em;text-transform:uppercase;color:#a8a29e;
        margin:8px 4px 0;display:flex;align-items:center;gap:10px }
    .rg-seclabel::after{ content:"";flex:1;height:1px;background:rgba(28,25,23,.1) }
    html.dark .rg-seclabel::after{ background:rgba(255,255,255,.1) }

    /* Data card */
    .rg-card{ padding:20px 22px;overflow:hidden }
    .rg-lbl{ font-size:.64rem;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#be123c;margin:0 0 7px }
    html.dark .rg-lbl{ color:#fb7185 }
    .rg-val{ font-size:1.05rem;font-weight:600;margin:0;line-height:1.35;color:#1c1917 }
    html.dark .rg-val{ color:#f5f5f4 }
    .rg-val.empty{ color:#a8a29e;font-weight:400;font-style:italic;font-size:.9rem }
    .rg-sub{ font-size:.8rem;color:#78716c;margin:4px 0 0;line-height:1.45 }
    html.dark .rg-sub{ color:#a8a29e }
    .rg-grid2{ display:grid;grid-template-columns:1fr 1fr;gap:14px }
    @media(max-width:460px){ .rg-grid2{ grid-template-columns:1fr } }

    /* Pills */
    .rg-pills{ display:flex;flex-wrap:wrap;gap:6px;margin-top:8px }
    .rg-pill{ font-size:.72rem;font-weight:600;border-radius:999px;padding:.24rem .7rem;background:rgba(225,29,72,.1);color:#be123c }
    html.dark .rg-pill{ background:rgba(251,113,133,.16);color:#fda4af }
    .rg-pill.amber{ background:rgba(234,179,8,.15);color:#854d0e } html.dark .rg-pill.amber{ background:rgba(250,204,21,.13);color:#fde047 }
    .rg-pill.orange{ background:rgba(234,88,12,.13);color:#9a3412 } html.dark .rg-pill.orange{ background:rgba(249,115,22,.15);color:#fdba74 }
    .rg-pill.red{ background:rgba(220,38,38,.12);color:#991b1b } html.dark .rg-pill.red{ background:rgba(239,68,68,.15);color:#fca5a5 }
    .rg-pill-h{ font-size:.7rem;font-weight:700;letter-spacing:.04em;margin:.5rem 0 .1rem }

    /* Capítulos disclosure */
    details.rg-caps{ padding:0;overflow:hidden }
    details.rg-caps>summary{ list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;
        padding:15px 18px;font-weight:600;color:#1c1917 }
    html.dark details.rg-caps>summary{ color:#f5f5f4 }
    details.rg-caps>summary::-webkit-details-marker{ display:none }
    .rg-caps-count{ font-size:.7rem;font-weight:700;color:#be123c;background:rgba(225,29,72,.1);border-radius:999px;padding:.16rem .6rem;margin-left:.4rem }
    html.dark .rg-caps-count{ color:#fda4af;background:rgba(251,113,133,.16) }
    .rg-chev{ transition:transform .25s;color:#a8a29e;flex-shrink:0 }
    details.rg-caps[open] .rg-chev{ transform:rotate(180deg) }
    .rg-caps-body{ padding:0 14px 12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:9px }
    .rg-cap{ display:flex;align-items:center;gap:9px;font-size:.75rem;color:#44403c;
        background:rgba(0,0,0,.025);border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:9px 11px }
    html.dark .rg-cap{ color:#d6d3d1;background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.07) }
    .rg-cap svg{ width:14px;height:14px;color:#e11d48;flex-shrink:0 }
    .rg-cap b{ font-weight:700 }
    .rg-foot{ font-size:.72rem;color:#a8a29e;text-align:center;line-height:1.5;padding:2px 18px 14px }

    .rg-a1{ animation:rg-up .5s cubic-bezier(.16,1,.3,1) both }
    .rg-a2{ animation:rg-up .5s .08s cubic-bezier(.16,1,.3,1) both }
    .rg-a3{ animation:rg-up .5s .16s cubic-bezier(.16,1,.3,1) both }

    /* ── Fuego (claro) / luciérnagas (oscuro) del hero - igual que el inicio ── */
    .rg-fx{ position:absolute; inset:0; pointer-events:none; overflow:hidden; z-index:0 }
    .rg-hero-content{ position:relative; z-index:2 }

    @keyframes rg-ember-rise{
        0%{ transform:translateY(0) translateX(0) scale(1); opacity:0 }
        8%{ opacity:.92 }
        80%{ opacity:.45 }
        100%{ transform:translateY(-300px) translateX(var(--drift,20px)) scale(.2); opacity:0 }
    }
    .rg-ember{ position:absolute; bottom:-4px; border-radius:50%; pointer-events:none; will-change:transform,opacity;
        animation:rg-ember-rise var(--dur,5s) var(--del,0s) ease-in infinite }
    html.dark .rg-ember{ display:none }

    @keyframes rg-twinkle{ 0%,100%{ opacity:.04; transform:scale(.3) } 45%,55%{ opacity:1; transform:scale(1.3) } }
    @keyframes rg-ffdrift{ 0%,100%{ transform:translate(0,0) } 30%{ transform:translate(10px,-16px) } 65%{ transform:translate(-8px,-10px) } }
    .rg-firefly{ position:absolute; border-radius:50%; pointer-events:none; will-change:transform,opacity;
        animation:rg-twinkle var(--tw) var(--del) ease-in-out infinite, rg-ffdrift var(--dr) var(--del) ease-in-out infinite }
    html:not(.dark) .rg-firefly{ opacity:0 !important }

    /* Glow de fuego en la base (solo claro) */
    .rg-fire-base{ display:none; position:absolute; inset:0; pointer-events:none; z-index:0 }
    html:not(.dark) .rg-fire-base{ display:block;
        background:radial-gradient(ellipse 85% 55% at 50% 100%, rgba(255,110,20,.22) 0%, rgba(255,160,40,.10) 50%, transparent 100%) }

    /* Orbes flotantes suaves */
    @keyframes rg-orb-a{ 0%,100%{ transform:translate(0,0) scale(1) } 40%{ transform:translate(-18px,16px) scale(1.08) } }
    @keyframes rg-orb-b{ 0%,100%{ transform:translate(0,0) scale(1) } 45%{ transform:translate(16px,-18px) scale(1.1) } }
    .rg-orb{ position:absolute; border-radius:50%; filter:blur(28px); pointer-events:none }
    .rg-orb-a{ width:240px;height:240px;top:-60px;right:-40px;
        background:radial-gradient(circle,rgba(225,29,72,.45),transparent 70%); animation:rg-orb-a 12s ease-in-out infinite }
    .rg-orb-b{ width:190px;height:190px;bottom:-50px;left:-40px;
        background:radial-gradient(circle,rgba(201,168,76,.24),transparent 70%); animation:rg-orb-b 15s ease-in-out infinite }
    html:not(.dark) .rg-orb-a{ background:radial-gradient(circle,rgba(220,80,10,.26),transparent 70%) }
    html:not(.dark) .rg-orb-b{ background:radial-gradient(circle,rgba(201,140,20,.30),transparent 70%) }

    @media(prefers-reduced-motion:reduce){ .rg-a1,.rg-a2,.rg-a3,.rg-ember,.rg-firefly,.rg-orb-a,.rg-orb-b{ animation:none } }
</style>
@endverbatim

<div class="rg-wrap">

    {{-- ══ HERO ══ --}}
    <div class="rg-glass rg-hero rg-a1">

        {{-- Capa de efecto: orbes + embers (claro) + luciérnagas (oscuro) --}}
        <div class="rg-fx">
            <div class="rg-orb rg-orb-a"></div>
            <div class="rg-orb rg-orb-b"></div>
            @foreach ($embers as $e)
                <div class="rg-ember" style="left:{{ $e['x'] }}%;width:{{ $e['sz'] }}px;height:{{ $e['sz'] }}px;background:rgb({{ $e['c'] }});box-shadow:0 0 {{ $e['g'] }}px {{ $e['g'] }}px rgba({{ $e['c'] }},.6),0 0 {{ $e['g'] * 2 }}px {{ $e['g'] * 3 }}px rgba(255,160,30,.22);--drift:{{ $e['drift'] }}px;--dur:{{ $e['dur'] }}s;--del:{{ $e['del'] }}s;"></div>
            @endforeach
            @foreach ($fireflies as $f)
                <div class="rg-firefly" style="left:{{ $f['x'] }}%;top:{{ $f['y'] }}%;width:{{ $f['sz'] }}px;height:{{ $f['sz'] }}px;background:rgb({{ $f['c'] }});box-shadow:0 0 {{ $f['g'] }}px {{ $f['g'] * 2 }}px rgba({{ $f['c'] }},.55),0 0 {{ $f['g'] * 4 }}px {{ $f['g'] * 3 }}px rgba({{ $f['c'] }},.18);--tw:{{ $f['tw'] }}s;--del:{{ $f['del'] }}s;--dr:{{ $f['dr'] }}s;"></div>
            @endforeach
        </div>
        <div class="rg-fire-base"></div>

      <div class="rg-hero-content">
        <div class="rg-ring">
            <lord-icon src="https://cdn.lordicon.com/xjsqfzte.json" trigger="loop" delay="500" stroke="bold"
                colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"></lord-icon>
        </div>
        <p class="rg-kicker">Resumen de su información</p>
        <h2 class="rg-title rg-disp">Reglamento Interno de Trabajo</h2>
        <p class="rg-lead">
            Verifique que todo sea correcto. Use <span class="rg-em">Anterior</span> para cambiar algo;
            al pulsar <span class="rg-em">Crear</span>, la IA redactará los 16 capítulos conforme al Art. 105 CST.
        </p>

        <div class="rg-stats">
            <div class="rg-stat"><b>{{ $num_trabajadores ?: '?' }}</b><span>Trabaj.</span></div>
            <div class="rg-stat"><b>{{ $totalCargos ?: '-' }}</b><span>Cargos</span></div>
            <div class="rg-stat"><b>16</b><span>Capít.</span></div>
            <div class="rg-stat"><b>~80</b><span>Artíc.</span></div>
        </div>

        <div class="rg-notes">
            <div class="rg-note">
                <svg class="ic" fill="none" viewBox="0 0 24 24" stroke="#16a34a" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>Verifique que el resumen sea <b>correcto y completo</b> antes de continuar.</span>
            </div>
            <div class="rg-note">
                <svg class="ic" fill="none" viewBox="0 0 24 24" stroke="#d97706" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                <span>Puede tardar hasta <b>60 segundos</b> - no cierre ni recargue la ventana.</span>
            </div>
            <div class="rg-note">
                <svg class="ic" fill="none" viewBox="0 0 24 24" stroke="#ea580c" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                <span>Revíselo con un <b>abogado</b> antes de presentarlo al Ministerio del Trabajo.</span>
            </div>
        </div>
      </div>{{-- /.rg-hero-content --}}
    </div>

    <div class="rg-seclabel rg-a2">Resumen de sus respuestas</div>

    {{-- Tarjetas (grilla web, se adaptan a 3/2/1 columnas) --}}
    <div class="rg-grid rg-a2">

        <div class="rg-glass rg-card">
            <p class="rg-lbl">Empresa</p>
            <p class="rg-val">{{ $empresa?->razon_social ?? '-' }}</p>
            <p class="rg-sub">NIT {{ $empresa?->nit ?? '-' }}@if ($empresa?->ciudad) &nbsp;·&nbsp; {{ $empresa->ciudad }}@if ($empresa->departamento), {{ $empresa->departamento }}@endif @endif</p>
            @if (!empty($actividad_economica))<p class="rg-sub" style="margin-top:.3rem"><span style="opacity:.75">Actividad:</span> {{ $actividad_economica }}</p>@endif
            @if (($tiene_sucursales ?? null) === 'si')<p class="rg-sub" style="margin-top:.3rem">{{ count($sucursales ?? []) }} sucursal(es) registrada(s)</p>@endif
        </div>

        <div class="rg-glass rg-card">
            <p class="rg-lbl">Jornada</p>
            @php
                $labelsDia = \App\Support\DiasHabiles::LABELS;
                $jp = is_array($jornada_personalizada ?? null) ? $jornada_personalizada : [];
                $diasIso = [];
                foreach ($jp as $t) {
                    foreach ((array) ($t['dias'] ?? []) as $d) {
                        if (is_numeric($d)) { $diasIso[(int) $d] = true; }
                    }
                }
                ksort($diasIso);
                $diasTexto = collect(array_keys($diasIso))->map(fn($d) => $labelsDia[$d] ?? '')->filter()->join(', ');
            @endphp
            <p class="rg-val {{ empty($jp) ? 'empty' : '' }}">{{ $diasTexto ?: 'Sin turnos definidos aún' }}</p>
            @foreach ($jp as $t)
                @php
                    $dt = collect((array) ($t['dias'] ?? []))->map(fn($d) => $labelsDia[(int) $d] ?? '')->filter()->join(', ');
                @endphp
                <p class="rg-sub"><b>{{ $t['nombre'] ?: 'Turno' }}:</b> {{ $dt ?: '-' }} · {{ $fmtHora($t['hora_inicio'] ?? null) ?? '?' }} a {{ $fmtHora($t['hora_fin'] ?? null) ?? '?' }}{{ !empty($t['cargos']) ? ' ('.$t['cargos'].')' : '' }}</p>
            @endforeach
            @if (!empty($modalidades_jornada))<div class="rg-pills">@foreach ($modalidades_jornada as $mj)<span class="rg-pill">{{ $modalidadesJornadaLabels[$mj] ?? $mj }}</span>@endforeach</div>@endif
        </div>

        <div class="rg-glass rg-card">
            <p class="rg-lbl">Pago</p>
            <p class="rg-val {{ !$forma_pago ? 'empty' : '' }}">{{ $formaPagoLabels[$forma_pago ?? ''] ?? ($forma_pago ?: '-') }}</p>
            <p class="rg-sub">{{ $periodicidadTexto }}</p>
        </div>

        <div class="rg-glass rg-card">
            <p class="rg-lbl">Tipos de contrato</p>
            @if (is_array($tipos_contrato) && !empty($tipos_contrato))<div class="rg-pills">@foreach ($tipos_contrato as $tc)<span class="rg-pill">{{ $tiposContratoLabels[$tc] ?? $tc }}</span>@endforeach</div>@else<p class="rg-val empty">No indicado</p>@endif
        </div>

        <div class="rg-glass rg-card">
            <p class="rg-lbl">Control asistencia</p>
            <p class="rg-val {{ !$control_asistencia ? 'empty' : '' }}">{{ $controlLabels[$control_asistencia ?? ''] ?? ($control_asistencia ?: '-') }}</p>
        </div>

        <div class="rg-glass rg-card">
            <p class="rg-lbl">SG-SST</p>
            <p class="rg-val {{ !$tiene_sg_sst ? 'empty' : '' }}">{{ $sgSstLabels[$tiene_sg_sst ?? ''] ?? ($tiene_sg_sst ?: '-') }}</p>
        </div>

        <div class="rg-glass rg-card">
            <p class="rg-lbl">Riesgos principales</p>
            @if (!empty($riesgos_principales))<div class="rg-pills">@foreach ($riesgos_principales as $r)<span class="rg-pill">{{ $riesgosLabels[$r] ?? $r }}</span>@endforeach</div>@else<p class="rg-val empty">No indicados</p>@endif
        </div>

        {{-- Régimen disciplinario (ancho completo) --}}
        <div class="rg-glass rg-card rg-col-full">
            <p class="rg-lbl">Régimen disciplinario</p>
            @if (!empty($faltas_leves))<p class="rg-pill-h" style="color:#854d0e">Leves</p><div class="rg-pills">@foreach ($faltas_leves as $f)<span class="rg-pill amber">{{ $f }}</span>@endforeach</div>@endif
            @if (!empty($faltas_graves))<p class="rg-pill-h" style="color:#9a3412">Graves</p><div class="rg-pills">@foreach ($faltas_graves as $f)<span class="rg-pill orange">{{ $f }}</span>@endforeach</div>@endif
            @if (empty($faltas_leves) && empty($faltas_graves))<p class="rg-val empty">Sin faltas registradas</p>@endif
            @if (!empty($sanciones))<p class="rg-pill-h">Sanciones habilitadas</p><div class="rg-pills">@foreach ($sanciones as $s)<span class="rg-pill">{{ $sancionesLabels[$s] ?? $s }}</span>@endforeach</div>@endif
        </div>

    </div>

    {{-- Capítulos (colapsable) --}}
    <details class="rg-glass rg-caps rg-a3">
        <summary>
            <span class="rg-disp">Capítulos que generará la IA <span class="rg-caps-count">16</span></span>
            <svg class="rg-chev" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
        </summary>
        <div class="rg-caps-body">
            @foreach ($capitulosRIT as $num => $titulo)
                <div class="rg-cap">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    <span><b>{{ $num }}</b>&nbsp;· {{ $titulo }}</span>
                </div>
            @endforeach
        </div>
        <p class="rg-foot">Todos obligatorios según el Art. 105 CST. La IA los redacta artículo por artículo con la información que usted proporcionó.</p>
    </details>

</div>
