<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de proceso disciplinario sin sanción</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #16a34a;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }

        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }

        .info-box {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #16a34a;
            border-radius: 4px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
        }

        h2 {
            color: #1f2937;
            margin-top: 0;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0;">Proceso disciplinario cerrado sin sanción</h1>
    </div>

    <div class="content">
        <p>Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>

        <p>Le informamos que el proceso disciplinario <strong>{{ $proceso->codigo }}</strong> ha sido
            cerrado. Tras analizar los hechos y sus descargos, la empresa decidió
            <strong>no aplicarle ninguna sanción</strong>.</p>

        <div class="info-box">
            <h2>Información del Proceso</h2>
            <p><strong>Empresa:</strong> {{ $empresa->razon_social }}</p>
            <p><strong>Código del Proceso:</strong> {{ $proceso->codigo }}</p>
            <p><strong>Su cargo:</strong> {{ $trabajador->cargo }}</p>
            <p><strong>Resultado:</strong> Sin sanción</p>
        </div>

        <div class="info-box">
            <h2>Documento Adjunto</h2>
            <p>Encontrará adjunta la constancia oficial de cierre del proceso sin sanción. Este
                documento ha sido redactado en lenguaje claro para facilitar su comprensión.</p>
            <p>En el documento encontrará:</p>
            <ul>
                <li>Los hechos que se analizaron</li>
                <li>El análisis de sus descargos</li>
                <li>La decisión de no aplicar sanción y sus razones</li>
                <li>La constancia de que se respetó su derecho de defensa</li>
            </ul>
        </div>

        <div class="info-box" style="background-color: #dbeafe; border-left-color: #3b82f6;">
            <h2>Información de Contacto</h2>
            <p>Si tiene preguntas o requiere aclaraciones sobre esta comunicación, puede contactarnos a través de:</p>
            <p><strong>Área de Recursos Humanos</strong><br>
                {{ $empresa->razon_social }}</p>
        </div>

        <p>Atentamente,</p>
        <p><strong>{{ $empresa->razon_social }}</strong><br>
            Área de Recursos Humanos</p>
    </div>

    <div class="footer">
        <p>Este es un correo electrónico automático generado por el sistema de gestión de procesos disciplinarios.</p>
        <p>Por favor, no responda a este correo. Para comunicarse, utilice los canales oficiales de la empresa.</p>
    </div>

    {{-- Pixel de seguimiento de apertura (invisible) --}}
    @if(isset($trackingToken))
    <img src="{{ route('email.tracking.pixel', ['token' => $trackingToken]) }}"
         width="1" height="1"
         style="display:block !important; width:1px !important; height:1px !important; border:0 !important; margin:0 !important; padding:0 !important;"
         alt="" />
    @endif
</body>

</html>
