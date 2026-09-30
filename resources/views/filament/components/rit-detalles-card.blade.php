{{--
    Tarjeta "Detalles del Reglamento" (pedido del usuario, 2026-09-30):
    primera prueba del estilo visual inspirado en los paneles de Hostinger
    (hPanel) - filas etiqueta/valor limpias con separadores sutiles dentro
    de una tarjeta, en vez de metadatos dispersos por la página. Reusa el
    shell .rit-viewer/.rit-viewer-header/.rit-viewer-label ya establecido
    en mi-reglamento-interno.blade.php - solo las filas internas (.rit-dl-*)
    son nuevas.

    Deliberadamente NO repite fecha de publicación/plazo de objeción (ya
    viven en rit-compartir-banner y en la barra de progreso) - esta
    tarjeta se enfoca en metadatos que hoy no se muestran en ningún otro
    lado (fuente, versión, última actualización), para no triplicar la
    misma información en una sola pantalla.

    Recibe: $reglamento (App\Models\ReglamentoInterno).
--}}
@php
    $fuenteLabel = match ($reglamento?->fuente) {
        'construido_ia' => 'Construido con IA',
        'mejora_ia' => 'Mejorado con IA',
        'subido' => 'Subido manualmente',
        default => '—',
    };
@endphp
<div class="rit-viewer" style="margin:0;height:100%">
    <div class="rit-viewer-header">
        <span class="rit-viewer-label">Detalles del Reglamento</span>
        <span class="rit-badge rit-badge-sub">Vigente</span>
    </div>
    <div>
        <div class="rit-dl-item">
            <span class="rit-dl-label">Fuente</span>
            <span class="rit-dl-value">{{ $fuenteLabel }}</span>
        </div>
        <div class="rit-dl-item">
            <span class="rit-dl-label">Versión</span>
            <span class="rit-dl-value">{{ $reglamento?->version ?? 1 }}</span>
        </div>
        <div class="rit-dl-item">
            <span class="rit-dl-label">Actualizado</span>
            <span class="rit-dl-value">{{ $reglamento?->updated_at?->format('d/m/Y g:i A') }}</span>
        </div>
    </div>
</div>
