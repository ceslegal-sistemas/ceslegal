@php
    /** @var \App\Models\Impugnacion $impugnacion */
    $ia = is_array($impugnacion->analisis_ia) ? $impugnacion->analisis_ia : null;
    $listo = $impugnacion->analisis_ia_estado === 'completado' && $ia;
    $decisionIa = $ia['decision_recomendada'] ?? null;
    $etiquetaDecision = match ($decisionIa) {
        'confirma_sancion' => 'Confirmar la sanción',
        'revoca_sancion' => 'Revocar la sanción',
        'modifica_sancion' => 'Modificar la sanción',
        default => null,
    };
    $nuevaSancion = $ia['modificacion_sugerida']['nueva_sancion'] ?? null;
    $nuevaTxt = match ($nuevaSancion) {
        'llamado_atencion' => 'Llamado de atención',
        'suspension' => 'Suspensión laboral',
        'multa' => 'Multa',
        'terminacion' => 'Terminación de contrato',
        'no_sancion' => 'Sin sanción',
        default => null,
    };
    $confianza = $ia['confianza'] ?? 'media';
    $valoraciones = [
        'fundado' => ['Fundado', 'imv-p-ok'],
        'parcialmente_fundado' => ['Parcial', 'imv-p-mid'],
        'infundado' => ['Infundado', 'imv-p-no'],
    ];
@endphp

<p class="imv-sec">Análisis con IA · sugerencia para su empresa</p>

@if ($impugnacion->analisisIaEnCurso())
    <div class="rit-viewer" style="margin-top:0" wire:poll.4s>
        <div class="rit-viewer-body" style="max-height:none;display:flex;align-items:center;gap:.8rem;">
            <span class="imv-giro"></span>
            <p class="imv-texto" style="font-family:inherit;font-size:.88rem;line-height:1.5;">
                Estamos analizando la impugnación contra el expediente (hechos, descargos, cronología y norma). Suele tardar menos de un minuto; esta pantalla se actualiza sola.
            </p>
        </div>
    </div>
@elseif (! $listo)
    <div class="imv-warn">
        <b>Análisis no disponible</b>
        {{ in_array($impugnacion->analisis_ia_estado, ['error', 'pendiente', 'procesando'], true) ? 'No pudimos generar el análisis automático esta vez.' : 'Todavía no se ha generado el análisis.' }}
        Puede reintentarlo con <strong>Generar análisis con IA</strong> en «Ver impugnación», o resolver directamente con su criterio.
    </div>
@else
    {{-- Sugerencia principal --}}
    <div class="rit-hero" style="padding:1.1rem 1.3rem;">
        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
        <div style="position:relative;z-index:2">
            <span class="rit-badge {{ $decisionIa === 'revoca_sancion' ? 'rit-badge-sub' : ($decisionIa === 'modifica_sancion' ? 'rit-badge-warning' : 'rit-badge-ia') }}">
                Sugerencia de la IA · confianza {{ $confianza }}
            </span>
            <h1 class="rit-title">{{ $etiquetaDecision ?? 'Revisión manual recomendada' }}@if ($decisionIa === 'modifica_sancion' && $nuevaTxt): {{ $nuevaTxt }}@endif</h1>
            <p class="rit-sub" style="line-height:1.55;">{{ $ia['resumen_para_cliente'] ?? ($ia['justificacion_decision'] ?? '') }}</p>
        </div>
    </div>

    @if (! empty($ia['evaluacion_motivos']))
        <p class="imv-sec">Cada motivo del trabajador</p>
        <div>
            @foreach ($ia['evaluacion_motivos'] as $m)
                @php [$txt, $cls] = $valoraciones[$m['valoracion'] ?? 'infundado'] ?? ['-', 'imv-p-no']; @endphp
                <div class="imv-row">
                    <span class="imv-pill {{ $cls }}">{{ $txt }}</span>
                    <p><strong>{{ $m['motivo'] ?? '' }}</strong><br>{{ $m['comentario'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if (! empty($ia['justificacion_decision']))
        <p class="imv-sec">Por qué lo sugiere</p>
        <p class="imv-texto" style="font-family:inherit;font-size:.88rem;line-height:1.65;">{{ $ia['justificacion_decision'] }}</p>
    @endif

    @if (! empty($ia['riesgos_nulidad']))
        <div class="imv-warn" style="margin-top:.9rem"><b>Riesgo si decide distinto</b>{{ $ia['riesgos_nulidad'] }}</div>
    @endif

    @if (! empty($ia['pasos_sugeridos']))
        <p class="imv-sec">Pasos que sugiere</p>
        <ul class="imv-lista">
            @foreach ($ia['pasos_sugeridos'] as $paso) <li>{{ $paso }}</li> @endforeach
        </ul>
    @endif

    <p class="imv-nota">
        La IA solo sugiere: <strong>la decisión final es de su empresa</strong>. Al resolver, la decisión y el fundamento vienen precargados con esta sugerencia y usted puede cambiarlos.
        @if ($impugnacion->analisis_ia_en) Generado el {{ $impugnacion->analisis_ia_en->timezone('America/Bogota')->locale('es')->isoFormat('D MMM YYYY, h:mm a') }}. @endif
    </p>
@endif
