@extends('emails.layouts.lupe', [
    'preheader' => 'Su código de verificación para presentar la impugnación',
    'badge' => 'Verificación de identidad',
    'titulo' => 'Confirme que es usted',
    'subtitulo' => 'Para presentar su impugnación necesitamos verificar su identidad con este código.',
])

@section('contenido')
    <p style="margin:0 0 6px;">Hola, <strong>{{ $nombreTrabajador }}</strong>.</p>
    <p style="margin:0;color:#4b5563;">
        Recibió este mensaje porque solicitó presentar una impugnación dentro del proceso
        <strong>{{ $procesoCodigo }}</strong>. Ingrese este código en la pantalla de verificación:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0 8px;">
        <tr>
            <td align="center" bgcolor="#fff1f2" style="background-color:#fff1f2;border-radius:18px;padding:26px 12px;">
                <span style="font-family:'Courier New',monospace;font-size:42px;font-weight:800;letter-spacing:14px;color:#be123c;padding-left:14px;">{{ $codigo }}</span>
                <div style="margin-top:10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;">Válido por <strong>10 minutos</strong></div>
            </td>
        </tr>
    </table>

    @include('emails.partials.aviso', [
        'titulo' => 'Importante',
        'tono' => 'ambar',
        'texto' => 'No comparta este código con nadie. Si usted no solicitó presentar una impugnación, ignore este mensaje: no se registrará nada.',
    ])
@endsection
