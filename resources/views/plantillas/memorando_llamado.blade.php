<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memorando de Llamado de Atención</title>
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
        <h1>MEMORANDO DE LLAMADO DE ATENCIÓN</h1>
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
            <span class="label">ASUNTO:</span> <strong>LLAMADO DE ATENCIÓN</strong>
        </div>
    </div>

    <div class="content">
        <p>Por medio del presente memorando, me permito hacer un llamado de atención formal debido a los siguientes hechos:</p>

        <h3>HECHOS:</h3>
        <p>{{hechos}}</p>

        <h3>FECHA DE OCURRENCIA:</h3>
        <p>{{fecha_ocurrencia}}</p>

        <h3>NORMAS INCUMPLIDAS:</h3>
        <p>{{normas_incumplidas}}</p>

        <h3>CONSIDERACIONES:</h3>
        <p>Los hechos anteriormente descritos constituyen un incumplimiento de las obligaciones laborales establecidas en el Reglamento Interno de Trabajo y el Código Sustantivo del Trabajo, específicamente en lo relacionado con:</p>
        <p>{{fundamento_legal}}</p>

        <h3>DECISIÓN:</h3>
        <p>En virtud de lo expuesto, y de conformidad con las facultades que me confiere la ley y el contrato de trabajo, procedo a realizar un <strong>LLAMADO DE ATENCIÓN</strong> formal, el cual quedará registrado en su hoja de vida laboral.</p>

        <p>Se le advierte que la reincidencia en este tipo de comportamientos podrá dar lugar a la aplicación de sanciones más severas, incluyendo la suspensión o terminación del contrato de trabajo con justa causa.</p>

        <h3>DERECHO A LA DEFENSA:</h3>
        <p>Se le informa que tiene derecho a presentar las explicaciones o descargos que considere pertinentes respecto a esta sanción, dentro de los tres (3) días hábiles siguientes a la recepción del presente memorando.</p>
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
                <div style="font-size: 10pt; margin-top: 5px;">Acuso recibo del presente memorando</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p><em>Documento generado el {{fecha_generacion}} - Código: {{codigo}}</em></p>
        <p><em>Este documento ha sido generado de manera electrónica por el Sistema de Gestión Legal CES</em></p>
    </div>
</body>
</html>
