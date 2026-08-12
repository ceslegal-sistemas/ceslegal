{{--
    Aviso legal: decisión contraria a la recomendación jurídica de la IA.
    Variables esperadas: $tipoSeleccionado (string), $iaRazonesNoRecomendadas (array)
    Extraído del Placeholder 'exoneracion_aviso' que vivía inline en
    ProcesoDisciplinarioResource.php (Sección "Decisión Contraria a la
    Recomendación Jurídica", ahora eliminada de ese ->form()).
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

<style>
:root {
    --exo-label:rgba(0,0,0,0.45);
    --exo-text:rgba(17,24,39,0.78);
    --exo-strong:#b91c1c;
    --exo-reason-bg:rgba(239,68,68,0.06);
    --exo-reason-border:rgba(239,68,68,0.18);
}
html.dark {
    --exo-label:rgba(255,255,255,0.35);
    --exo-text:rgba(255,255,255,0.70);
    --exo-strong:rgba(255,160,160,0.95);
    --exo-reason-bg:rgba(239,68,68,0.10);
    --exo-reason-border:rgba(248,113,113,0.25);
}
</style>

<div style="padding:16px 18px;background:rgba(239,68,68,0.11);border-radius:14px;border:1px solid rgba(239,68,68,0.22);border-left:3px solid #f87171;">
    <div style="display:flex;align-items:flex-start;gap:12px;">
        <lord-icon
            src="https://cdn.lordicon.com/hmpomorl.json"
            trigger="loop" delay="800" stroke="bold"
            colors="primary:#f87171,secondary:#fca5a5"
            style="width:36px;height:36px;flex-shrink:0;margin-top:-2px">
        </lord-icon>
        <div style="flex:1;min-width:0;">
            <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--exo-label);margin:0 0 4px;">Advertencia Legal</p>
            <p style="font-size:15px;font-weight:800;color:#f87171;margin:0 0 10px;line-height:1.2;">Decisión contraria a la recomendación jurídica</p>
            <p style="font-size:13px;color:var(--exo-text);line-height:1.6;margin:0;">La decisión que está tomando va en contra de la recomendación jurídica emitida por el sistema de inteligencia artificial de LUPE. <strong style="color:var(--exo-strong);">LUPE no se responsabiliza por las consecuencias legales, laborales o judiciales derivadas de esta decisión.</strong></p>

            @if($razonEspecifica)
                <div style="margin-top:12px;padding:12px 14px;background:var(--exo-reason-bg);border-radius:10px;border:1px solid var(--exo-reason-border);">
                    <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--exo-label);margin:0 0 5px;">Por qué la IA no recomienda «{{ $labelSeleccionado }}»</p>
                    <p style="font-size:13px;color:var(--exo-text);line-height:1.6;margin:0;">{{ $razonEspecifica }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
