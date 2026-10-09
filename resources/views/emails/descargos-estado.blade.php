@php $realizados = $estado === 'descargos_realizados'; @endphp
@extends('emails.layouts.lupe', [
    'preheader' => $realizados ? 'Recibimos sus descargos' : 'El plazo para presentar sus descargos venció',
    'badge' => $realizados ? 'Descargos recibidos' : 'Plazo vencido',
    'titulo' => $realizados ? 'Recibimos sus descargos' : 'Descargos no realizados',
    'subtitulo' => "Proceso disciplinario {$proceso->codigo}",
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0 0 4px;">Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>

    @if ($realizados)
        <p style="margin:0;color:#4b5563;">Le informamos que recibimos correctamente sus descargos del proceso disciplinario <strong>{{ $proceso->codigo }}</strong>.</p>
        @include('emails.partials.aviso', ['titulo' => 'Descargos completados', 'tono' => 'verde', 'texto' => 'Sus respuestas y la documentación que aportó quedaron registradas exitosamente en el sistema.'])
        @include('emails.partials.aviso', ['titulo' => 'Qué sigue', 'tono' => 'azul', 'texto' => 'La empresa revisará la información que aportó y tomará la decisión correspondiente. Recibirá una notificación cuando se decida sobre su caso.'])
    @else
        <p style="margin:0;color:#4b5563;">Le informamos que el plazo para presentar sus descargos en el proceso <strong>{{ $proceso->codigo }}</strong> venció sin que recibiéramos su respuesta.</p>
        @include('emails.partials.aviso', ['titulo' => 'Descargos no realizados', 'tono' => 'ambar', 'texto' => 'No se registró su asistencia ni respuesta a la diligencia de descargos programada.'])
        @include('emails.partials.aviso', [
            'titulo' => 'Consecuencias',
            'tono' => 'rosa',
            'texto' => 'Según el procedimiento disciplinario, al no presentar descargos:<ul style="margin:6px 0 0;padding-left:18px;"><li>Se tomará una decisión con la información disponible.</li><li>Se considera que renunció a su derecho de defensa en esta etapa.</li><li>El proceso continúa su curso normal.</li></ul>',
        ])
    @endif

    @include('emails.partials.datos', ['filas' => [
        'Empresa' => $empresa->razon_social,
        'Código del proceso' => $proceso->codigo,
        'Su cargo' => $trabajador->cargo,
        'Fecha programada de descargos' => $proceso->fecha_descargos_programada
            ? \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
            : null,
    ]])

    <p style="margin:0;font-size:14px;color:#4b5563;">Si tiene preguntas, comuníquese con el área de Recursos Humanos de {{ $empresa->razon_social }}.</p>
    <p style="margin:16px 0 0;font-size:14px;color:#4b5563;">Atentamente,<br><strong>{{ $empresa->razon_social }}</strong><br><span style="color:#6b7280;">Área de Recursos Humanos</span></p>
    <p style="margin:18px 0 0;font-size:11px;line-height:1.6;color:#9ca3af;">Correo automático. Por favor no responda; use los canales oficiales de la empresa.</p>

    @if (isset($trackingToken))
        <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}" width="1" height="1"
             style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;" alt="" />
    @endif
@endsection
