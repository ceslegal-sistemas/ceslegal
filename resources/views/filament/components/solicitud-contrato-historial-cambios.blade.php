{{--
    Tarjeta "Historial de Cambios" en Ver Contrato - lista los otrosíes ya
    aplicados a ESTE contrato, mismo lenguaje visual (.rit-info-card) que el
    resto de la página. $modificaciones ya viene ordenada por fecha_efectiva
    desc (ver SolicitudContrato::modificaciones()).
--}}
<div class="rit-info-card">
    <div class="rit-info-header">
        <lord-icon src="https://cdn.lordicon.com/edcgvlnw.json" trigger="loop" delay="800" stroke="bold"
            colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
            data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
            data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
            style="width:24px;height:24px;flex-shrink:0">
        </lord-icon>
        <p class="rit-info-title">Historial de Cambios</p>
    </div>
    <div class="rit-info-body">
        @if($modificaciones->isEmpty())
            <p class="rit-info-value rit-info-value-muted">Este contrato no ha tenido cambios formales.</p>
        @else
            <div style="display:flex;flex-direction:column;gap:.65rem">
                @foreach($modificaciones as $modificacion)
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;padding:.6rem .75rem;border-radius:.6rem;background:rgba(148,163,184,.08);border:1px solid rgba(148,163,184,.18)">
                        <div>
                            <p style="margin:0;font-size:.85rem;font-weight:600">
                                {{ \App\Models\ModificacionContractual::TIPOS[$modificacion->tipo_modificacion] ?? $modificacion->tipo_modificacion }}
                                <span style="font-weight:400;color:#94a3b8">
                                    {{ $modificacion->valor_anterior }} &rarr; {{ $modificacion->valor_nuevo }}
                                </span>
                            </p>
                            <p style="margin:.15rem 0 0;font-size:.75rem;color:#94a3b8">
                                Efectiva desde el {{ $modificacion->fecha_efectiva?->format('d/m/Y') }}
                            </p>
                        </div>
                        @if($modificacion->ruta_otrosi)
                            <a href="{{ route('modificacion-contractual.descargar', $modificacion) }}" target="_blank" style="font-size:.8rem;white-space:nowrap">
                                Ver documento
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
