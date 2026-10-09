@extends('emails.layouts.lupe', [
    'preheader' => "{$trabajador->nombre_completo} presentó una impugnación en el proceso {$proceso->codigo}",
    'badge' => 'Requiere su atención',
    'titulo' => 'El trabajador presentó una impugnación',
    'subtitulo' => "{$trabajador->nombre_completo} impugnó la decisión del proceso {$proceso->codigo}. Ya quedó registrada en la plataforma.",
    'cta' => ['label' => 'Revisar la impugnación', 'url' => $urlPanel],
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0;color:#4b5563;">
        El trabajador usó el enlace personal de impugnación y verificó su identidad con un código enviado a su correo.
        El proceso pasó a estado <strong>Impugnación realizada</strong> y queda a la espera de su revisión: usted debe ingresar a
        la plataforma para leer los motivos y las pruebas y tomar la decisión final.
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
        'titulo' => 'Qué debe hacer ahora',
        'tono' => 'ambar',
        'texto' => 'Ingrese, abra <strong>Ver impugnación</strong> y revise lo que alegó el trabajador. LUPE Legal ya prepara un <strong>análisis con IA</strong> con una sugerencia de decisión (confirmar, revocar o modificar) y un fundamento listo para usar, pero <strong>la decisión final es de su empresa</strong>. No hay que registrar nada: el trabajador la presentó él mismo.',
    ])
@endsection
