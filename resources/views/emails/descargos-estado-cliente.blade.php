<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación de Estado de Descargos - Cliente</title>
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
            background-color: {{ $estado === 'descargos_realizados' ? '#2563eb' : '#f59e0b' }};
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
            border-left: 4px solid {{ $estado === 'descargos_realizados' ? '#2563eb' : '#f59e0b' }};
            border-radius: 4px;
        }

        .action-box {
            background-color: #dbeafe;
            border-left-color: #2563eb;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            border-left: 4px solid #2563eb;
        }

        .warning-box {
            background-color: #fef3c7;
            border-left-color: #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            border-left: 4px solid #f59e0b;
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

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            margin: 10px 0;
            background-color: {{ $estado === 'descargos_realizados' ? '#dbeafe' : '#fef3c7' }};
            color: {{ $estado === 'descargos_realizados' ? '#1e40af' : '#92400e' }};
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2563eb;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 15px;
        }

        .btn:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 style="margin: 0;">
            @if($estado === 'descargos_realizados')
                Descargos Completados por el Trabajador
            @else
                Trabajador No Presentó Descargos
            @endif
        </h1>
    </div>

    <div class="content">
        <p>Estimado(a) <strong>{{ $cliente->name }}</strong>,</p>

        @if($estado === 'descargos_realizados')
            <p>Le informamos que el trabajador <strong>{{ $trabajador->nombre_completo }}</strong> ha completado
                la diligencia de descargos correspondiente al proceso disciplinario <strong>{{ $proceso->codigo }}</strong>.</p>

            <div class="info-box">
                <h2>Estado del Proceso</h2>
                <p><span class="status-badge">Descargos Completados</span></p>
                <p>El trabajador ha presentado sus respuestas y documentación de soporte en el sistema.</p>
            </div>

            <div class="action-box">
                <h2>Acción Requerida</h2>
                <p>El siguiente paso en el proceso es:</p>
                <ol>
                    <li><strong>Revisar las respuestas</strong> proporcionadas por el trabajador</li>
                    <li><strong>Analizar las evidencias</strong> aportadas como parte de su defensa</li>
                    <li><strong>Emitir la sanción correspondiente</strong> según la gravedad de los hechos y las respuestas recibidas</li>
                </ol>
                <p>Puede acceder al proceso desde el panel de administración para revisar toda la información.</p>
            </div>

        @else
            <p>Le informamos que el trabajador <strong>{{ $trabajador->nombre_completo }}</strong>
                <strong>NO asistió ni presentó descargos</strong> en el proceso disciplinario <strong>{{ $proceso->codigo }}</strong>.</p>

            <div class="info-box">
                <h2>Estado del Proceso</h2>
                <p><span class="status-badge">Descargos No Realizados</span></p>
                <p>El plazo para presentar descargos ha vencido sin respuesta del trabajador.</p>
            </div>

            <div class="warning-box">
                <h2>Implicaciones</h2>
                <p>Al no presentar descargos, el trabajador:</p>
                <ul>
                    <li>Renunció a su derecho de defensa en esta etapa del proceso</li>
                    <li>El proceso puede continuar basándose únicamente en la información y evidencias existentes</li>
                </ul>
            </div>

            <div class="action-box">
                <h2>Siguiente Paso</h2>
                <p>Puede proceder a <strong>emitir la sanción correspondiente</strong> teniendo en cuenta que el trabajador
                    tuvo la oportunidad de defenderse pero no la ejerció.</p>
            </div>
        @endif

        <div class="info-box">
            <h2>Información del Proceso</h2>
            <p><strong>Código del Proceso:</strong> {{ $proceso->codigo }}</p>
            <p><strong>Trabajador:</strong> {{ $trabajador->nombre_completo }}</p>
            <p><strong>Cargo:</strong> {{ $trabajador->cargo }}</p>
            <p><strong>Empresa:</strong> {{ $empresa->razon_social }}</p>
            @if($proceso->fecha_descargos_programada)
                <p><strong>Fecha programada de descargos:</strong>
                    {{ \Carbon\Carbon::parse($proceso->fecha_descargos_programada)->locale('es')->isoFormat('D [de] MMMM [de] YYYY [a las] h:mm A') }}
                </p>
            @endif
        </div>

        <p>Atentamente,</p>
        <p><strong>CES LEGAL S.A.S.</strong><br>
            Sistema de Gestión de Procesos Disciplinarios</p>
    </div>

    <div class="footer">
        <p>Este es un correo electrónico automático generado por el sistema de gestión de procesos disciplinarios.</p>
        <p>Para más información, ingrese al panel de administración.</p>
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
