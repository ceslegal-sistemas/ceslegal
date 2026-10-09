<div style="display:flex;flex-direction:column;gap:.75rem;align-items:center;">
    <img src="{{ route('trabajador.foto-aceptacion-rit', ['trabajador' => $aceptacion->trabajador_id, 'aceptacion' => $aceptacion->id]) }}"
        alt="Selfie de verificación" style="max-width:100%;max-height:60vh;border-radius:.75rem;">
    <p style="margin:0;font-size:.8rem;color:#78716c;text-align:center;">
        Capturada el {{ $aceptacion->aceptado_en?->format('d/m/Y H:i') }}
        @if($aceptacion->ip_aceptacion) desde la IP {{ $aceptacion->ip_aceptacion }} @endif
    </p>
</div>
