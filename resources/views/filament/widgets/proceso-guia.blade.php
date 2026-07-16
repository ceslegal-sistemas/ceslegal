{{-- Guía "Tu proceso" - datos desde GuiaProcesoService. Un solo root (widget Filament). --}}
@php $g = $guia; @endphp

<div class="pg-wrap">
    @if(($g['estado'] ?? 'oculto') === 'oculto')
        {{-- nada --}}

    @elseif($g['estado'] === 'sin_empresa')
        {{-- Bufete recién registrado: solo crear la primera empresa --}}
        <div class="pg-card pg-onboard">
            <lord-icon src="https://cdn.lordicon.com/bduzytli.json" trigger="loop" delay="600"
                colors="primary:#e11d48,secondary:#fb923c" style="width:96px;height:96px"></lord-icon>
            <div class="pg-onboard-txt">
                <p class="pg-kicker">Primer paso</p>
                <h2 class="pg-h2">Cree su primera empresa</h2>
                <p class="pg-lead">Para gestionar reglamentos, trabajadores y descargos, primero registre una empresa. Luego selecciónela en la barra superior para habilitar todo.</p>
                <a href="{{ $g['accion']['url'] }}" class="pg-btn pg-btn-primary">{{ $g['accion']['label'] }}</a>
            </div>
        </div>

    @elseif($g['estado'] === 'sin_seleccion')
        <div class="pg-card pg-hint">
            @svg('heroicon-o-building-office-2', 'pg-hint-ico')
            <div>
                <h2 class="pg-h2">Seleccione una empresa</h2>
                <p class="pg-lead">Elija una empresa en el selector de la barra superior para ver su proceso paso a paso.</p>
            </div>
        </div>

    @else
        {{-- estado ok: roadmap --}}
        @php
            // Iconos lordicon por paso (animan con loop al pasar el mouse). El estado
            // 'done' usa el icono de check. Los colores se ajustan según el estado del
            // nodo (blanco sobre fondo de color; gris cuando está pendiente).
            $lord = [
                'cuenta'     => ['src' => 'kdduutaw.json', 'state' => 'hover-looking-around'],
                'rit'        => ['src' => 'fikcyfpp.json', 'state' => null],
                'descargo'   => ['src' => 'exymduqj.json', 'state' => 'hover-line'],
                'diligencia' => ['src' => 'warimioc.json', 'state' => null],
                'sancion'    => ['src' => 'bduzytli.json', 'state' => null],
            ];
            $lordCheck = ['src' => 'lvrxlmju.json', 'state' => 'hover-loading'];
        @endphp
        <div class="pg-card">
            <div class="pg-head">
                <div>
                    <p class="pg-kicker">Tu proceso</p>
                    <h2 class="pg-h2">{{ $g['empresa']->razon_social ?? '' }}</h2>
                </div>
            </div>

            {{-- Fila de pasos --}}
            <div class="pg-steps">
                @foreach($g['pasos'] as $i => $paso)
                    @php
                        $ic = $paso['estado'] === 'done' ? $lordCheck : ($lord[$paso['clave']] ?? null);
                        // Color del icono según el estado del nodo (fondo de color -> blanco).
                        $icColor = $paso['estado'] === 'pending'
                            ? 'primary:#a8a29e,secondary:#a8a29e'
                            : 'primary:#ffffff,secondary:#ffffff';
                    @endphp
                    <div class="pg-step pg-{{ $paso['estado'] }}">
                        <span class="pg-node">
                            @if($ic)
                                <lord-icon
                                    src="https://cdn.lordicon.com/{{ $ic['src'] }}"
                                    trigger="loop-on-hover"
                                    @if(!empty($ic['state'])) state="{{ $ic['state'] }}" @endif
                                    colors="{{ $icColor }}"
                                    class="pg-node-lord"></lord-icon>
                            @else
                                @svg('heroicon-o-minus', 'pg-node-ico')
                            @endif
                        </span>
                        <span class="pg-step-label">{{ $paso['label'] }}</span>
                        @if(!$loop->last)<span class="pg-line"></span>@endif
                    </div>
                @endforeach
            </div>

            {{-- Acción siguiente --}}
            @if($accion = ($g['accion'] ?? null))
                <div class="pg-action pg-action-{{ $accion['tipo'] }}">
                    <div class="pg-action-txt">
                        <p class="pg-action-label">{{ $accion['label'] }}</p>
                        @if(!empty($accion['nota']))<p class="pg-action-nota">{{ $accion['nota'] }}</p>@endif
                    </div>
                    @if($accion['tipo'] !== 'info')
                        <a href="{{ $accion['url'] }}" class="pg-btn pg-btn-{{ $accion['tipo'] === 'sancion' ? 'sancion' : 'primary' }}">
                            {{ $accion['label'] }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Listos para sancionar --}}
            @if(!empty($g['listos']))
                <div class="pg-listos">
                    <p class="pg-listos-title">Listos para sancionar</p>
                    @foreach($g['listos'] as $l)
                        <a href="{{ $g['sancion_url'] }}" class="pg-listo">
                            @svg('heroicon-o-scale', 'pg-listo-ico')
                            <span><strong>{{ $l['trabajador'] }}</strong> · {{ $l['codigo'] }}</span>
                            <span class="pg-listo-cta">Emitir sanción →</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <style>
        .pg-card{background:#fff;border:1px solid rgba(0,0,0,.07);border-radius:1rem;padding:1.25rem 1.5rem;box-shadow:0 4px 20px rgba(28,25,23,.05)}
        html.dark .pg-card{background:rgba(255,255,255,.02);border-color:rgba(255,255,255,.07)}
        .pg-kicker{font-size:.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#be123c;margin:0 0 2px}
        html.dark .pg-kicker{color:#fb7185}
        .pg-h2{font-family:'Space Grotesk',sans-serif;font-size:1.15rem;font-weight:700;margin:0;color:#1c1917}
        html.dark .pg-h2{color:#f5f5f4}
        .pg-lead{font-size:.85rem;line-height:1.5;color:#78716c;margin:.35rem 0 .9rem;max-width:60ch}
        html.dark .pg-lead{color:#a8a29e}
        .pg-head{margin-bottom:1.1rem}
        /* pasos - una sola fila en escritorio, scroll horizontal en móvil (nunca se corta) */
        .pg-steps{display:flex;flex-wrap:nowrap;gap:0;align-items:flex-start;margin-bottom:1.1rem}
        .pg-step{position:relative;display:flex;flex-direction:column;align-items:center;flex:1 1 0;min-width:0;gap:.4rem;padding:0 .15rem}
        .pg-node{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;
            background:#f5f5f4;border:2px solid #e7e5e4;color:#a8a29e;z-index:1}
        html.dark .pg-node{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.12)}
        .pg-node-ico{width:19px;height:19px}
        .pg-node-lord{width:22px;height:22px;display:block}
        .pg-step-label{font-size:.72rem;font-weight:600;text-align:center;color:#78716c;line-height:1.25;max-width:13ch}
        @media(max-width:768px){
            .pg-steps{overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:.4rem}
            .pg-step{flex:0 0 5.5rem}
        }
        html.dark .pg-step-label{color:#a8a29e}
        .pg-line{position:absolute;top:18px;left:50%;width:100%;height:2px;background:#e7e5e4;z-index:0}
        html.dark .pg-line{background:rgba(255,255,255,.1)}
        .pg-step.pg-done .pg-node{background:#16a34a;border-color:#16a34a;color:#fff}
        .pg-step.pg-done .pg-line{background:#16a34a}
        .pg-step.pg-done .pg-step-label{color:#15803d}
        html.dark .pg-step.pg-done .pg-step-label{color:#86efac}
        .pg-step.pg-current .pg-node{background:linear-gradient(135deg,#e11d48,#f97316);border-color:transparent;color:#fff;
            box-shadow:0 0 0 4px rgba(225,29,72,.15)}
        .pg-step.pg-current .pg-step-label{color:#be123c;font-weight:700}
        html.dark .pg-step.pg-current .pg-step-label{color:#fb7185}
        /* acción */
        .pg-action{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;
            padding:.9rem 1.1rem;border-radius:.75rem;background:rgba(225,29,72,.05);border:1px solid rgba(225,29,72,.15)}
        .pg-action-info{background:rgba(0,0,0,.03);border-color:rgba(0,0,0,.07)}
        html.dark .pg-action{background:rgba(251,113,133,.08);border-color:rgba(251,113,133,.18)}
        html.dark .pg-action-info{background:rgba(255,255,255,.03);border-color:rgba(255,255,255,.07)}
        .pg-action-label{font-size:.92rem;font-weight:700;margin:0;color:#1c1917}
        html.dark .pg-action-label{color:#f5f5f4}
        .pg-action-nota{font-size:.78rem;color:#78716c;margin:.2rem 0 0;line-height:1.45}
        html.dark .pg-action-nota{color:#a8a29e}
        /* botones */
        .pg-btn{display:inline-flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:700;text-decoration:none;
            padding:.6rem 1.1rem;border-radius:.65rem;white-space:nowrap;transition:filter .15s,transform .1s}
        .pg-btn:hover{filter:brightness(1.05);transform:translateY(-1px)}
        .pg-btn-primary{background:linear-gradient(135deg,#e11d48,#f97316);color:#fff}
        .pg-btn-sancion{background:#dc2626;color:#fff}
        /* onboarding */
        .pg-onboard{display:flex;gap:1.5rem;align-items:center}
        @media(max-width:640px){.pg-onboard{flex-direction:column;text-align:center}}
        .pg-onboard lord-icon{flex-shrink:0}
        /* hint */
        .pg-hint{display:flex;gap:1rem;align-items:center}
        .pg-hint-ico{width:40px;height:40px;color:#be123c;flex-shrink:0}
        html.dark .pg-hint-ico{color:#fb7185}
        /* listos */
        .pg-listos{margin-top:1rem;padding-top:.9rem;border-top:1px solid rgba(0,0,0,.06)}
        html.dark .pg-listos{border-color:rgba(255,255,255,.07)}
        .pg-listos-title{font-size:.66rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#a8a29e;margin:0 0 .5rem}
        .pg-listo{display:flex;align-items:center;gap:.6rem;padding:.55rem .7rem;border-radius:.6rem;text-decoration:none;
            font-size:.85rem;color:#44403c;background:rgba(220,38,38,.05);border:1px solid rgba(220,38,38,.12);margin-bottom:.4rem}
        html.dark .pg-listo{color:#e7e5e4;background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.18)}
        .pg-listo:hover{background:rgba(220,38,38,.1)}
        .pg-listo-ico{width:18px;height:18px;color:#dc2626;flex-shrink:0}
        .pg-listo-cta{margin-left:auto;font-weight:700;color:#dc2626;font-size:.8rem}
        @media(prefers-reduced-motion:reduce){.pg-btn:hover{transform:none}}
    </style>
</div>
