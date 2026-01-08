<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación de Sanción</title>
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
            background-color: #dc2626;
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
            border-left: 4px solid #dc2626;
            border-radius: 4px;
        }

        .important {
            background-color: #fef3c7;
            border-left-color: #f59e0b;
            padding: 15px;
            margin: 20px 0;
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

        .highlight {
            font-weight: bold;
            color: #dc2626;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0;">Notificación de {{ $tipoSancion }}</h1>
    </div>

    <div class="content">
        <p>Estimado(a) <strong>{{ $trabajador->nombre_completo }}</strong>,</p>

        <p>Por medio del presente, se le notifica la decisión tomada respecto al proceso disciplinario
            <strong>{{ $proceso->codigo }}</strong>.</p>

        <div class="info-box">
            <h2>Información del Proceso</h2>
            <p><strong>Empresa:</strong> {{ $empresa->razon_social }}</p>
            <p><strong>Código del Proceso:</strong> {{ $proceso->codigo }}</p>
            <p><strong>Su cargo:</strong> {{ $trabajador->cargo }}</p>
            <p><strong>Tipo de Sanción:</strong> {{ $tipoSancion }}</p>
        </div>

        <div class="info-box">
            <h2>Documento Adjunto</h2>
            <p>Encontrará adjunto a este correo el documento oficial con la decisión de sanción. Este documento ha sido
                redactado en lenguaje claro para facilitar su comprensión.</p>
            <p><strong>Por favor, lea cuidadosamente el documento adjunto.</strong></p>
            <p>En el documento encontrará:</p>
            <ul>
                <li>Los hechos que motivaron esta decisión</li>
                <li>El análisis de sus descargos</li>
                <li>La decisión tomada y sus fundamentos</li>
                <li>Las consecuencias prácticas de esta sanción</li>
                <li>Sus derechos de impugnación</li>
            </ul>
        </div>

        <div class="important">
            <h2>Derecho a Impugnación</h2>
            <p>Usted tiene derecho a presentar una impugnación si no está de acuerdo con esta decisión.</p>
            <p>Los detalles sobre cómo hacerlo y los plazos disponibles se encuentran en el documento adjunto.</p>
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
</body>

</html>