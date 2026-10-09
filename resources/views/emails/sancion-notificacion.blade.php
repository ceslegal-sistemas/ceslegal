@php
    $limiteTxt = isset($limiteImpugnacion) && $limiteImpugnacion
        ? $limiteImpugnacion->copy()->timezone('America/Bogota')->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')
        : null;
    $cta = !empty($urlImpugnacion) ? ['label' => 'Presentar mi impugnación', 'url' => $urlImpugnacion] : null;
@endphp
@extends('emails.layouts.lupe', [
    'preheader' => "Decisión del proceso disciplinario {$proceso->codigo}",
    'badge' => 'Notificación oficial',
    'titulo' => "Notificación de {$tipoSancion}",
    'subtitulo' => "Proceso disciplinario {$proceso->codigo}. El documento oficial va adjunto a este correo.",
    'empresa' => $empresa,
    'cta' => null,
])

@section('contenido')
    <p style="margin:0 0 4px;">Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>
    <p style="margin:0;color:#4b5563;">
        Por medio del presente se le notifica la decisión tomada respecto al proceso disciplinario
        <strong>{{ $proceso->codigo }}</strong>. Lea con calma el documento adjunto: está redactado en lenguaje claro.
    </p>

    @include('emails.partials.datos', ['filas' => [
        'Empresa' => $empresa->razon_social,
        'Código del proceso' => $proceso->codigo,
        'Su cargo' => $trabajador->cargo,
        'Decisión' => $tipoSancion,
    ]])

    <p style="margin:18px 0 6px;font-weight:700;color:#1f2937;">En el documento encontrará</p>
    <ul style="margin:0;padding-left:20px;color:#4b5563;font-size:14px;line-height:1.8;">
        <li>Los hechos que motivaron la decisión</li>
        <li>El análisis de sus descargos</li>
        <li>La decisión y sus fundamentos</li>
        <li>Las consecuencias prácticas de la sanción</li>
        <li>Sus derechos de impugnación</li>
    </ul>

    {{-- ═══ Derecho de impugnación: tarjeta destacada con botón propio ═══ --}}
    @if ($cta)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 8px;">
            <tr>
                <td bgcolor="#1f1114" style="background-color:#1f1114;background-image:radial-gradient(circle at 100% 0%, rgba(225,29,72,0.5) 0%, rgba(225,29,72,0) 55%),linear-gradient(150deg,#1a0f0c 0%,#241319 100%);border-radius:18px;padding:24px 24px 26px;font-family:Arial,Helvetica,sans-serif;">
                    <span style="display:inline-block;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#fecdd3;background-color:#3a1620;border:1px solid #6b2236;border-radius:30px;padding:5px 12px;">Su derecho</span>
                    <p style="margin:12px 0 0;font-size:20px;font-weight:800;line-height:1.25;color:#ffffff;">¿No está de acuerdo? Puede impugnar esta decisión</p>
                    <p style="margin:8px 0 0;font-size:14px;line-height:1.6;color:#cbd5e1;">
                        Presente su impugnación usted mismo(a), en línea y sin trámites: verifique su identidad con un código,
                        cuéntenos sus motivos y adjunte pruebas.
                        @if ($limiteTxt) Tiene plazo hasta el <strong style="color:#ffffff;">{{ $limiteTxt }}</strong>. @endif
                    </p>
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;">
                        <tr>
                            <td bgcolor="#e11d48" style="border-radius:12px;background-color:#e11d48;background-image:linear-gradient(135deg,#e11d48,#f97316);">
                                <a href="{{ $cta['url'] }}" target="_blank" style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;">{{ $cta['label'] }} &rarr;</a>
                            </td>
                        </tr>
                    </table>
                    <p style="margin:16px 0 0;font-size:12px;line-height:1.6;color:#94a3b8;">
                        ¿El botón no funciona? Copie este enlace en su navegador:<br>
                        <a href="{{ $cta['url'] }}" target="_blank" style="color:#fda4af;word-break:break-all;">{{ $cta['url'] }}</a>
                    </p>
                </td>
            </tr>
        </table>
    @else
        @include('emails.partials.aviso', [
            'titulo' => 'Derecho a impugnación',
            'tono' => 'ambar',
            'texto' => 'Usted tiene derecho a presentar una impugnación si no está de acuerdo con esta decisión. Los detalles y plazos están en el documento adjunto.',
        ])
    @endif

    @include('emails.partials.aviso', [
        'titulo' => 'Contacto',
        'tono' => 'azul',
        'texto' => 'Si tiene preguntas sobre esta comunicación, comuníquese con el <strong>Área de Recursos Humanos</strong> de ' . e($empresa->razon_social) . '.',
    ])

    <p style="margin:22px 0 0;font-size:11px;line-height:1.6;color:#9ca3af;">
        Este es un correo automático del sistema de gestión de procesos disciplinarios. Por favor no responda a este mensaje; use los canales oficiales de la empresa.
    </p>

    {{-- Pixel de seguimiento de apertura (invisible) --}}
    @if (isset($trackingToken))
        <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}" width="1" height="1"
             style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;" alt="" />
    @endif
@endsection
