<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato de Labor u Obra</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.8;
            margin: 50px;
            color: #000;
            text-align: justify;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 20px;
            text-decoration: underline;
        }
        .contract-code {
            text-align: right;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .clause {
            margin: 20px 0;
        }
        .clause-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 10px;
        }
        .signature-section {
            margin-top: 60px;
            page-break-inside: avoid;
        }
        .signature-box {
            display: inline-block;
            width: 45%;
            text-align: center;
            margin: 20px 2%;
            vertical-align: top;
        }
        .signature-line {
            border-top: 2px solid #000;
            margin-top: 80px;
            padding-top: 10px;
        }
        .footer {
            margin-top: 40px;
            font-size: 9pt;
            color: #666;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="contract-code">
        CONTRATO No. {{codigo_contrato}}
    </div>

    <div class="header">
        <h1>CONTRATO DE TRABAJO POR LABOR U OBRA CONTRATADA</h1>
    </div>

    <p>Entre los suscritos a saber: <strong>{{empresa_nombre}}</strong>, sociedad legalmente constituida, identificada con NIT {{empresa_nit}}, representada legalmente por <strong>{{representante_legal}}</strong>, quien en adelante se denominará <strong>EL EMPLEADOR</strong>, por una parte; y por la otra <strong>{{trabajador_nombres}} {{trabajador_apellidos}}</strong>, identificado(a) con {{trabajador_tipo_documento}} No. {{trabajador_numero_documento}}, quien en adelante se denominará <strong>EL TRABAJADOR</strong>, hemos convenido celebrar el presente CONTRATO DE TRABAJO POR LABOR U OBRA CONTRATADA, que se regirá por las siguientes cláusulas:</p>

    <div class="clause">
        <div class="clause-title">PRIMERA: OBJETO DEL CONTRATO.</div>
        <p>EL EMPLEADOR contrata los servicios de EL TRABAJADOR para que desempeñe el cargo de <strong>{{cargo_contrato}}</strong>, ejecutando específicamente la siguiente labor u obra:</p>
        <p>{{objeto_comercial}}</p>
    </div>

    <div class="clause">
        <div class="clause-title">SEGUNDA: NATURALEZA DEL CONTRATO.</div>
        <p>El presente contrato es de naturaleza laboral por labor u obra contratada, por lo tanto, su duración estará determinada por el tiempo requerido para ejecutar y terminar completamente la labor u obra descrita en la cláusula primera. El contrato terminará cuando se cumpla totalmente la labor u obra contratada.</p>
    </div>

    <div class="clause">
        <div class="clause-title">TERCERA: FECHA DE INICIO Y DURACIÓN.</div>
        <p>EL TRABAJADOR iniciará sus labores el día <strong>{{fecha_inicio}}</strong>. La duración del presente contrato estará sujeta a la terminación de la labor u obra contratada, sin que pueda entenderse como un contrato a término indefinido.</p>
    </div>

    <div class="clause">
        <div class="clause-title">CUARTA: LUGAR DE TRABAJO.</div>
        <p>EL TRABAJADOR prestará sus servicios en <strong>{{lugar_trabajo}}</strong>, o en cualquier otro lugar que requiera EL EMPLEADOR para el desarrollo de las actividades objeto del presente contrato.</p>
    </div>

    <div class="clause">
        <div class="clause-title">QUINTA: FUNCIONES Y RESPONSABILIDADES.</div>
        <p>Son funciones y responsabilidades de EL TRABAJADOR:</p>
        <p>{{manual_funciones}}</p>
        <p>{{responsabilidades}}</p>
    </div>

    <div class="clause">
        <div class="clause-title">SEXTA: REMUNERACIÓN.</div>
        <p>EL EMPLEADOR pagará a EL TRABAJADOR por la ejecución de las labores objeto de este contrato, la suma de <strong>{{salario_letras}} ({{salario_numeros}})</strong> mensuales, pagaderos de conformidad con las disposiciones legales vigentes.</p>
    </div>

    <div class="clause">
        <div class="clause-title">SÉPTIMA: JORNADA LABORAL.</div>
        <p>La jornada ordinaria de trabajo será de {{horas_semanales}} horas semanales, distribuidas de la siguiente manera: {{distribucion_horario}}. EL TRABAJADOR se compromete a laborar el tiempo que sea necesario para la ejecución de la labor u obra contratada.</p>
    </div>

    <div class="clause">
        <div class="clause-title">OCTAVA: OBLIGACIONES DEL TRABAJADOR.</div>
        <p>Además de las contempladas en el artículo 58 del Código Sustantivo del Trabajo, son obligaciones especiales de EL TRABAJADOR:</p>
        <ol>
            <li>Ejecutar la labor u obra contratada con diligencia, eficiencia y profesionalismo.</li>
            <li>Cumplir con las disposiciones del Reglamento Interno de Trabajo.</li>
            <li>Guardar reserva sobre la información confidencial a la que tenga acceso.</li>
            <li>Cuidar los elementos, equipos y herramientas que le sean entregados.</li>
            <li>Informar oportunamente sobre el avance de la labor u obra.</li>
        </ol>
    </div>

    <div class="clause">
        <div class="clause-title">NOVENA: OBLIGACIONES DEL EMPLEADOR.</div>
        <p>Además de las contempladas en el artículo 57 del Código Sustantivo del Trabajo, son obligaciones de EL EMPLEADOR:</p>
        <ol>
            <li>Pagar oportunamente la remuneración pactada.</li>
            <li>Proporcionar los elementos necesarios para la ejecución de la labor.</li>
            <li>Garantizar condiciones de seguridad y salud en el trabajo.</li>
            <li>Afiliar al trabajador al Sistema de Seguridad Social Integral.</li>
        </ol>
    </div>

    <div class="clause">
        <div class="clause-title">DÉCIMA: SEGURIDAD SOCIAL.</div>
        <p>EL EMPLEADOR se obliga a afiliar a EL TRABAJADOR al Sistema de Seguridad Social Integral (Salud, Pensión y Riesgos Laborales) y realizar los aportes correspondientes de acuerdo con la ley.</p>
    </div>

    <div class="clause">
        <div class="clause-title">UNDÉCIMA: TERMINACIÓN DEL CONTRATO.</div>
        <p>El presente contrato terminará:</p>
        <ol>
            <li>Por la conclusión de la labor u obra contratada.</li>
            <li>Por mutuo acuerdo entre las partes.</li>
            <li>Por cualquiera de las causas establecidas en los artículos 61 y 62 del Código Sustantivo del Trabajo.</li>
        </ol>
        <p>Al terminar el contrato, EL EMPLEADOR deberá pagar a EL TRABAJADOR todas las prestaciones sociales y salarios pendientes.</p>
    </div>

    <div class="clause">
        <div class="clause-title">DUODÉCIMA: CLÁUSULA DE CONFIDENCIALIDAD.</div>
        <p>EL TRABAJADOR se compromete a guardar absoluta reserva sobre toda la información técnica, comercial, administrativa y de cualquier naturaleza a la que tenga acceso durante la ejecución del contrato, inclusive después de su terminación.</p>
    </div>

    <div class="clause">
        <div class="clause-title">DECIMOTERCERA: ACEPTACIÓN DEL REGLAMENTO.</div>
        <p>EL TRABAJADOR declara haber recibido y conocer el Reglamento Interno de Trabajo de EL EMPLEADOR, comprometiéndose a su estricto cumplimiento.</p>
    </div>

    <div class="clause">
        <div class="clause-title">DECIMOCUARTA: DOMICILIO CONTRACTUAL.</div>
        <p>Para todos los efectos legales derivados del presente contrato, las partes fijan como domicilio contractual la ciudad de <strong>{{ciudad_contrato}}</strong>.</p>
    </div>

    <div class="clause">
        <div class="clause-title">DECIMOQUINTA: MODIFICACIONES.</div>
        <p>Cualquier modificación al presente contrato deberá hacerse por escrito y con la firma de ambas partes.</p>
    </div>

    <p style="margin-top: 30px;">Para constancia se firma el presente contrato por las partes, en dos (2) ejemplares del mismo tenor y valor, en <strong>{{ciudad_firma}}</strong>, a los {{dia_firma}} días del mes de {{mes_firma}} de {{año_firma}}.</p>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                <div><strong>EL EMPLEADOR</strong></div>
                <div style="margin-top: 10px;">{{representante_legal}}</div>
                <div>{{cargo_representante}}</div>
                <div>{{empresa_nombre}}</div>
                <div>NIT {{empresa_nit}}</div>
            </div>
        </div>

        <div class="signature-box">
            <div class="signature-line">
                <div><strong>EL TRABAJADOR</strong></div>
                <div style="margin-top: 10px;">{{trabajador_nombres}} {{trabajador_apellidos}}</div>
                <div>{{trabajador_tipo_documento}} {{trabajador_numero_documento}}</div>
                <div>Dirección: {{trabajador_direccion}}</div>
                <div>Tel: {{trabajador_telefono}}</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p><strong>CONTRATO No. {{codigo_contrato}}</strong></p>
        <p><em>Documento generado el {{fecha_generacion}} por el Sistema de Gestión Legal CES</em></p>
        <p><em>Este contrato se rige por las disposiciones del Código Sustantivo del Trabajo de Colombia</em></p>
    </div>
</body>
</html>
