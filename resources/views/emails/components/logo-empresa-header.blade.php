{{--
    Header con el logo REAL de la empresa (nunca el de LUPE Legal) para
    correos HTML - a diferencia de resources/views/pdfs/components/
    membrete-empresa.blade.php (que usa position:fixed calibrado para
    margenes de pagina de Dompdf y NO es compatible con clientes de
    correo), este partial usa flujo normal de documento, seguro para
    Gmail/Outlook/etc. Si la empresa no tiene logo cargado, no muestra nada
    (mismo comportamiento del membrete de PDF).

    No se embebe en base64 (bug real 2026-09-25: Gmail no renderiza de forma
    confiable imágenes base64 en el cuerpo del correo, se ve como un icono de
    imagen rota) - se usa una URL firmada real que el cliente de correo
    busca por su cuenta, sin sesión de panel activa (ver ruta
    'logo-empresa.mostrar').
--}}
@if($empresa->logo_path)
<div style="text-align:center; padding:20px 20px 0 20px;">
    <img src="{{ \Illuminate\Support\Facades\URL::signedRoute('logo-empresa.mostrar', $empresa) }}" alt="{{ $empresa->razon_social }}" style="max-height:60px; max-width:220px;">
</div>
@endif
