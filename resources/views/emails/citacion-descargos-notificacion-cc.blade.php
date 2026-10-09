@extends('emails.layouts.lupe', [
    'preheader' => "Se citó a {$trabajador->nombre_completo} a descargos",
    'badge' => 'Copia informativa',
    'titulo' => 'Citación a descargos',
    'subtitulo' => "{$empresa->razon_social} · Proceso {$proceso->codigo}",
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0;color:#4b5563;">
        Le informamos que se citó formalmente a <strong>{{ $trabajador->nombre_completo }}</strong> ({{ $trabajador->cargo }})
        a una audiencia de descargos. Este correo es solo informativo: el trabajador ya recibió la citación formal con el
        enlace y las instrucciones para participar.
    </p>

    @include('emails.partials.datos', ['filas' => [
        'Trabajador' => $trabajador->nombre_completo,
        'Cargo' => $trabajador->cargo,
        'Código del proceso' => $proceso->codigo,
        'Fecha de la audiencia' => $proceso->fecha_descargos_programada
            ? \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY')
            : null,
    ]])

    <p style="margin:0;color:#4b5563;font-size:14px;">Adjuntamos el documento de citación enviado al trabajador para su conocimiento.</p>

    <p style="margin:18px 0 0;font-size:14px;color:#4b5563;">Atentamente,<br><strong>{{ $empresa->razon_social }}</strong><br><span style="color:#6b7280;">Área de Recursos Humanos</span></p>
    <p style="margin:18px 0 0;font-size:11px;line-height:1.6;color:#9ca3af;">Correo automático del sistema de gestión de procesos disciplinarios. Por favor no responda; use los canales oficiales de la empresa.</p>
@endsection
