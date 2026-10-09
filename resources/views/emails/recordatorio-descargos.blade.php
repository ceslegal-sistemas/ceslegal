@php
    $fecha = $proceso->fecha_descargos_programada
        ? \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY')
        : null;
    $hora = $proceso->hora_descargos_programada ? \Carbon\Carbon::parse($proceso->hora_descargos_programada)->format('H:i') . ' hrs' : null;
    $cta = !empty($linkDescargos) ? ['label' => 'Acceder al formulario de descargos', 'url' => $linkDescargos] : null;
@endphp
@extends('emails.layouts.lupe', [
    'preheader' => 'Mañana es su audiencia de descargos' . ($hora ? " a las {$hora}" : ''),
    'badge' => 'Recordatorio · acción requerida mañana',
    'titulo' => 'Su audiencia es mañana',
    'subtitulo' => "{$empresa->razon_social} · Proceso {$proceso->codigo}",
    'cta' => $cta,
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0 0 4px;">Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>
    <p style="margin:0;color:#4b5563;">Le recordamos que <strong>mañana</strong> tiene programada su audiencia de descargos. Lea este correo completo.</p>

    @if ($fecha)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0;">
            <tr>
                <td align="center" bgcolor="#fff1f2" class="lupe-code" style="background-color:#fff1f2;border-radius:18px;padding:24px 14px;font-family:Arial,Helvetica,sans-serif;">
                    <span style="display:block;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#be123c;">Fecha y hora</span>
                    <span style="display:block;margin-top:8px;font-size:22px;font-weight:800;color:#1f2937;text-transform:capitalize;">{{ $fecha }}</span>
                    @if ($hora)<span style="display:block;margin-top:4px;font-size:30px;font-weight:800;color:#be123c;">{{ $hora }}</span>@endif
                    <span style="display:block;margin-top:8px;font-size:13px;color:#6b7280;">Modalidad: <strong>{{ ucfirst($proceso->modalidad_descargos ?? 'Presencial') }}</strong></span>
                </td>
            </tr>
        </table>
    @endif

    @if ($cta)
        @include('emails.partials.aviso', [
            'titulo' => 'Su enlace para mañana',
            'tono' => 'ambar',
            'texto' => 'Mañana, a la hora indicada, ingrese con el botón de arriba al formulario. <strong>Este enlace solo funciona el día de la audiencia.</strong>',
        ])
    @endif

    <p style="margin:18px 0 0;font-weight:700;color:#1f2937;">Prepárese para mañana</p>
    @include('emails.partials.pasos', ['pasos' => [
        'Tenga a la mano este correo con el botón de acceso.',
        'Prepare los documentos o evidencias que quiera presentar.',
        'Asegúrese de tener una conexión a internet estable.',
        'Tenga disponibilidad para completar el formulario durante la audiencia.',
    ]])

    @include('emails.partials.datos', ['filas' => [
        'Empresa' => $empresa->razon_social,
        'Código del proceso' => $proceso->codigo,
        'Su cargo' => $trabajador->cargo,
    ]])

    @include('emails.partials.aviso', [
        'titulo' => 'Importante',
        'tono' => 'rosa',
        'texto' => '<ul style="margin:4px 0 0;padding-left:18px;"><li>Su asistencia es <strong>obligatoria</strong>.</li><li>Si no se presenta ni responde el formulario, el proceso continuará sin su participación.</li><li>Tiene derecho a presentar pruebas y argumentos en su defensa.</li></ul>',
    ])

    <p style="margin:0;font-size:14px;color:#4b5563;">Si tiene preguntas, comuníquese con Recursos Humanos de <strong>{{ $empresa->razon_social }}</strong> <strong>hoy</strong>, antes de la diligencia.</p>
    <p style="margin:16px 0 0;font-size:14px;color:#4b5563;">Atentamente,<br><strong>{{ $empresa->razon_social }}</strong><br><span style="color:#6b7280;">Área de Recursos Humanos</span></p>
    <p style="margin:18px 0 0;font-size:11px;line-height:1.6;color:#9ca3af;">Correo automático. Por favor no responda; use los canales oficiales de la empresa.</p>

    @if (isset($trackingToken))
        <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}" width="1" height="1"
             style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;" alt="" />
    @endif
@endsection
