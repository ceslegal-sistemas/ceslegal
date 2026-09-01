{{--
    Historial/auditoría de la solicitud (quién hizo qué y cuándo), mismo
    lenguaje visual que rit-info-card.blade.php - lista los eventos
    registrados por SolicitudContratoObserver + TimelineService en orden
    cronológico descendente (más reciente primero).

    Variables esperadas:
      $eventos  \Illuminate\Support\Collection<\App\Models\Timeline>
--}}
<div class="rit-info-card">
    <div class="rit-info-header">
        <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold"
            colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
            data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
            data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
            style="width:24px;height:24px;flex-shrink:0">
        </lord-icon>
        <p class="rit-info-title">Historial de la Solicitud</p>
    </div>
    <div class="rit-info-body">
        @if ($eventos->isEmpty())
            <p class="rit-info-value rit-info-value-muted">Aún no hay eventos registrados.</p>
        @else
            <div class="rit-timeline-list">
                @foreach ($eventos as $evento)
                    <div class="rit-timeline-item">
                        <span class="rit-timeline-dot"></span>
                        <span class="rit-timeline-line"></span>
                        <p class="rit-timeline-accion">{{ $evento->accion }}</p>
                        @if ($evento->descripcion)
                            <p class="rit-timeline-descripcion">{{ $evento->descripcion }}</p>
                        @endif
                        @if ($evento->accion === 'Cambio de estado' && ($evento->metadata['motivo_rechazo'] ?? null))
                            <p class="rit-timeline-descripcion">Motivo: {{ $evento->metadata['motivo_rechazo'] }}</p>
                        @endif
                        <p class="rit-timeline-meta">
                            {{ $evento->user?->name ?? 'Sistema' }} · {{ $evento->created_at->format('d/m/Y h:i A') }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
