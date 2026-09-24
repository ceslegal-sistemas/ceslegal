{{--
    Header con el logo REAL de la empresa (nunca el de LUPE Legal) para
    correos HTML - a diferencia de resources/views/pdfs/components/
    membrete-empresa.blade.php (que usa position:fixed calibrado para
    margenes de pagina de Dompdf y NO es compatible con clientes de
    correo), este partial usa flujo normal de documento, seguro para
    Gmail/Outlook/etc. Si la empresa no tiene logo cargado, no muestra nada
    (mismo comportamiento del membrete de PDF).
--}}
@php
    $logoBase64 = null;
    if ($empresa->logo_path) {
        $rutaAbsoluta = \Illuminate\Support\Facades\Storage::disk('local')->path($empresa->logo_path);
        if (is_file($rutaAbsoluta)) {
            $mime = mime_content_type($rutaAbsoluta) ?: 'image/png';
            $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($rutaAbsoluta));
        }
    }
@endphp
@if($logoBase64)
<div style="text-align:center; padding:20px 20px 0 20px;">
    <img src="{{ $logoBase64 }}" alt="{{ $empresa->razon_social }}" style="max-height:60px; max-width:220px;">
</div>
@endif
