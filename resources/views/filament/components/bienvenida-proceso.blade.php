@php
    $uid    = 'bv_' . substr(md5(uniqid()), 0, 8);
    $nombre = auth()->user()?->name ?? 'usuario';
@endphp

@verbatim
<style>
  /* ── Keyframes ───────────────────────────────── */
  @keyframes bv-up  { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
  @keyframes bv-pop { from{opacity:0;transform:scale(.55)}        to{opacity:1;transform:scale(1)} }
  @keyframes bv-glow{ 0%,100%{box-shadow:0 0 0 0 rgba(201,168,76,.5)} 65%{box-shadow:0 0 0 14px rgba(201,168,76,0)} }

  .bv-a1{animation:bv-up .6s cubic-bezier(.16,1,.3,1) both}
  .bv-a2{animation:bv-up .6s .12s cubic-bezier(.16,1,.3,1) both}
  .bv-a3{animation:bv-up .6s .24s cubic-bezier(.16,1,.3,1) both}
  .bv-icon-ring{animation:bv-pop .7s .08s cubic-bezier(.34,1.56,.64,1) both, bv-glow 3s 1.2s ease-in-out infinite}

  /* ── Hero — mobile first ─────────────────────── */
  .bv-hero{
    position:relative; overflow:hidden; border-radius:1.125rem;
    padding:2rem 1.25rem 1.75rem; text-align:center;
    background:linear-gradient(155deg,#060f22 0%,#091830 50%,#060e20 100%);
  }
  @media(min-width:540px){
    .bv-hero{ border-radius:1.375rem; padding:2.75rem 2.25rem 2.5rem; }
  }
  html:not(.dark) .bv-hero{
    background:linear-gradient(155deg,#f0f4ff 0%,#f8faff 45%,#eef2f9 100%);
    border:1px solid rgba(30,58,138,.08);
    box-shadow:0 4px 24px rgba(30,58,138,.07);
  }
  html:not(.dark) .bv-hero-orb-blue{
    background:radial-gradient(circle,rgba(30,58,138,.15),transparent 70%) !important;
  }
  html:not(.dark) .bv-hero-orb-gold{
    background:radial-gradient(circle,rgba(201,168,76,.1),transparent 70%) !important;
  }

  /* ── Hero title — mobile first ───────────────── */
  .bv-title{
    font-size:1.25rem; font-weight:700; letter-spacing:-.02em;
    line-height:1.25; margin:0 0 .75rem;
  }
  @media(min-width:540px){ .bv-title{ font-size:1.625rem; } }

  /* ── Subtitle max-width — only on larger screens */
  .bv-subtitle-wrap{ max-width:none; }
  @media(min-width:540px){ .bv-subtitle-wrap{ max-width:420px; margin-left:auto; margin-right:auto; } }

  /* ── Divider ─────────────────────────────────── */
  .bv-rule{ display:flex; align-items:center; gap:.75rem; margin-bottom:.875rem; }
  .bv-rule-line{ flex:1; height:1px; }
  .bv-rule-label{ font-size:.625rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; white-space:nowrap; }

  /* ── Cards grid — mobile first: 1 col → 3 col ── */
  .bv-cards-grid{
    display:grid;
    grid-template-columns:1fr;
    gap:.75rem;
  }
  @media(min-width:480px){ .bv-cards-grid{ grid-template-columns:repeat(3,1fr); } }

  /* ── Card ────────────────────────────────────── */
  .bv-card{
    border-radius:1rem; padding:1.25rem 1.125rem;
    cursor:default; transform-style:preserve-3d; will-change:transform;
    transition:transform .35s ease, box-shadow .35s ease;
    position:relative; overflow:hidden;
    background:rgba(255,255,255,.055);
    border:1px solid rgba(255,255,255,.1);
    backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px);
  }
  /* Hover radial shine */
  .bv-card::after{
    content:''; position:absolute; inset:0; border-radius:inherit;
    background:radial-gradient(circle at var(--mx,50%) var(--my,50%),rgba(255,255,255,.07),transparent 60%);
    opacity:0; transition:opacity .22s; pointer-events:none;
  }
  .bv-card:hover::after{ opacity:1; }
  html:not(.dark) .bv-card{
    background:rgba(255,255,255,.82); border-color:rgba(0,0,0,.08);
    box-shadow:0 2px 12px rgba(0,0,0,.07);
  }
  /* On mobile, card content layout is horizontal (icon left, text right) */
  .bv-card-inner{ display:flex; align-items:flex-start; gap:.875rem; }
  .bv-card-icon{ flex-shrink:0; }
  @media(min-width:480px){
    /* On larger screens: icon stacked above text */
    .bv-card-inner{ display:block; }
    .bv-card-icon{ margin-bottom:.75rem; }
  }

  /* ── Steps — mobile first: column → row ─────── */
  .bv-steps-row{
    display:flex; flex-direction:column; gap:.625rem;
  }
  @media(min-width:480px){
    .bv-steps-row{ flex-direction:row; gap:.75rem; }
    .bv-steps-row .bv-step{ flex:1; }
  }
  /* On mobile: step is horizontal (number left, text right) */
  .bv-step{
    border-radius:1rem; padding:1rem 1.125rem;
    cursor:default; transform-style:preserve-3d; will-change:transform;
    transition:transform .35s ease, box-shadow .35s ease;
    position:relative; overflow:hidden;
    background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.09);
    backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px);
    display:flex; align-items:center; gap:.875rem;
  }
  .bv-step::before{
    content:''; position:absolute; top:0; left:0; right:0; height:2.5px;
    background:var(--sc,#c9a84c);
    transform:scaleX(0); transform-origin:left;
    transition:transform .28s ease;
  }
  .bv-step:hover::before{ transform:scaleX(1); }
  @media(min-width:480px){
    /* On desktop: step stacks vertically like before */
    .bv-step{ display:block; padding:1.25rem 1.125rem; }
    .bv-step-num{ margin-bottom:.625rem; }
  }
  html:not(.dark) .bv-step{
    background:rgba(255,255,255,.82); border-color:rgba(0,0,0,.08);
    box-shadow:0 2px 8px rgba(0,0,0,.06);
  }

  /* ── Step number badge ───────────────────────── */
  .bv-step-num{
    width:34px; height:34px; border-radius:.5rem; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:.9375rem; font-weight:700;
    background:rgba(201,168,76,.12); border:1px solid rgba(201,168,76,.25); color:#c9a84c;
  }
  html:not(.dark) .bv-step-num{ background:rgba(201,168,76,.1); border-color:rgba(201,168,76,.3); color:#92710d; }

  /* ── Color tokens — dark default ─────────────── */
  .t-h  { color:#f1f5f9 }
  .t-s  { color:#94a3b8 }
  .t-m  { color:#cbd5e1 }
  .t-gold{ color:#c9a84c }
  .t-ct { color:#f1f5f9; font-size:.875rem; font-weight:600; margin:0 0 .2rem; line-height:1.3 }
  .t-cb { color:#94a3b8; font-size:.8rem; margin:0; line-height:1.5 }
  .t-dl { color:#475569 }
  .bv-rule-line-c{ background:rgba(255,255,255,.08) }

  html:not(.dark) .t-h  { color:#0f172a }
  html:not(.dark) .t-s  { color:#64748b }
  html:not(.dark) .t-m  { color:#334155 }
  html:not(.dark) .t-gold{ color:#92710d }
  html:not(.dark) .t-ct { color:#0f172a }
  html:not(.dark) .t-cb { color:#475569 }
  html:not(.dark) .t-dl { color:#94a3b8 }
  html:not(.dark) .bv-rule-line-c{ background:#e2e8f0 }

  /* ── Pills ───────────────────────────────────── */
  .bv-pill{
    display:inline-flex; align-items:center; gap:.35rem;
    border-radius:9999px; padding:.3rem .9rem;
    background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.13);
    backdrop-filter:blur(10px); font-size:.75rem;
    transition:background .2s, border-color .2s;
  }
  html:not(.dark) .bv-pill{
    background:rgba(30,58,138,.06); border-color:rgba(30,58,138,.14);
  }

  @media(prefers-reduced-motion:reduce){
    .bv-a1,.bv-a2,.bv-a3,.bv-icon-ring{ animation:none; opacity:1; transform:none }
    .bv-card,.bv-step{ transition:none }
  }
</style>
@endverbatim

<div style="display:flex;flex-direction:column;gap:1.375rem;padding:.25rem 0;">

  {{-- ══════════════════════════ HERO ══════════════════════════ --}}
  <div class="bv-hero bv-a1" id="{{ $uid }}_hero">
    <canvas id="{{ $uid }}_canvas" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;opacity:.4;"></canvas>

    <div style="position:absolute;inset:0;pointer-events:none;overflow:hidden;">
      <div class="bv-hero-orb-blue" style="position:absolute;width:240px;height:240px;top:-60px;right:-40px;border-radius:50%;background:radial-gradient(circle,rgba(30,58,138,.45),transparent 70%);filter:blur(40px);transition:background .4s;"></div>
      <div class="bv-hero-orb-gold" style="position:absolute;width:160px;height:160px;bottom:-40px;left:-30px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,.18),transparent 70%);filter:blur(36px);transition:background .4s;"></div>
    </div>

    <div style="position:relative;z-index:1;">
      <div class="bv-icon-ring" style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:50%;background:rgba(201,168,76,.12);border:1.5px solid rgba(201,168,76,.35);margin-bottom:1.125rem;">
        <svg style="width:28px;height:28px;color:#c9a84c" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16M6 8h12M6 8 3 14h6zm12 0-3 6h6zM9 20h6"/>
        </svg>
      </div>

      <p class="t-gold" style="font-size:.7rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;margin:0 0 .4rem;">
        Proceso Disciplinario Laboral
      </p>

      <h1 class="bv-title t-h">Asistente de Gestión Jurídica</h1>

      <p class="t-s bv-subtitle-wrap" style="font-size:.875rem;line-height:1.65;margin:0;">
        Bienvenido/a, <strong class="t-m" style="font-weight:500;">{{ $nombre }}</strong>.
        Le guiaremos paso a paso para registrar el proceso conforme al
        <span class="t-gold" style="font-weight:500;">Código Sustantivo del Trabajo</span>.
      </p>

      <div style="display:flex;gap:.5rem;margin-top:1.125rem;flex-wrap:wrap;justify-content:center;">
        <span class="bv-pill t-s">
          <svg style="width:11px;height:11px;color:#c9a84c;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          CST Art. 111–115
        </span>
        <span class="bv-pill t-s">
          <svg style="width:11px;height:11px;color:#60a5fa;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          5–10 minutos
        </span>
        <span class="bv-pill t-s">
          <svg style="width:11px;height:11px;color:#34d399;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          Confidencial
        </span>
      </div>
    </div>
  </div>

  {{-- ══════════════════════════ AL FINALIZAR ══════════════════════════ --}}
  <div class="bv-a2">
    <div class="bv-rule">
      <div class="bv-rule-line bv-rule-line-c"></div>
      <span class="bv-rule-label t-dl">Al finalizar el registro</span>
      <div class="bv-rule-line bv-rule-line-c"></div>
    </div>

    <div class="bv-cards-grid" id="{{ $uid }}_cards">
      @foreach([
        ['icon'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
         'ic'=>'#60a5fa','ib'=>'rgba(96,165,250,.12)','label'=>'Documentación','title'=>'Acta de Citación',
         'body'=>'Se genera en PDF con plena validez legal.'],
        ['icon'=>'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
         'ic'=>'#34d399','ib'=>'rgba(52,211,153,.12)','label'=>'Notificación','title'=>'Citación al Trabajador',
         'body'=>'Se envía de inmediato al correo del trabajador.'],
        ['icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
         'ic'=>'#c9a84c','ib'=>'rgba(201,168,76,.12)','label'=>'Audiencia','title'=>'Diligencia de Descargos',
         'body'=>'45 minutos para que el trabajador presente su defensa.'],
      ] as $c)
        <div class="bv-card">
          <div class="bv-card-inner">
            <div class="bv-card-icon" style="width:38px;height:38px;border-radius:.625rem;background:{{ $c['ib'] }};border:1px solid {{ $c['ic'] }}33;display:flex;align-items:center;justify-content:center;">
              <svg style="width:19px;height:19px;color:{{ $c['ic'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $c['icon'] }}"/>
              </svg>
            </div>
            <div>
              <p style="font-size:.6rem;font-weight:700;letter-spacing:.13em;text-transform:uppercase;color:{{ $c['ic'] }};margin:0 0 .2rem;opacity:.85;">{{ $c['label'] }}</p>
              <p class="t-ct">{{ $c['title'] }}</p>
              <p class="t-cb">{{ $c['body'] }}</p>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- ══════════════════════════ CÓMO FUNCIONA ══════════════════════════ --}}
  <div class="bv-a3">
    <div class="bv-rule">
      <div class="bv-rule-line bv-rule-line-c"></div>
      <span class="bv-rule-label t-dl">Cómo funciona</span>
      <div class="bv-rule-line bv-rule-line-c"></div>
    </div>

    <div class="bv-steps-row" id="{{ $uid }}_steps">
      @foreach([
        ['n'=>'1','sc'=>'#60a5fa','title'=>'Identificar las partes',
         'body'=>'Seleccione la empresa y el trabajador involucrado.'],
        ['n'=>'2','sc'=>'#c9a84c','title'=>'Relato de los hechos',
         'body'=>'Describa la situación — la IA redacta la versión jurídica.'],
        ['n'=>'3','sc'=>'#34d399','title'=>'Fijar la audiencia',
         'body'=>'Programe la fecha y hora de la diligencia virtual.'],
      ] as $p)
        <div class="bv-step" style="--sc:{{ $p['sc'] }}">
          <div class="bv-step-num">{{ $p['n'] }}</div>
          <div>
            <p class="t-ct">{{ $p['title'] }}</p>
            <p class="t-cb">{{ $p['body'] }}</p>
          </div>
        </div>
      @endforeach
    </div>
  </div>

</div>

<script>
(function(){
  var UID    = '{{ $uid }}';
  var hero   = document.getElementById(UID+'_hero');
  var canvas = document.getElementById(UID+'_canvas');
  if(!canvas||!hero) return;

  /* ── Canvas particles ───────────────────────── */
  var ctx = canvas.getContext('2d'), raf, pts = [];

  function resize(){ canvas.width=hero.offsetWidth; canvas.height=hero.offsetHeight; }
  resize();
  window.addEventListener('resize', function(){ resize(); pts=[]; init(); });

  function init(){
    pts=[];
    for(var i=0;i<28;i++) pts.push({
      x:Math.random()*canvas.width, y:Math.random()*canvas.height,
      vx:(Math.random()-.5)*.3, vy:(Math.random()-.5)*.3,
      r:Math.random()*1.4+.4
    });
  }
  init();

  function isDark(){ return document.documentElement.classList.contains('dark'); }

  function draw(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    var dark=isDark();
    var dc=dark?'rgba(201,168,76,.65)':'rgba(30,58,138,.35)';
    var lc=dark?'rgba(201,168,76,':'rgba(30,58,138,';
    pts.forEach(function(p){
      p.x+=p.vx; p.y+=p.vy;
      if(p.x<0||p.x>canvas.width)  p.vx*=-1;
      if(p.y<0||p.y>canvas.height) p.vy*=-1;
      ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
      ctx.fillStyle=dc; ctx.fill();
    });
    for(var a=0;a<pts.length;a++) for(var b=a+1;b<pts.length;b++){
      var dx=pts[a].x-pts[b].x, dy=pts[a].y-pts[b].y, d=Math.sqrt(dx*dx+dy*dy);
      if(d<85){ ctx.beginPath(); ctx.moveTo(pts[a].x,pts[a].y); ctx.lineTo(pts[b].x,pts[b].y);
        ctx.strokeStyle=lc+(0.3*(1-d/85))+')'; ctx.lineWidth=.5; ctx.stroke(); }
    }
    raf=requestAnimationFrame(draw);
  }
  draw();

  if('IntersectionObserver' in window)
    new IntersectionObserver(function(e){ if(!e[0].isIntersecting) cancelAnimationFrame(raf); else draw(); }).observe(hero);

  /* ── 3D Tilt — desktop (hover capable) only ── */
  var hasHover = window.matchMedia('(hover:hover) and (pointer:fine)').matches;
  if(!hasHover) return;

  document.querySelectorAll('#'+UID+'_cards .bv-card, #'+UID+'_steps .bv-step').forEach(function(el){
    el.addEventListener('mousemove',function(e){
      var r=el.getBoundingClientRect();
      var rx=((e.clientY-r.top-r.height/2)/(r.height/2))*7;
      var ry=((r.width/2-(e.clientX-r.left))/(r.width/2))*7;
      el.style.setProperty('--mx',((e.clientX-r.left)/r.width*100)+'%');
      el.style.setProperty('--my',((e.clientY-r.top)/r.height*100)+'%');
      el.style.transition='transform .1s ease,box-shadow .1s ease';
      el.style.transform='perspective(700px) rotateX('+rx+'deg) rotateY('+ry+'deg) scale(1.025)';
      el.style.boxShadow='0 22px 50px rgba(0,0,0,.3)';
    });
    el.addEventListener('mouseleave',function(){
      el.style.transition='transform .45s cubic-bezier(.16,1,.3,1),box-shadow .45s ease';
      el.style.transform='perspective(700px) rotateX(0deg) rotateY(0deg) scale(1)';
      el.style.boxShadow='';
    });
  });
})();
</script>
