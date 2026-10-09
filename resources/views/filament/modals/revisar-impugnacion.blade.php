@php
    $trabajador = $proceso->trabajador;
    $tipoSancion = match ($proceso->tipo_sancion) {
        'llamado_atencion' => 'Llamado de atención',
        'suspension' => 'Suspensión laboral' . ($proceso->dias_suspension ? " de {$proceso->dias_suspension} día(s)" : ''),
        'multa' => 'Multa',
        'terminacion' => 'Terminación de contrato',
        'no_sancion' => 'Sin sanción',
        default => ucfirst(str_replace('_', ' ', $proceso->tipo_sancion ?? 'N/A')),
    };
    $medios = ['correo_electronico' => 'Correo electrónico', 'carta_fisica' => 'Carta física', 'verbal' => 'Verbal', 'otro' => 'Otro medio', 'portal_web' => 'Portal en línea'];
    $pruebas = collect($impugnacion->pruebas_adicionales ?? []);
    $porElTrabajador = $impugnacion->presentadaPorElTrabajador();
@endphp

@include('filament.modals.partials.impugnacion-estilos')

<div>
    {{-- ═══ Hero ═══ --}}
    <div class="rit-hero" style="padding:1.25rem 1.4rem;">
        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
        <div style="position:relative;z-index:2">
            <span class="rit-badge {{ $porElTrabajador ? 'rit-badge-sub' : 'rit-badge-info' }}">
                {{ $porElTrabajador ? 'Presentada por el trabajador' : 'Registrada por la empresa' }} · {{ $impugnacion->radicado() }}
            </span>
            <h1 class="rit-title">{{ $trabajador?->nombre_completo }} impugnó la decisión</h1>
            <p class="rit-sub">Proceso {{ $proceso->codigo }} · {{ $trabajador?->cargo }} · {{ $proceso->empresa?->razon_social }}</p>
        </div>
    </div>

    {{-- ═══ Datos clave ═══ --}}
    <div class="imv-grid" style="margin-top:.9rem">
        <div class="imv-stat"><p class="imv-k">Decisión impugnada</p><p class="imv-v">{{ $tipoSancion }}</p></div>
        <div class="imv-stat">
            <p class="imv-k">Presentada</p>
            <p class="imv-v">{{ $impugnacion->fecha_impugnacion?->timezone('America/Bogota')->locale('es')->isoFormat('D MMM YYYY, h:mm a') ?? 'Sin fecha' }}</p>
        </div>
        <div class="imv-stat">
            <p class="imv-k">Medio</p>
            <p class="imv-v">{{ $medios[$impugnacion->medio_recepcion] ?? '-' }}</p>
        </div>
        @if ($porElTrabajador)
            <div class="imv-stat">
                <p class="imv-k">Identidad verificada</p>
                <p class="imv-v">Código al correo{{ $impugnacion->otp_verificado_en ? ' · ' . $impugnacion->otp_verificado_en->timezone('America/Bogota')->format('h:i a') : '' }}@if ($impugnacion->ip_registro)<br><span style="font-weight:500;font-size:.78rem;opacity:.75">IP {{ $impugnacion->ip_registro }}</span>@endif</p>
            </div>
        @endif
    </div>

    {{-- ═══ Lo que alega el trabajador ═══ --}}
    <p class="imv-sec">Lo que alega el trabajador</p>
    <div class="rit-viewer" style="margin-top:0">
        <div class="rit-viewer-body" style="max-height:none">
            <p class="imv-texto">{{ $impugnacion->motivos_impugnacion ?: 'No se expresaron motivos.' }}</p>
        </div>
    </div>

    {{-- ═══ Pruebas ═══ --}}
    <p class="imv-sec">Pruebas aportadas @if ($pruebas->isNotEmpty()) ({{ $pruebas->count() }}) @endif</p>
    @if ($pruebas->isEmpty())
        <p class="imv-nota" style="margin-top:0">El trabajador no adjuntó pruebas con la impugnación.</p>
    @else
        <div style="display:grid;gap:.5rem">
            @foreach ($pruebas as $prueba)
                @php
                    $ruta = is_array($prueba) ? ($prueba['path'] ?? '') : (string) $prueba;
                    $nombre = is_array($prueba) ? ($prueba['name'] ?? basename($ruta)) : basename($ruta);
                @endphp
                <div class="imv-file">
                    <span>{{ $nombre }}</span>
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($ruta) }}" target="_blank" rel="noopener" class="rit-btn rit-btn-secondary" style="padding:.3rem .8rem">Abrir</a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ═══ Análisis con IA ═══ --}}
    @include('filament.modals.partials.impugnacion-ia', ['impugnacion' => $impugnacion])

    {{-- ═══ Resolución (si ya existe) ═══ --}}
    @if ($impugnacion->decision_final)
        <p class="imv-sec">Decisión final</p>
        <div class="imv-stat">
            <p class="imv-v">{{ ['confirma_sancion' => 'Sanción confirmada', 'revoca_sancion' => 'Sanción revocada', 'modifica_sancion' => 'Sanción modificada'][$impugnacion->decision_final] ?? $impugnacion->decision_final }}</p>
            @if ($impugnacion->fundamento_decision)<p class="imv-texto" style="margin-top:.5rem;font-size:.85rem;line-height:1.7">{{ $impugnacion->fundamento_decision }}</p>@endif
        </div>
    @endif
</div>
