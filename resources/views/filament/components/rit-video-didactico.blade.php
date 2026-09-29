{{--
    Video didáctico de "segunda socialización" (pedido del equipo,
    2026-09-28) - se muestra solo si ya hay un video generado o si la
    generación anterior falló. Reusa .rit-viewer/.rit-viewer-label/.rit-btn
    ya establecidos en esta misma página, sin inventar clases nuevas.

    Variable esperada: $reglamento (App\Models\ReglamentoInterno)
--}}
@if($reglamento?->video_didactico_path || $reglamento?->tieneErrorVideoDidactico())
    <div class="rit-viewer" style="margin-top:1.25rem">
        <div class="rit-viewer-header">
            <span class="rit-viewer-label">Video didáctico del Reglamento</span>
            @if($reglamento->video_didactico_generado_en)
                <span style="font-size:.75rem;color:#64748b">
                    Generado {{ $reglamento->video_didactico_generado_en->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm A') }}
                </span>
            @endif
        </div>
        <div class="rit-viewer-body" style="padding:1.25rem 1.5rem">
            @if($reglamento->tieneErrorVideoDidactico())
                <p style="margin:0;font-size:.8125rem;color:#b91c1c">
                    No se pudo generar el video: {{ $reglamento->video_didactico_error }}
                </p>
            @elseif($reglamento->video_didactico_path)
                <video controls preload="metadata" style="width:100%;max-width:520px;border-radius:.75rem;display:block">
                    <source src="{{ route('rit.video-didactico', ['reglamento' => $reglamento->id]) }}" type="video/mp4">
                </video>
                <div style="margin-top:.75rem">
                    <a href="{{ route('rit.video-didactico', ['reglamento' => $reglamento->id]) }}" download class="rit-btn rit-btn-secondary">
                        <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        Descargar video
                    </a>
                </div>
            @endif
        </div>
    </div>
@endif
