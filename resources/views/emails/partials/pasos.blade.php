{{--
    Pasos numerados (círculo en degradado de marca, sin barras verticales).
    @include('emails.partials.pasos', ['pasos' => ['HTML del paso 1', 'HTML del paso 2', ...]])
    Los textos se imprimen sin escapar: el llamador escapa lo dinámico con e().
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;">
    @foreach ($pasos as $i => $paso)
        <tr>
            <td width="40" valign="top" style="padding:6px 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="30" height="30" align="center" bgcolor="#e11d48" style="width:30px;height:30px;border-radius:15px;background-color:#e11d48;background-image:linear-gradient(135deg,#e11d48,#f97316);font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#ffffff;line-height:30px;">{{ $i + 1 }}</td>
                    </tr>
                </table>
            </td>
            <td valign="top" class="lupe-paso" style="padding:6px 0 6px 4px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#374151;">{!! $paso !!}</td>
        </tr>
    @endforeach
</table>
