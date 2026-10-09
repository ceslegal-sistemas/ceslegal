@extends('emails.layouts.lupe', [
    'preheader' => "{$trabajador->nombre_completo} presentó una impugnación en el proceso {$proceso->codigo}",
    'badge' => 'Requiere su atención',
    'titulo' => 'El trabajador presentó una impugnación',
    'subtitulo' => "{$trabajador->nombre_completo} impugnó la decisión del proceso {$proceso->codigo}. Ya quedó registrada en la plataforma.",
    'cta' => ['label' => 'Ver la impugnación', 'url' => $urlPanel],
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0;color:#4b5563;">
        El trabajador usó el enlace personal de impugnación y verificó su identidad con un código enviado a su correo.
        El proceso pasó a estado <strong>Impugnación realizada</strong> y el equipo de LUPE Legal revisará los motivos.
    </p>

    @include('emails.partials.datos', ['filas' => [
        'Radicado' => $impugnacion->radicado(),
        'Trabajador' => $trabajador->nombre_completo,
        'Cargo' => $trabajador->cargo,
        'Proceso' => $proceso->codigo,
        'Presentada' => $impugnacion->fecha_impugnacion?->timezone('America/Bogota')->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm a'),
        'Dirección IP' => $impugnacion->ip_registro,
    ]])

    @include('emails.partials.aviso', [
        'titulo' => 'No tiene que hacer nada en el panel para registrarla',
        'tono' => 'verde',
        'texto' => 'La impugnación la presentó el propio trabajador; usted solo puede consultarla. Le avisaremos cuando exista una resolución.',
    ])
@endsection
