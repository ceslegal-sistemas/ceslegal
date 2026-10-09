@extends('emails.layouts.lupe', [
    'preheader' => "{$trabajador->nombre_completo} no se presentó a la diligencia de descargos",
    'badge' => 'Aviso importante',
    'titulo' => 'El trabajador no se presentó a descargos',
    'subtitulo' => "Proceso disciplinario {$proceso->codigo}",
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0 0 4px;">Estimado(a) <strong>{{ $cliente->name }}</strong>,</p>
    <p style="margin:0;color:#4b5563;">
        El trabajador citado a la diligencia de descargos del proceso <strong>{{ $proceso->codigo }}</strong>
        <strong>no se presentó</strong> en la fecha y hora programadas.
    </p>

    @include('emails.partials.datos', ['filas' => [
        'Trabajador' => $trabajador->nombre_completo,
        'Documento' => trim($trabajador->tipo_documento . ' ' . $trabajador->numero_documento),
        'Cargo' => $trabajador->cargo,
    ]])

    @include('emails.partials.aviso', [
        'titulo' => 'Estado del proceso',
        'tono' => 'rosa',
        'texto' => 'El proceso <strong>' . e($proceso->codigo) . '</strong> pasó al estado <strong>«Descargos no realizados»</strong>: el trabajador no ejerció su derecho a presentar descargos dentro del plazo establecido.',
    ])

    @include('emails.partials.datos', ['filas' => [
        'Fecha programada' => \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'),
        'Hora programada' => $proceso->hora_descargos_programada ? \Carbon\Carbon::parse($proceso->hora_descargos_programada)->format('h:i A') : null,
        'Modalidad' => ucfirst($proceso->modalidad_descargos ?? 'Presencial'),
    ]])

    <p style="margin:18px 0 0;font-weight:700;color:#1f2937;">¿Qué sigue ahora?</p>
    @include('emails.partials.pasos', ['pasos' => [
        'El abogado asignado al caso evaluará las pruebas y evidencias disponibles.',
        'Se procederá a emitir la sanción correspondiente con base en los hechos documentados.',
        'El trabajador será notificado de la decisión tomada.',
    ]])

    @include('emails.partials.aviso', [
        'titulo' => 'Nota',
        'tono' => 'azul',
        'texto' => 'La no asistencia del trabajador a los descargos no impide que el proceso continúe ni que se aplique la sanción que corresponda.',
    ])

    <p style="margin:0;font-size:14px;color:#4b5563;">Puede consultar el detalle y el avance del proceso en LUPE Legal. Si tiene preguntas, no dude en contactarnos.</p>
    <p style="margin:16px 0 0;font-size:14px;color:#4b5563;">Atentamente,<br><strong>Equipo LUPE Legal</strong><br><span style="color:#6b7280;">Gestión de procesos disciplinarios</span></p>

    @if (isset($trackingToken))
        <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}" width="1" height="1"
             style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;" alt="" />
    @endif
@endsection
