{{--
    Aviso de transparencia cuando la revisión adicional en segundo plano
    (EjecutarValidacionesV6Job) encontró algo grave y corrigió automáticamente
    la recomendación. Nunca se reemplaza en silencio: se explica qué cambió y
    se deja ver la versión original para auditoría. Variables esperadas:
    $motivo (string), $original (array|null).
--}}
@php
    $original = is_array($original ?? null) ? $original : null;
    $sancionOriginal = $original['recomendacion_final']['sancion_principal'] ?? null;
    $estadoOriginal  = $original['recomendacion_final']['estado_recomendacion'] ?? null;
    $mensajeOriginal = $original['recomendacion_final']['mensaje_para_decision'] ?? null;
    $etiquetasSancion = [
        'llamado_atencion' => 'Llamado de Atención',
        'suspension'       => 'Suspensión Laboral',
        'multa'            => 'Multa',
        'terminacion'      => 'Terminación de Contrato',
    ];
@endphp

<div class="esa-card" style="margin-bottom:10px;border-left:3px solid #2563eb;">
    <div style="padding:12px 16px;">
        <div style="display:flex;align-items:flex-start;gap:8px;">
            <svg style="width:18px;height:18px;flex-shrink:0;color:#2563eb;margin-top:1px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
            <div style="flex:1;min-width:0;">
                <p style="font-size:12.5px;font-weight:600;color:var(--esa-text);margin:0;">Esta recomendación se ajustó automáticamente</p>
                <p style="font-size:12.5px;color:var(--esa-muted);line-height:1.55;margin:4px 0 0;">{{ $motivo }}</p>
            </div>
        </div>

        @if($original)
            <details style="margin-top:8px;">
                <summary style="cursor:pointer;font-size:11.5px;color:var(--esa-muted);list-style:none;display:flex;align-items:center;gap:5px;">
                    <svg style="width:12px;height:12px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 4.707a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L10.586 10 7.293 6.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    Ver la recomendación antes del ajuste
                </summary>
                <div style="margin:8px 0 0 17px;padding:8px 10px;border-left:2px solid rgba(0,0,0,.08);font-size:12px;color:var(--esa-muted);line-height:1.55;">
                    @if($estadoOriginal)
                        <p style="margin:0;"><strong style="color:var(--esa-text);">Estado:</strong> {{ ['sancionar' => 'Procede sancionar', 'condicionada' => 'Condicionada a verificación', 'no_sancionar' => 'No sancionar'][$estadoOriginal] ?? $estadoOriginal }}</p>
                    @endif
                    @if($sancionOriginal)
                        <p style="margin:4px 0 0;"><strong style="color:var(--esa-text);">Sanción principal sugerida:</strong> {{ $etiquetasSancion[$sancionOriginal] ?? $sancionOriginal }}</p>
                    @endif
                    @if($mensajeOriginal)
                        <p style="margin:4px 0 0;">{{ $mensajeOriginal }}</p>
                    @endif
                </div>
            </details>
        @endif
    </div>
</div>
