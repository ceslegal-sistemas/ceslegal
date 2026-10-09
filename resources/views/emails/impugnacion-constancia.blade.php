@php
    $limiteTxt = $proceso->limiteImpugnacion()?->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
    $tipoSancion = match ($proceso->tipo_sancion) {
        'llamado_atencion' => 'Llamado de atención',
        'suspension' => 'Suspensión laboral' . ($proceso->dias_suspension ? " ({$proceso->dias_suspension} días)" : ''),
        'multa' => 'Multa',
        'terminacion' => 'Terminación de contrato',
        default => 'Sanción disciplinaria',
    };
    $adjuntos = collect($impugnacion->pruebas_adicionales ?? [])->count();
@endphp
@extends('emails.layouts.lupe', [
    'preheader' => "Su impugnación quedó radicada con el número {$impugnacion->radicado()}",
    'badge' => 'Impugnación recibida',
    'titulo' => 'Recibimos su impugnación',
    'subtitulo' => "Quedó radicada con el número {$impugnacion->radicado()}. Su empresa la analizará y recibirá la decisión final por este mismo medio.",
    'empresa' => $empresa,
])

@section('contenido')
    <p style="margin:0 0 4px;">Hola, <strong>{{ $trabajador->nombre_completo }}</strong>.</p>
    <p style="margin:0;color:#4b5563;">
        Este correo es la constancia de que usted presentó una impugnación contra la decisión adoptada en el proceso
        disciplinario <strong>{{ $proceso->codigo }}</strong>. Guárdelo como soporte.
    </p>

    @include('emails.partials.datos', ['filas' => [
        'Número de radicado' => $impugnacion->radicado(),
        'Fecha y hora' => $impugnacion->fecha_impugnacion?->timezone('America/Bogota')->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm a'),
        'Decisión impugnada' => $tipoSancion,
        'Proceso' => $proceso->codigo,
        'Pruebas adjuntas' => $adjuntos > 0 ? $adjuntos . ' archivo(s)' : null,
    ]])

    <p style="margin:18px 0 6px;font-weight:700;color:#1f2937;">Sus motivos</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td bgcolor="#f9fafb" class="lupe-caja" style="background-color:#f9fafb;border-radius:14px;padding:16px 18px;font-size:14px;line-height:1.65;color:#374151;white-space:pre-wrap;">{{ $impugnacion->motivos_impugnacion }}</td>
        </tr>
    </table>

    @include('emails.partials.aviso', [
        'titulo' => 'Qué sigue',
        'tono' => 'azul',
        'texto' => 'Su empresa analizará su impugnación y le comunicará la decisión final por correo electrónico. No necesita hacer nada más.',
    ])
@endsection
