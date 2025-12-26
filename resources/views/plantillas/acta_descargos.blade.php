<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acta de Diligencia de Descargos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            margin: 40px;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        .header h1 {
            font-size: 14pt;
            font-weight: bold;
            margin: 10px 0;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 35%;
            padding: 5px;
            border: 1px solid #000;
            background-color: #f0f0f0;
        }
        .info-value {
            display: table-cell;
            padding: 5px;
            border: 1px solid #000;
        }
        .content {
            text-align: justify;
            margin: 15px 0;
        }
        .question-answer {
            margin: 15px 0;
            padding: 10px;
            border-left: 3px solid #333;
            background-color: #f9f9f9;
        }
        .signature-section {
            margin-top: 50px;
        }
        .signature-box {
            border: 1px solid #000;
            padding: 15px;
            margin: 20px 0;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin: 60px auto 5px auto;
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ACTA DE DILIGENCIA DE DESCARGOS</h1>
        <div>Proceso Disciplinario No. {{codigo_proceso}}</div>
        <div>Acta No. {{codigo_acta}}</div>
    </div>

    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">EMPRESA:</div>
            <div class="info-value">{{empresa_nombre}} - NIT {{empresa_nit}}</div>
        </div>
        <div class="info-row">
            <div class="info-label">FECHA Y HORA:</div>
            <div class="info-value">{{fecha_diligencia}} - {{hora_diligencia}}</div>
        </div>
        <div class="info-row">
            <div class="info-label">LUGAR:</div>
            <div class="info-value">{{lugar_diligencia}}</div>
        </div>
        <div class="info-row">
            <div class="info-label">TRABAJADOR:</div>
            <div class="info-value">{{trabajador_nombre}} - {{trabajador_documento}}</div>
        </div>
        <div class="info-row">
            <div class="info-label">CARGO:</div>
            <div class="info-value">{{trabajador_cargo}}</div>
        </div>
        <div class="info-row">
            <div class="info-label">REPRESENTANTE EMPRESA:</div>
            <div class="info-value">{{representante_empresa}}</div>
        </div>
    </div>

    <div class="content">
        <h3>1. OBJETO DE LA DILIGENCIA:</h3>
        <p>Se convoca la presente diligencia con el fin de dar cumplimiento al debido proceso y al derecho de defensa del trabajador {{trabajador_nombre}}, en el marco del proceso disciplinario No. {{codigo_proceso}}.</p>

        <h3>2. ASISTENCIA DEL TRABAJADOR:</h3>
        <p><strong>{{#if trabajador_asistio}}El trabajador SÍ asistió a la diligencia de descargos.{{else}}El trabajador NO asistió a la diligencia de descargos.{{/if}}</strong></p>

        {{#if motivo_inasistencia}}
        <p><strong>Motivo de inasistencia:</strong> {{motivo_inasistencia}}</p>
        {{/if}}

        {{#if trabajador_asistio}}
        {{#if acompanante_nombre}}
        <h3>3. ACOMPAÑANTE:</h3>
        <p>El trabajador asistió acompañado de:</p>
        <ul>
            <li><strong>Nombre:</strong> {{acompanante_nombre}}</li>
            <li><strong>Cargo/Rol:</strong> {{acompanante_cargo}}</li>
        </ul>
        {{/if}}

        <h3>4. HECHOS OBJETO DEL PROCESO:</h3>
        <p>Se le informa al trabajador sobre los hechos que dieron origen al presente proceso disciplinario:</p>
        <p>{{hechos}}</p>
        <p><strong>Fecha de ocurrencia:</strong> {{fecha_ocurrencia}}</p>

        <h3>5. NORMAS PRESUNTAMENTE INCUMPLIDAS:</h3>
        <p>{{normas_incumplidas}}</p>

        <h3>6. DESARROLLO DE LA DILIGENCIA:</h3>
        {{preguntas_respuestas}}

        {{#if pruebas_aportadas}}
        <h3>7. PRUEBAS APORTADAS:</h3>
        <p>El trabajador aportó las siguientes pruebas en su defensa:</p>
        <p>{{descripcion_pruebas}}</p>
        {{else}}
        <h3>7. PRUEBAS APORTADAS:</h3>
        <p>El trabajador manifestó no aportar pruebas adicionales en este momento.</p>
        {{/if}}

        {{#if observaciones}}
        <h3>8. OBSERVACIONES:</h3>
        <p>{{observaciones}}</p>
        {{/if}}

        <h3>{{#if pruebas_aportadas}}9{{else}}8{{/if}}. FINALIZACIÓN DE LA DILIGENCIA:</h3>
        <p>Se da por terminada la presente diligencia a las {{hora_finalizacion}}, informándole al trabajador que se procederá al análisis jurídico de los hechos, los descargos presentados y las pruebas aportadas, y que se le notificará oportunamente la decisión que se adopte.</p>

        <p>Se le recuerda al trabajador su derecho a impugnar la decisión que se tome, dentro de los tres (3) días hábiles siguientes a su notificación.</p>
        {{/if}}

        {{#unless trabajador_asistio}}
        <h3>3. CONSTANCIA DE INASISTENCIA:</h3>
        <p>Habiéndose citado debidamente al trabajador para la presente diligencia de descargos, y no habiendo asistido, se deja constancia de su inasistencia, por lo cual se procederá conforme a lo establecido en el Reglamento Interno de Trabajo y la legislación laboral vigente.</p>

        <p>Se continuará con el proceso disciplinario, valorándose los hechos y pruebas existentes para la toma de decisión correspondiente.</p>
        {{/unless}}
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <p><strong>FIRMAS:</strong></p>

            <div class="signature-line">
                <div>{{representante_empresa}}</div>
                <div style="font-size: 9pt;">Representante de la Empresa</div>
            </div>

            {{#if trabajador_asistio}}
            <div class="signature-line">
                <div>{{trabajador_nombre}}</div>
                <div style="font-size: 9pt;">Trabajador - C.C. {{trabajador_documento}}</div>
            </div>

            {{#if acompanante_nombre}}
            <div class="signature-line">
                <div>{{acompanante_nombre}}</div>
                <div style="font-size: 9pt;">{{acompanante_cargo}}</div>
            </div>
            {{/if}}
            {{else}}
            <p style="margin-top: 20px;"><em>El trabajador no asistió a la diligencia, por lo cual no firma la presente acta.</em></p>
            {{/if}}
        </div>
    </div>

    <div class="footer">
        <p><strong>Código de Acta:</strong> {{codigo_acta}} | <strong>Proceso:</strong> {{codigo_proceso}}</p>
        <p><em>Documento generado el {{fecha_generacion}} por el Sistema de Gestión Legal CES</em></p>
        <p><em>Este documento hace parte integral del proceso disciplinario y debe ser archivado en la hoja de vida del trabajador.</em></p>
    </div>
</body>
</html>
