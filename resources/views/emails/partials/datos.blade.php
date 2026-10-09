{{--
    Tarjeta de datos clave/valor (sin barras verticales): fondo tintado de marca,
    esquinas redondeadas, etiquetas en mayúsculas pequeñas.
    @include('emails.partials.datos', ['filas' => ['Etiqueta' => 'Valor', ...]])
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;">
    <tr>
        <td bgcolor="#fff1f2" style="background-color:#fff1f2;border-radius:16px;padding:6px 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                @foreach ($filas as $etiqueta => $valor)
                    @continue(blank($valor))
                    <tr>
                        <td style="padding:11px 0;{{ $loop->last ? '' : 'border-bottom:1px solid #fde2e7;' }}font-family:Arial,Helvetica,sans-serif;">
                            <span style="display:block;font-size:11px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#be123c;">{{ $etiqueta }}</span>
                            <span style="display:block;margin-top:2px;font-size:15px;font-weight:600;color:#1f2937;">{{ $valor }}</span>
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
