<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memorando de Suspensión</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            margin: 40px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .header .company {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .content {
            text-align: justify;
            margin: 20px 0;
        }
        .highlight-box {
            background-color: #f0f0f0;
            border: 2px solid #333;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .signature-section {
            margin-top: 60px;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 300px;
            margin: 80px auto 10px auto;
            text-align: center;
        }
        .footer {
            margin-top: 40px;
            font-size: 10pt;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{empresa_nombre}}</div>
        <div>{{empresa_nit}}</div>
        <div>{{empresa_direccion}}</div>
        <div>{{empresa_ciudad}}</div>
        <h1>MEMORANDO DE SUSPENSIÓN LABORAL</h1>
        <div><strong>Código:</strong> {{codigo}}</div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span class="label">FECHA:</span> {{fecha}}
        </div>
        <div class="info-row">
            <span class="label">PARA:</span> {{trabajador_nombre}}
        </div>
        <div class="info-row">
            <span class="label">CARGO:</span> {{trabajador_cargo}}
        </div>
        <div class="info-row">
            <span class="label">DOCUMENTO:</span> {{trabajador_documento}}
        </div>
        <div class="info-row">
            <span class="label">DE:</span> {{empresa_representante}}
        </div>
        <div class="info-row">
            <span class="label">ASUNTO:</span> <strong>SUSPENSIÓN LABORAL</strong>
        </div>
    </div>

    <div class="content">
        <p>Por medio del presente memorando, me permito comunicarle la decisión de suspensión laboral adoptada de conformidad con las disposiciones legales vigentes y el Reglamento Interno de Trabajo.</p>

        <h3>ANTECEDENTES:</h3>
        <p>Con fecha {{fecha_apertura_proceso}}, se inició proceso disciplinario bajo el código {{codigo_proceso}}, debido a los siguientes hechos:</p>

        <h3>HECHOS:</h3>
        <p>{{hechos}}</p>

        <h3>FECHA DE OCURRENCIA:</h3>
        <p>{{fecha_ocurrencia}}</p>

        <h3>NORMAS INCUMPLIDAS:</h3>
        <p>{{normas_incumplidas}}</p>

        <h3>PROCESO DISCIPLINARIO:</h3>
        <p>Se le otorgó el derecho a presentar descargos según lo establecido en la normatividad laboral vigente, diligencia que se llevó a cabo el día {{fecha_descargos}}.</p>

        <h3>ANÁLISIS JURÍDICO:</h3>
        <p>{{analisis_juridico}}</p>

        <h3>FUNDAMENTO LEGAL:</h3>
        <p>{{fundamento_legal}}</p>

        <div class="highlight-box">
            <h3>DECISIÓN:</h3>
            <p><strong>Se impone SUSPENSIÓN LABORAL por {{dias_suspension}} día(s) hábil(es)</strong></p>
            <p><strong>Fecha de inicio:</strong> {{fecha_inicio_suspension}}</p>
            <p><strong>Fecha de finalización:</strong> {{fecha_fin_suspension}}</p>
            <p><strong>Durante este periodo no habrá lugar al pago de salario ni prestaciones sociales</strong></p>
        </div>

        <h3>CONSIDERACIONES:</h3>
        <p>Esta sanción se fundamenta en el incumplimiento de las obligaciones laborales establecidas en el contrato de trabajo y el Reglamento Interno de Trabajo, y se ajusta a lo dispuesto en el Código Sustantivo del Trabajo.</p>

        <p>Se le advierte que la reincidencia en este tipo de comportamientos podrá dar lugar a la aplicación de sanciones más severas, incluyendo la terminación del contrato de trabajo con justa causa.</p>

        <h3>DERECHO DE IMPUGNACIÓN:</h3>
        <p>De conformidad con lo establecido en la legislación laboral vigente, usted tiene derecho a impugnar esta decisión dentro de los tres (3) días hábiles siguientes a la notificación del presente memorando.</p>

        <h3>REINTEGRO:</h3>
        <p>Una vez cumplido el período de suspensión, deberá reintegrarse a sus labores habituales el día {{fecha_reintegro}} en el horario establecido para su cargo.</p>
    </div>

    <div class="signature-section">
        <div class="signature-line">
            <div>{{empresa_representante}}</div>
            <div>{{empresa_cargo_representante}}</div>
            <div>{{empresa_nombre}}</div>
        </div>

        <div style="margin-top: 80px;">
            <div class="signature-line">
                <div>{{trabajador_nombre}}</div>
                <div>{{trabajador_cargo}}</div>
                <div>C.C. {{trabajador_documento}}</div>
                <div style="font-size: 10pt; margin-top: 5px;">Acuso recibo del presente memorando el día: _________________</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p><em>Documento generado el {{fecha_generacion}} - Código: {{codigo}}</em></p>
        <p><em>Este documento ha sido generado de manera electrónica por el Sistema de Gestión Legal CES</em></p>
    </div>
</body>
</html>
