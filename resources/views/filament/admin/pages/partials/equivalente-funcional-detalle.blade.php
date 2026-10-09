{{-- Detalle de un evento de trazabilidad: evidencia completa en el modal nativo de Filament. --}}
<div style="display:flex;flex-direction:column;gap:1rem">
    @if($selfie)
        <img src="{{ $selfie }}" alt="Selfie de verificación" style="max-width:100%;max-height:60vh;border-radius:.75rem;margin:0 auto;display:block">
    @endif

    <dl style="display:grid;grid-template-columns:minmax(9rem,auto) 1fr;gap:.6rem 1rem;font-size:.875rem;margin:0">
        <dt style="opacity:.65">Fecha</dt><dd style="margin:0">{{ $evento->fecha?->format('d/m/Y H:i') }}</dd>
        <dt style="opacity:.65">Proceso</dt><dd style="margin:0">{{ $evento->proceso_clave }}@if($evento->proceso_extra) ({{ $evento->proceso_extra }})@endif</dd>
        <dt style="opacity:.65">Quién</dt><dd style="margin:0">{{ $evento->actor_nombre ?: 'Sin registro' }}@if($evento->actor_cargo) · {{ $evento->actor_cargo }}@endif</dd>
        @if($evento->sujeto_nombre && $evento->sujeto_nombre !== $evento->actor_nombre)
            <dt style="opacity:.65">Trabajador</dt><dd style="margin:0">{{ $evento->sujeto_nombre }}@if($evento->sujeto_documento) · {{ $evento->sujeto_documento }}@endif</dd>
        @endif
        <dt style="opacity:.65">Empresa</dt><dd style="margin:0">{{ $evento->empresa_nombre ?: '-' }}</dd>
        <dt style="opacity:.65">Dirección IP</dt><dd style="margin:0;font-family:ui-monospace,monospace">{{ $evento->ip ?: 'Sin registro' }}</dd>
        @foreach($detalle as $etiqueta => $valor)
            <dt style="opacity:.65">{{ $etiqueta }}</dt><dd style="margin:0;word-break:break-word">{{ $valor }}</dd>
        @endforeach
    </dl>
</div>
