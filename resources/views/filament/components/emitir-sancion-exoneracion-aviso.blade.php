{{--
    Aviso legal: decisión contraria a la recomendación jurídica de la IA.
    Variables esperadas: $tipoSeleccionado (string), $iaRazonesNoRecomendadas (array)
    Extraído del Placeholder 'exoneracion_aviso' que vivía inline en
    ProcesoDisciplinarioResource.php (Sección "Decisión Contraria a la
    Recomendación Jurídica", ahora eliminada de ese ->form()).

    Convertido a .rit-hero (2026-09-16) - quedó fuera del barrido de
    conversión aplicado al resto del modal en una sesión anterior, por error
    de alcance (ver backlog-emitir-sancion-feedback-detallado-2026-09-16,
    punto 4).
--}}
@php
    $labelsMap = [
        'llamado_atencion' => 'Llamado de Atención',
        'suspension'       => 'Suspensión Laboral',
        'multa'            => 'Multa',
        'terminacion'      => 'Terminación de Contrato',
        'no_sancion'       => 'No Aplicar Sanción',
    ];

    $razonEspecifica   = $iaRazonesNoRecomendadas[$tipoSeleccionado] ?? null;
    $labelSeleccionado = $labelsMap[$tipoSeleccionado] ?? ucfirst(str_replace('_', ' ', $tipoSeleccionado ?? ''));
@endphp

<div class="rit-hero" style="padding:1.25rem 1.5rem;">
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">
        <span class="rit-badge rit-badge-danger">
            <lord-icon src="https://cdn.lordicon.com/hmpomorl.json" trigger="loop" delay="500" stroke="bold" colors="primary:#fca5a5,secondary:#fca5a5" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
            Advertencia Legal
        </span>
        <h1 class="rit-title">Decisión contraria a la recomendación jurídica</h1>
        <p class="rit-sub">
            La decisión que está tomando va en contra de la recomendación jurídica emitida por el
            sistema de inteligencia artificial de LUPE Legal.
            <strong>LUPE Legal no se responsabiliza por las consecuencias legales, laborales o
                judiciales derivadas de esta decisión.</strong>
        </p>

        @if($razonEspecifica)
            <div style="margin-top:0.75rem;padding:0.75rem 0.875rem;background:rgba(239,68,68,0.08);border-radius:0.625rem;border:1px solid rgba(239,68,68,0.2);">
                <p class="rit-sub" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;margin:0 0 0.3rem;">Por qué la IA no recomienda «{{ $labelSeleccionado }}»</p>
                <p class="rit-sub" style="margin:0;">{{ $razonEspecifica }}</p>
            </div>
        @endif
    </div>
</div>
