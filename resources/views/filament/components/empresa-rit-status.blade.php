@php
    /** @var \App\Models\Empresa|null $empresa */
    // En infolist llega por $getRecord(); en formulario, por viewData ($empresa).
    $empresa = $empresa ?? (isset($getRecord) ? $getRecord() : null);
    $mostrarObligacion = $mostrarObligacion ?? true;

    $rit = $empresa?->reglamentoInterno;
    $tiene = $rit && ! empty($rit->texto_completo);

    $user = auth()->user();
    $esAdmin = $user?->hasRole('super_admin') || $user?->hasRole('abogado');
    $descargaUrl = $esAdmin ? route('rit.descargar.admin', $empresa) : route('rit.descargar');

    $fuente = $rit
        ? match ($rit->fuente) {
            'construido_ia' => 'Construido con IA',
            'subido'        => 'Subido manualmente',
            default         => 'Registrado',
        }
        : null;
    $fecha = $rit?->updated_at?->format('d/m/Y');
@endphp

<style>
    .ers-card{border-radius:16px;overflow:hidden;border:1px solid rgba(0,0,0,.07)}
    html.dark .ers-card{border-color:rgba(255,255,255,.08)}
    .ers-head{display:flex;align-items:center;gap:.85rem;padding:1rem 1.15rem}
    .ers-on .ers-head{background:linear-gradient(135deg,rgba(225,29,72,.10),rgba(249,115,22,.10))}
    html.dark .ers-on .ers-head{background:linear-gradient(135deg,rgba(225,29,72,.18),rgba(249,115,22,.15))}
    .ers-ring{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
        background:linear-gradient(135deg,#e11d48,#f97316);box-shadow:0 8px 20px rgba(225,29,72,.30)}
    .ers-off .ers-ring{background:#a8a29e;box-shadow:none}
    .ers-ring svg{width:22px;height:22px;color:#fff}
    .ers-ttl{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1rem;color:#1c1917;margin:0;line-height:1.2}
    html.dark .ers-ttl{color:#f5f5f4}
    .ers-badge{margin-left:auto;font-size:.66rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:.25rem .65rem;border-radius:100px}
    .ers-badge.ok{background:rgba(22,163,74,.12);color:#15803d} html.dark .ers-badge.ok{background:rgba(22,163,74,.22);color:#86efac}
    .ers-badge.no{background:rgba(120,113,108,.12);color:#78716c} html.dark .ers-badge.no{background:rgba(255,255,255,.08);color:#a8a29e}
    .ers-body{padding:.9rem 1.15rem 1.1rem}
    .ers-meta{display:flex;gap:1.5rem;flex-wrap:wrap;font-size:.8rem;color:#78716c;margin:0 0 .85rem}
    html.dark .ers-meta{color:#a8a29e}
    .ers-meta b{color:#1c1917;font-weight:600} html.dark .ers-meta b{color:#e7e5e4}
    .ers-btn{display:inline-flex;align-items:center;gap:.45rem;font-size:.82rem;font-weight:700;text-decoration:none;
        padding:.55rem 1.05rem;border-radius:.6rem;background:linear-gradient(135deg,#e11d48,#f97316);color:#fff;transition:filter .15s}
    .ers-btn:hover{filter:brightness(1.06)}
    .ers-btn svg{width:15px;height:15px;color:#fff}
    .ers-empty{font-size:.85rem;color:#78716c;line-height:1.5;margin:0}
    html.dark .ers-empty{color:#a8a29e}
    .ers-gap{height:.85rem}
</style>

@if ($mostrarObligacion && $empresa)
    {!! \App\Support\ObligacionRit::avisoHtml($empresa->numero_empleados, $empresa->actividadEconomica?->seccion) !!}
    <div class="ers-gap"></div>
@endif

<div class="ers-card {{ $tiene ? 'ers-on' : 'ers-off' }}">
    <div class="ers-head">
        <span class="ers-ring">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
        </span>
        <p class="ers-ttl">Reglamento Interno de Trabajo</p>
        <span class="ers-badge {{ $tiene ? 'ok' : 'no' }}">{{ $tiene ? 'Vigente' : 'Sin reglamento' }}</span>
    </div>

    <div class="ers-body">
        @if ($tiene)
            <p class="ers-meta">
                <span>Origen: <b>{{ $fuente }}</b></span>
                <span>Actualizado: <b>{{ $fecha }}</b></span>
            </p>
            <a href="{{ $descargaUrl }}" target="_blank" rel="noopener" class="ers-btn">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Descargar PDF
            </a>
        @else
            <p class="ers-empty">Esta empresa aún no tiene un Reglamento Interno de Trabajo vigente. Puede subirlo o construirlo con IA desde <b>Mi Reglamento Interno</b>.</p>
        @endif
    </div>
</div>
