{{--
    Aviso destacado con tarjeta tintada (sin barra vertical).
    @include('emails.partials.aviso', ['titulo' => '...', 'texto' => '...', 'tono' => 'ambar|rosa|verde|azul'])
--}}
@php
    $paleta = [
        'ambar' => ['#fffbeb', '#fde68a', '#b45309', '#78350f'],
        'rosa'  => ['#fff1f2', '#fecdd3', '#be123c', '#4c0519'],
        'verde' => ['#f0fdf4', '#bbf7d0', '#15803d', '#14532d'],
        'azul'  => ['#eff6ff', '#bfdbfe', '#1d4ed8', '#1e3a8a'],
    ][$tono ?? 'ambar'];
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;">
    <tr>
        <td bgcolor="{{ $paleta[0] }}" class="lupe-aviso-{{ $tono ?? 'ambar' }}" style="background-color:{{ $paleta[0] }};border:1px solid {{ $paleta[1] }};border-radius:16px;padding:18px 20px;font-family:Arial,Helvetica,sans-serif;">
            <span class="lupe-aviso-t" style="display:inline-block;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:{{ $paleta[2] }};">{{ $titulo }}</span>
            <div class="lupe-aviso-x" style="margin-top:6px;font-size:14px;line-height:1.6;color:{{ $paleta[3] }};">{!! $texto !!}</div>
        </td>
    </tr>
</table>
