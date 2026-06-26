{{--
    Placeholder skeleton para StatsOverviewWidget (carga lazy). Imita el grid de
    tarjetas de stats: cada tarjeta lleva una barra de etiqueta, una de valor
    (grande) y una de descripción. El CSS (.ces-sk / .ces-ssk-*) se inyecta
    globalmente desde AdminPanelProvider. $columns lo pasa el trait HasStatsSkeleton.
--}}
@php $columns = (int) ($columns ?? 4); @endphp

<div class="ces-ssk-grid c{{ $columns }}" aria-busy="true" aria-live="polite">
    @for ($i = 0; $i < $columns; $i++)
        <div class="ces-ssk-card">
            <div class="ces-sk" style="height:.6rem;border-radius:.375rem;width:45%"></div>
            <div class="ces-sk" style="height:1.6rem;border-radius:.45rem;width:60%"></div>
            <div class="ces-sk" style="height:.55rem;border-radius:.375rem;width:80%"></div>
        </div>
    @endfor
</div>
