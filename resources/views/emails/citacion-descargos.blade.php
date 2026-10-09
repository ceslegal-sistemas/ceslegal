@php
    $fecha = $proceso->fecha_descargos_programada
        ? \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY')
        : null;
    $fechaCorta = $proceso->fecha_descargos_programada
        ? \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('D [de] MMMM')
        : 'fecha indicada';
    $hora = $proceso->hora_descargos_programada ? \Carbon\Carbon::parse($proceso->hora_descargos_programada)->format('H:i') . ' hrs' : null;
    $evidenciasEmpleador = $evidenciasEmpleador ?? [];
    $cta = !empty($linkDescargos) ? ['label' => 'Acceder al formulario de descargos', 'url' => $linkDescargos] : null;
@endphp
@extends('emails.layouts.lupe', [
    'preheader' => 'Fue citado(a) a una audiencia de descargos' . ($fecha ? ": {$fecha}" : ''),
    'badge' => 'Acción requerida de su parte',
    'titulo' => 'Citación a audiencia de descargos',
    'subtitulo' => "{$empresa->razon_social} · Proceso {$proceso->codigo}",
    'cta' => $cta,
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0 0 4px;">Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>
    <p style="margin:0;color:#4b5563;">Usted fue citado(a) formalmente a una audiencia de descargos. Aquí están la fecha, la hora y el enlace para participar.</p>

    @if ($fecha)
        {{-- Fecha y hora: tarjeta protagonista --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0;">
            <tr>
                <td align="center" bgcolor="#fff1f2" class="lupe-code" style="background-color:#fff1f2;border-radius:18px;padding:24px 14px;font-family:Arial,Helvetica,sans-serif;">
                    <span style="display:block;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#be123c;">Fecha y hora de su audiencia</span>
                    <span style="display:block;margin-top:8px;font-size:22px;font-weight:800;color:#1f2937;text-transform:capitalize;">{{ $fecha }}</span>
                    @if ($hora)<span style="display:block;margin-top:4px;font-size:30px;font-weight:800;color:#be123c;">{{ $hora }}</span>@endif
                    <span style="display:block;margin-top:8px;font-size:13px;color:#6b7280;">Modalidad: <strong>{{ ucfirst($proceso->modalidad_descargos ?? 'Presencial') }}</strong></span>
                </td>
            </tr>
        </table>
    @endif

    @if ($cta)
        @include('emails.partials.aviso', [
            'titulo' => 'Su enlace de descargos',
            'tono' => 'ambar',
            'texto' => 'El <strong>día de la audiencia</strong> ingrese con el botón de arriba para presentar sus descargos en línea. Guárdelo ahora: solo funciona ese día.',
        ])
    @endif

    <p style="margin:18px 0 0;font-weight:700;color:#1f2937;">Cómo participar</p>
    @include('emails.partials.pasos', ['pasos' => [
        'Lea el <strong>documento adjunto</strong> a este correo para conocer los cargos y sus derechos.',
        'El día de la audiencia (<strong>' . e($fechaCorta) . '</strong>) haga clic en el botón «Acceder al formulario de descargos».',
        'Complete el formulario en línea y envíe sus descargos.',
    ]])

    @if (!empty($evidenciasEmpleador))
        {{-- Derecho de contradicción: el trabajador debe saber qué pruebas existen en su contra antes de la
             diligencia. No se adjuntan (peso/reenvío): se enumeran y se abren desde el enlace seguro (con código). --}}
        @include('emails.partials.aviso', [
            'titulo' => 'Pruebas aportadas por la empresa (' . count($evidenciasEmpleador) . ')',
            'tono' => 'azul',
            'texto' => '<ul style="margin:6px 0 0;padding-left:18px;">' . collect($evidenciasEmpleador)->map(fn ($n) => '<li style="margin-bottom:3px;">' . e($n) . '</li>')->implode('') . '</ul><p style="margin:8px 0 0;">Puede consultarlas desde el enlace de esta citación, junto con los hechos del proceso, antes de presentar sus descargos.</p>',
        ])
    @endif

    @include('emails.partials.datos', ['filas' => [
        'Empresa' => $empresa->razon_social,
        'Código del proceso' => $proceso->codigo,
        'Su cargo' => $trabajador->cargo,
    ]])

    @include('emails.partials.aviso', [
        'titulo' => 'Importante',
        'tono' => 'rosa',
        'texto' => '<ul style="margin:4px 0 0;padding-left:18px;"><li>Su asistencia a esta audiencia es <strong>obligatoria</strong>.</li><li>Si no se presenta ni responde el formulario, el proceso continuará sin su participación.</li><li>Tiene derecho a presentar pruebas y argumentos en su defensa.</li></ul>',
    ])

    <p style="margin:0;font-size:14px;color:#4b5563;">Si tiene preguntas, comuníquese con el área de Recursos Humanos de <strong>{{ $empresa->razon_social }}</strong> antes de la fecha programada.</p>
    <p style="margin:16px 0 0;font-size:14px;color:#4b5563;">Atentamente,<br><strong>{{ $empresa->razon_social }}</strong><br><span style="color:#6b7280;">Área de Recursos Humanos</span></p>
    <p style="margin:18px 0 0;font-size:11px;line-height:1.6;color:#9ca3af;">Correo automático del sistema de gestión de procesos disciplinarios. Por favor no responda; use los canales oficiales de la empresa.</p>

    @if (isset($trackingToken))
        <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}" width="1" height="1"
             style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;" alt="" />
    @endif
@endsection
