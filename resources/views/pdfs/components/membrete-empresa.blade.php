@php
    $logoBase64 = null;
    if ($empresa->logo_path) {
        $rutaAbsoluta = \Illuminate\Support\Facades\Storage::disk('local')->path($empresa->logo_path);
        if (is_file($rutaAbsoluta)) {
            $mime = mime_content_type($rutaAbsoluta) ?: 'image/png';
            $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($rutaAbsoluta));
        }
    }

    $colorAcento = $empresa->logo_color_acento ?? '#3A3A3A';
    $tieneContacto = $empresa->direccion || $empresa->telefono || $empresa->email_contacto;
@endphp
@if($logoBase64)
<div style="position: fixed; top: 1.2cm; left: 1.5cm; z-index: 10;">
    <img src="{{ $logoBase64 }}" style="height: 2.2cm; width: auto;">
</div>

<div style="position: fixed; top: 0; left: 0.6cm; width: 0.35cm; height: 100%; background: {{ $colorAcento }}; z-index: 5;"></div>

<div style="position: fixed; top: 35%; left: 20%; width: 60%; opacity: 0.09; z-index: 1;">
    <img src="{{ $logoBase64 }}" style="width: 100%; height: auto;">
</div>

@if($tieneContacto)
<div class="membrete-pie" style="position: fixed; bottom: 0.8cm; left: 0; width: 100%; text-align: center; font-size: 7.5pt; color: #555555;">
    {{ collect([$empresa->direccion, $empresa->telefono, $empresa->email_contacto])->filter()->implode(' · ') }}
</div>
@endif
@endif
