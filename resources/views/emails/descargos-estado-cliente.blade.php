@php $realizados = $estado === 'descargos_realizados'; @endphp
@extends('emails.layouts.lupe', [
    'preheader' => $realizados ? "{$trabajador->nombre_completo} completó sus descargos" : "{$trabajador->nombre_completo} no presentó descargos",
    'badge' => 'Requiere su atención',
    'titulo' => $realizados ? 'El trabajador completó sus descargos' : 'El trabajador no presentó descargos',
    'subtitulo' => "Proceso disciplinario {$proceso->codigo}",
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0 0 4px;">Estimado(a) <strong>{{ $cliente->name }}</strong>,</p>

    @if ($realizados)
        <p style="margin:0;color:#4b5563;">El trabajador <strong>{{ $trabajador->nombre_completo }}</strong> completó la diligencia de descargos del proceso <strong>{{ $proceso->codigo }}</strong>.</p>
        @include('emails.partials.aviso', ['titulo' => 'Descargos completados', 'tono' => 'verde', 'texto' => 'El trabajador presentó sus respuestas y documentación de soporte en el sistema.'])

        <p style="margin:18px 0 0;font-weight:700;color:#1f2937;">Qué debe hacer ahora</p>
        @include('emails.partials.pasos', ['pasos' => [
            '<strong>Revisar las respuestas</strong> que dio el trabajador.',
            '<strong>Analizar las evidencias</strong> que aportó como parte de su defensa.',
            '<strong>Emitir la sanción correspondiente</strong> según la gravedad de los hechos y las respuestas recibidas.',
        ]])
        <p style="margin:0;font-size:14px;color:#4b5563;">Puede acceder al proceso desde el panel para revisar toda la información.</p>
    @else
        <p style="margin:0;color:#4b5563;">El trabajador <strong>{{ $trabajador->nombre_completo }}</strong> <strong>no asistió ni presentó descargos</strong> en el proceso <strong>{{ $proceso->codigo }}</strong>.</p>
        @include('emails.partials.aviso', ['titulo' => 'Descargos no realizados', 'tono' => 'ambar', 'texto' => 'El plazo para presentar descargos venció sin respuesta del trabajador.'])
        @include('emails.partials.aviso', [
            'titulo' => 'Implicaciones',
            'tono' => 'rosa',
            'texto' => 'Al no presentar descargos, el trabajador renunció a su derecho de defensa en esta etapa y el proceso puede continuar con la información y evidencias existentes.',
        ])
        @include('emails.partials.aviso', [
            'titulo' => 'Siguiente paso',
            'tono' => 'azul',
            'texto' => 'Puede <strong>emitir la sanción correspondiente</strong>: el trabajador tuvo la oportunidad de defenderse pero no la ejerció.',
        ])
    @endif

    @include('emails.partials.datos', ['filas' => [
        'Código del proceso' => $proceso->codigo,
        'Trabajador' => $trabajador->nombre_completo,
        'Cargo' => $trabajador->cargo,
        'Empresa' => $empresa->razon_social,
        'Fecha programada de descargos' => $proceso->fecha_descargos_programada
            ? \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('D [de] MMMM [de] YYYY [a las] h:mm A')
            : null,
    ]])

    <p style="margin:18px 0 0;font-size:11px;line-height:1.6;color:#9ca3af;">Correo automático del sistema de gestión de procesos disciplinarios. Para más información, ingrese al panel.</p>

    @if (isset($trackingToken))
        <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}" width="1" height="1"
             style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;" alt="" />
    @endif
@endsection
