{{--
    Modal "Ver Actas" del historial de aceptaciones del RIT de un trabajador
    (evidencia jurídica) - se muestra cuando hay 2+ actas generadas.
    Branding LUPE: hero rosa/durazno en claro, oscuro con brillo rosa en dark.

    Variables esperadas:
      $actas ( Collection<AceptacionReglamentoInterno> ) - ya ordenadas desc
      $trabajador (App\Models\Trabajador)
--}}
@include('filament.modals.partials.impugnacion-estilos')
@verbatim
<style>
.act-fila{display:flex;align-items:center;justify-content:space-between;gap:.9rem;padding:.85rem 1rem;border-radius:.95rem;background:rgba(225,29,72,.04);border:1px solid rgba(225,29,72,.14)}
.act-fila-vigente{background:rgba(225,29,72,.09);border-color:rgba(225,29,72,.35)}
html.dark .act-fila{background:rgba(225,29,72,.08);border-color:rgba(225,29,72,.2)}
html.dark .act-fila-vigente{background:rgba(225,29,72,.16);border-color:rgba(251,113,133,.45)}
.act-fecha{margin:0;font-size:.92rem;font-weight:700;color:#1c1917;display:flex;flex-wrap:wrap;align-items:center;gap:.5rem}
html.dark .act-fecha{color:#f1f5f9}
.act-hash{margin:.2rem 0 0;font-family:ui-monospace,Menlo,monospace;font-size:.72rem;color:#78716c}
html.dark .act-hash{color:#94a3b8}
.act-tag{font-size:.62rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:.2rem .6rem;border-radius:2rem;background:rgba(22,163,74,.12);border:1px solid rgba(22,163,74,.3);color:#166534}
html.dark .act-tag{color:#86efac}
</style>
@endverbatim
<div class="space-y-4">
    <div class="rit-hero" style="padding:1.1rem 1.25rem;">
        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
        <div style="position:relative;z-index:2">
            <span class="rit-badge rit-badge-ia">Evidencia jurídica</span>
            <h1 class="rit-title">Actas de socialización del RIT</h1>
            <p class="rit-sub">{{ $trabajador->nombre_completo ?? 'Trabajador' }}: cada actualización del reglamento exige una nueva aceptación. La más reciente es la vigente.</p>
        </div>
    </div>

    <div style="display:grid;gap:.6rem;">
        @foreach($actas as $i => $acta)
            <div class="act-fila {{ $i === 0 ? 'act-fila-vigente' : '' }}">
                <div style="min-width:0">
                    <p class="act-fecha">
                        {{ $acta->aceptado_en?->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm A') }}
                        @if($i === 0)<span class="act-tag">Vigente</span>@endif
                    </p>
                    <p class="act-hash">SHA-256: {{ \Illuminate\Support\Str::limit($acta->texto_rit_hash, 24, '…') }}</p>
                </div>
                <a href="{{ route('trabajador.acta-rit.descargar', ['trabajador' => $trabajador->id, 'aceptacion' => $acta->id]) }}"
                    target="_blank" class="rit-btn rit-btn-primary" style="flex-shrink:0;text-decoration:none;">
                    @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                    Descargar
                </a>
            </div>
        @endforeach
    </div>
</div>
