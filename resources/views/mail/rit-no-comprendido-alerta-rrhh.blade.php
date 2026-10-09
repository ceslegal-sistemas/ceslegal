@extends('emails.layouts.lupe', [
    'preheader' => "{$nombreTrabajador} no comprende el Reglamento Interno",
    'badge' => 'Alerta de Recursos Humanos',
    'titulo' => 'Un trabajador no comprende el Reglamento',
    'subtitulo' => 'La socialización quedó bloqueada y requiere su decisión.',
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0;color:#4b5563;">
        El trabajador <strong>{{ $nombreTrabajador }}</strong> (documento <strong>{{ $documentoTrabajador }}</strong>) manifestó, en dos
        oportunidades, no comprender el Reglamento Interno de Trabajo de <strong>{{ $empresa?->razon_social }}</strong>, a pesar de
        habérsele presentado un video explicativo, un resumen en lenguaje sencillo y el documento completo con los cambios resaltados.
    </p>

    @include('emails.partials.aviso', [
        'titulo' => 'Proceso de socialización bloqueado',
        'tono' => 'ambar',
        'texto' => 'El sistema no pudo continuar con el registro de socialización de este trabajador. Les corresponde a ustedes, como área de Recursos Humanos, decidir los siguientes pasos.',
    ])
@endsection
