{{--
    Contrato Individual de Trabajo a Término Indefinido - texto real provisto por
    el usuario (FORMATO DE CONTRATO A TÉRMINO INDEFINIDO-28 DE AGOSTO DE 2026.docx),
    con los datos variables identificados en el spec sustituidos por variables
    Blade. El resto del texto es literal - no resumir ni reformular ninguna
    cláusula.

    Variables esperadas (todas provistas por
    SolicitudContratoIAService::generarHTMLDesdeVista()):
      $nombreEmpresa, $nit, $direccionEmpresa, $telefonoEmpresa
      $representanteLegal, $representanteLegalCedula (puede ser null)
      $nombreTrabajador, $tipoDocumentoLabel, $numeroDocumento
      $direccionTrabajador, $telefonoTrabajador, $emailTrabajador (pueden ser null)
      $cargo, $salarioFormateado, $salarioEnLetras
      $periodoPagoLabel, $periodoPagoFrase
      $lugarLabores, $lugarContratacion
      $fechaInicio, $fechaFirma
      $diaDescansoObligatorio
      $objetoJuridico (HTML ya escapado por el llamador, puede ir vacío)

    NOTA: este tipo de contrato NO usa $fechaFin ni $duracionTexto (no aplica
    fecha de terminación en un contrato a término indefinido).
--}}
<html>

<head>
    <style>
        @page {
            margin: 2cm 2.3cm;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            font-family: 'Tahoma', 'DejaVu Sans', Arial, sans-serif;
            font-size: 9.5pt;
            color: #000;
        }

        body {
            margin: 0;
            padding: 0;
            line-height: 1.35;
            text-align: justify;
        }

        h1 {
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            margin: 0 0 12pt 0;
            text-transform: uppercase;
            line-height: 1.2;
        }

        h2 {
            font-size: 9.5pt;
            font-weight: bold;
            text-align: center;
            margin: 0 0 12pt 0;
            line-height: 1.3;
        }

        table.datos {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 14pt 0;
            font-size: 9.5pt;
        }

        table.datos td {
            border: 1px solid #000;
            padding: 3pt 6pt;
            font-size: 9.5pt;
            line-height: 1.25;
            vertical-align: top;
        }

        table.datos td.label {
            font-weight: bold;
            width: 42%;
        }

        p {
            font-size: 9.5pt;
            line-height: 1.35;
            text-align: justify;
            margin: 0 0 7pt 0;
        }

        ol {
            margin-top: 3pt;
            margin-bottom: 7pt;
            padding-left: 25pt;
            font-size: 9.5pt;
        }

        ol li {
            font-size: 9.5pt;
            line-height: 1.35;
            margin-bottom: 3pt;
            text-align: justify;
            padding-left: 2pt;
        }

        .clausula-titulo {
            font-weight: bold;
            text-transform: uppercase;
        }

        p.clausula {
            margin-top: 9pt;
            margin-bottom: 7pt;
        }

        strong, b {
            font-weight: bold;
        }

        table.firma {
            width: 100%;
            margin-top: 55pt;
            border-collapse: collapse;
        }

        table.firma td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding-top: 38pt;
            font-size: 9.5pt;
            line-height: 1.3;
        }

        .page-break {
            page-break-before: always;
        }

        .avoid-break {
            page-break-inside: avoid;
        }

        div, span, table, td, th {
            font-size: 9.5pt;
        }
    </style>
</head>

<body>

    <h1>CONTRATO INDIVIDUAL DE TRABAJO A TÉRMINO INDEFINIDO</h1>
    <h2>TABLA DE INFORMACIÓN DE LAS PARTES CONTRATANTES Y TÉRMINO DE DURACIÓN DEL CONTRATO</h2>

    <table class="datos">
        <tr>
            <td class="label">NOMBRE DEL EMPLEADOR:</td>
            <td>{{ $nombreEmpresa }}</td>
        </tr>
        <tr>
            <td class="label">NÚMERO DE ID DEL EMPLEADOR:</td>
            <td>NIT. {{ $nit }}</td>
        </tr>
        <tr>
            <td class="label">DIRECCIÓN DEL EMPLEADOR:</td>
            <td>{{ $direccionEmpresa }}</td>
        </tr>
        <tr>
            <td class="label">TELÉFONO:</td>
            <td>{{ $telefonoEmpresa }}</td>
        </tr>
        <tr>
            <td class="label">NOMBRE DEL TRABAJADOR:</td>
            <td>{{ $nombreTrabajador }}</td>
        </tr>
        <tr>
            <td class="label">NÚMERO DE ID DEL TRABAJADOR:</td>
            <td>{{ ucfirst($tipoDocumentoLabel) }} N° {{ $numeroDocumento }}</td>
        </tr>
        <tr>
            <td class="label">DIRECCIÓN DEL TRABAJADOR:</td>
            <td>{{ $direccionTrabajador ?: 'No registrada' }}</td>
        </tr>
        <tr>
            <td class="label">NÚMERO TELEFÓNICO DEL TRABAJADOR:</td>
            <td>{{ $telefonoTrabajador ?: 'No registrado' }}</td>
        </tr>
        <tr>
            <td class="label">CORREO ELECTRÓNICO DEL TRABAJADOR:</td>
            <td>{{ $emailTrabajador ?: 'No registrado' }}</td>
        </tr>
        <tr>
            <td class="label">CARGO DEL TRABAJADOR:</td>
            <td>{{ $cargo }}</td>
        </tr>
        <tr>
            <td class="label">SALARIO:</td>
            <td>$ {{ $salarioFormateado }} COP</td>
        </tr>
        <tr>
            <td class="label">PERÍODOS DE PAGO:</td>
            <td>{{ $periodoPagoLabel }}</td>
        </tr>
        <tr>
            <td class="label">LUGAR DONDE DESEMPEÑARÁ LABORES:</td>
            <td>{{ $lugarLabores }}</td>
        </tr>
        <tr>
            <td class="label">LUGAR DONDE HA SIDO CONTRATADO EL TRABAJADOR:</td>
            <td>{{ $lugarContratacion }}</td>
        </tr>
        <tr>
            <td class="label">DURACIÓN DEL CONTRATO:</td>
            <td>Indefinida</td>
        </tr>
        <tr>
            <td class="label">FECHA DE INICIO DE LABORES:</td>
            <td>{{ $fechaInicio }}</td>
        </tr>
    </table>

    <p>Entre los suscritos a saber: {{ $nombreEmpresa }}, identificada con el Nit. {{ $nit }}, representada
        por {{ $representanteLegal }}@if ($representanteLegalCedula)
            identificado con la cédula de ciudadanía No. {{ $representanteLegalCedula }}
        @endif, quien es su representante legal, quien para los efectos de este contrato se
        denominará &ldquo;El Empleador&rdquo;, por una parte y, por la otra {{ $nombreTrabajador }} identificado con la
        {{ $tipoDocumentoLabel }} No. {{ $numeroDocumento }} quien se denominará: &ldquo;El Trabajador&rdquo;, se ha
        acordado celebrar el presente contrato individual de trabajo.</p>

    <p><strong>Anexos del Contrato:</strong> Son anexos del presente Contrato todos los documentos, anexos e información
        compartida por las partes, la totalidad de aquella puesta a disposición por El Empleador y el (la) Trabajador(a)
        para el evento, la totalidad de documentos que rigen la relación contractual entre El Empleador y El (La)
        Trabajador(a), y particularmente, pero sin limitarse a los siguientes documentos, todos los cuales, se entienden
        y reconocen como información conocida por Las Partes:</p>

    <p>&bull; Anexo No.1: Manual de funciones y responsabilidades del cargo de {{ $cargo }}.</p>

    @if ($objetoJuridico)
        <p>{!! $objetoJuridico !!}</p>
    @endif

    <p>El presente contrato de trabajo a término indefinido se regirá por las siguientes cláusulas:</p>

    <p class="clausula"><span class="clausula-titulo">PRIMERA: LEGISLACIÓN APLICABLE.</span> Este contrato se regirá por
        las normas de la Legislación Colombiana en virtud del principio de Territorialidad consagrado en el Código
        Sustantivo del Trabajo; por ende, será este último y su legislación complementaria, el compendio rector de la
        relación laboral existente entre las PARTES, para su formación, ejecución y terminación.</p>

    <p class="clausula"><span class="clausula-titulo">SEGUNDA: DIRECCIÓN NOTIFICACIONES.</span> La dirección del (de la)
        Trabajador(a) que aparece relacionada en la portada de este contrato, se entiende como la residencia actual del
        (de la) Trabajador(a) y en consecuencia su domicilio legal para todos los efectos que se desprendan de la
        relación laboral que con este documento se regla. El (La) Trabajador(a) informará a la empresa, por escrito,
        cualquier cambio con destino a los archivos de ésta. La inobservancia de esta formalidad hará que cualquier
        notificación por escrito hecha a la dirección que aparece registrada, surta los efectos pertinentes cuando la
        Ley exija o autorice la notificación escrita.</p>

    <p><strong>PARÁGRAFO PRIMERO:</strong> Toda la información del personal es de carácter reservado. No obstante, el
        (la) Trabajador(a) otorga su consentimiento expreso e inequívoco al Empleador para que éste pueda ceder o
        comunicar cualquier dato que repose en su hoja de vida, incluso los personales, a otras entidades con las que
        tenga especial interés, así como para transferir cualquier dato que repose en su hoja de vida, incluso los
        personales, a entidades que hagan parte de la Administración Pública.</p>

    <p><strong>PARÁGRAFO SEGUNDO:</strong> El (La) Trabajador(a) se compromete a comunicar al Empleador, de manera
        inmediata, cualquier modificación que se produzca en su estado civil, domicilio, número de hijos menores y
        cualquier otra circunstancia que pueda afectar a sus cotizaciones a la Seguridad Social, y en general cualquier
        dato personal del (de la) Trabajador(a) que interese al Empleador. Se considera falta grave el que el (la)
        Trabajador(a) no suministre dicha información, siendo que el Empleador no responderá por hecho alguno derivado
        de la ausencia o desactualización de dicha información por parte del (de la) Trabajador(a).</p>

    <p class="clausula"><span class="clausula-titulo">TERCERA: OBJETO.</span> El Empleador contrata los servicios
        personales del (de la) Trabajador(a) y éste se obliga a:</p>

    <ol>
        <li>Poner al servicio del Empleador toda su capacidad normal de trabajo, en el desempeño de las funciones
            propias de su cargo y en las labores anexas y complementarias del mismo, de conformidad con las órdenes,
            instrucciones, procedimientos y metas que le indique el Empleador directamente o a través de sus
            representantes.</li>
        <li>Prestar sus servicios en forma exclusiva al Empleador; es decir, a no prestar directa ni indirectamente
            servicios laborales a otros empleadores, ni a trabajar por cuenta propia en el mismo oficio, durante la
            vigencia de este contrato, velar por la conservación y restituir en buen estado, salvo el deterioro por el
            uso natural, los elementos y útiles que se le entreguen para el desempeño de sus labores.</li>
        <li>Hacer saber al Empleador de manera oportuna toda información de interés para la Empresa.</li>
        <li>Responder al Empleador por los perjuicios que le ocasionare la toma de decisiones por causa o con ocasión de
            funciones que no le hayan sido encomendadas o por cualquier extralimitación en las que le corresponda.</li>
        <li>Cumplir toda otra obligación y prohibición que se desprenda de las labores principales, anexas, conexas y
            complementarias que según lo anterior y de acuerdo con su cargo incumban al (a la) Trabajador(a).</li>
        <li>A guardar completa reserva de todo lo que llegue a su conocimiento en razón de su oficio y cuya divulgación
            pudiera causar perjuicios al Empleador o a las empresas o personas naturales o jurídicas relacionadas con la
            misma, o a los clientes del Empleador. El (La) Trabajador(a) se obliga igualmente a continuar guardando
            dicha reserva cuando deje de estar vinculado con el Empleador.</li>
        <li>A utilizar los enseres, útiles, instrumentos, herramientas y demás elementos que le entregue la empresa
            exclusivamente para los fines que le fueron suministrados.</li>
        <li>Utilizar única y exclusivamente el software previamente autorizado por el Empleador para la prestación de
            sus servicios y abstenerse en todo caso de instalar cualquier otro tipo de software diferente al autorizado.
            En caso de que el (la) Trabajador(a) instale cualquier tipo de software no autorizado será el único
            responsable por los eventuales perjuicios que se llegue a causar al Empleador y a terceros por uso
            incorrecto del mismo y la utilización de programas sin las licencias correspondientes.</li>
        <li>No suministrar el nombre de usuario y contraseñas proporcionadas por el Empleador de uso interno y externo
            que sean suministradas al (a la) Trabajador(a) en virtud de las funciones ejecutadas por él.</li>
        <li>Comunicar de manera oportuna cualquier eventual conflicto de interés que involucre al (a la) Trabajador(a),
            al Empleador o a las empresas o personas naturales o jurídicas relacionadas con la misma y/o a los clientes
            o afiliados del Empleador.</li>
        <li>Comunicar de manera oportuna a sus superiores jerárquicos o el representante del Empleador cualquier
            irregularidad o aparente irregularidad en la realización o aprobación de gastos y/o inversiones, y
            abstenerse de realizar inversiones y/o gastos que no guarden relación directa con el objeto social del
            Empleador.</li>
        <li>Cumplir la totalidad de las Políticas contenidas en la normativa interna del Empleador, o documentos que lo
            adicionen, sustituyan o complementen.</li>
        <li>Teniendo en cuenta las normas legales que regulan el tipo de actividad contratada, El (La) Trabajador(a)
            dará estricto cumplimiento a las disposiciones legales y reglamentarias sobre el SARLAFT.</li>
    </ol>

    <p><strong>PARÁGRAFO PRIMERO:</strong> El (La) Trabajador(a) en desarrollo de las funciones a él asignadas por el
        Empleador, prestará sus servicios en forma coetánea a cualquier persona natural o jurídica que por instrucciones
        determine el Empleador y en especial a aquellas sociedades filiales, subsidiarias, matrices, subordinadas y
        demás empresas relacionadas con el Empleador, sin que ello implique la coexistencia de otros contratos de
        trabajo o de prestación de servicios, pues para todos los efectos legales, laborales, prestacionales y
        parafiscales se entiende que el único empleador es {{ $nombreEmpresa }}, y por tanto los servicios prestados a
        cualquier otra persona natural o jurídica que indique el Empleador no implican ni generan concurrencia o
        coexistencia de contratos.</p>

    <p><strong>PARÁGRAFO SEGUNDO:</strong> De conformidad con lo establecido en el literal i) del artículo 8º de la Ley
        1010 de 2006, las partes entienden que no constituirá una situación de acoso laboral, bajo ninguna de sus
        modalidades, las exigencias que llegue a hacer el Empleador al (a la) Trabajador(a) para que cumpla con las
        estipulaciones contenidas en las cláusulas del presente contrato de trabajo, en especial la presente.</p>

    <p class="clausula"><span class="clausula-titulo">CUARTA: REMUNERACIÓN.</span> El Empleador reconocerá al (a la)
        Trabajador(a), como contraprestación por sus servicios la suma de {{ $salarioEnLetras }} como salario mensual,
        pagadera por períodos vencidos de {{ $periodoPagoFrase }}, en {{ $lugarContratacion }}, o mediante
        consignación o transferencia electrónica a la cuenta bancaria que el (la) Trabajador(a) indique para el efecto.
        Dentro de este pago se encuentra incluida la remuneración de los descansos Dominicales y festivos de que tratan
        en los Capítulos I, II Y III del Título VII del C.S.T.</p>

    <p><strong>PARÁGRAFO PRIMERO:</strong> Las partes acuerdan que en los casos en que se le reconozcan al (a la)
        Trabajador(a) beneficios diferentes al salario, por concepto de alimentación, habitación o vivienda, transporte
        y vestuario, se considerarán tales beneficios o reconocimientos como los no salariales y por lo tanto no se
        tendrán en cuenta como factor salarial para la liquidación de acreencias laborales, ni el pago de aportes
        parafiscales (diferentes a los de la seguridad social), de conformidad con los Arts. 15 y 16 de la ley 50/90, en
        concordancia con el Art. 17 de la 344/96.</p>

    <p class="clausula"><span class="clausula-titulo">QUINTA: TRABAJO NOCTURNO, SUPLEMENTARIO, DOMINICAL Y/O
            FESTIVO.</span> Para el reconocimiento y pago del trabajo suplementario, nocturno, dominical o festivo, el
        Empleador o sus representantes deberán haberlo autorizado previamente y por escrito. Cuando la necesidad de este
        trabajo se presente de manera imprevista o inaplazable, deberá ejecutarse y darse cuenta de él por escrito, a la
        mayor brevedad, al Empleador o a sus representantes para su aprobación. El Empleador, en consecuencia, no
        reconocerá ningún trabajo suplementario, o trabajo nocturno o en días de descanso legalmente obligatorio que no
        haya sido autorizado previamente o que, habiendo sido avisado inmediatamente, no haya sido aprobado como queda
        dicho.</p>

    <p class="clausula"><span class="clausula-titulo">SEXTA: JORNADA DE TRABAJO.</span> El (La) Trabajador(a) se obliga
        a laborar la jornada máxima legal, salvo estipulación expresa y escrita en contrario, cumpliendo con los turnos
        y horarios que señale el Empleador, quien podrá cambiarlos o ajustarlos cuando estime conveniente. Por el
        acuerdo expreso o tácito de las partes, podrán repartirse total parcial o parcialmente las horas de la jornada
        ordinaria, con base en lo dispuesto por el Art. 164 del C.S.T., modificado por el Art. 23 de la ley 50/90,
        teniendo en cuenta que los tiempos de descanso entre las secciones de la jornada no se computan dentro de la
        misma, según el Art. 167 ibidem. De igual manera, las partes podrán acordar que se preste el servicio en los
        turnos de jornada flexible contemplados en el Artículo 51 de la ley 789 de 2002.</p>

    <p><strong>PARÁGRAFO:</strong> En aplicación al artículo 161 del C.S.T. literal c y d, modificado por el artículo 2
        de la ley 2101 de 2021, empleador y trabajador acuerdan que la jornada semanal de 42 horas se realice mediante
        jornadas diarias flexibles de trabajo, distribuidas en máximo 6 días a la semana con un día de descanso
        obligatorio, que podrá coincidir con el domingo. En este, el número de horas de trabajo diario podrá repartirse
        de manera variable durante la respectiva semana y podrá ser de mínimo 4 horas continuas y hasta 9 horas diarias
        sin lugar a ningún recargo por trabajo suplementario, cuando el número de horas de trabajo no exceda el promedio
        de 42 horas semanales dentro de la jornada ordinaria de 6 AM a 7 PM, el empleador no podrá, aun con el
        consentimiento del trabajador, contratarlo para la ejecución de dos turnos en el mismo día. Salvo en labores de
        supervisión, dirección, confianza o manejo.</p>

    <p class="clausula"><span class="clausula-titulo">SÉPTIMA: PERÍODO DE PRUEBA.</span> Las partes contratantes
        convienen en establecer un período inicial de prueba equivalente a dos (2) meses a partir de la fecha de inicio.
    </p>

    <p class="clausula"><span class="clausula-titulo">OCTAVA: DÍA DE DESCANSO OBLIGATORIO.</span> De conformidad con lo
        dispuesto en el parágrafo 1° del artículo 179 del Código Sustantivo del Trabajo, modificado por la Ley 2466 de
        2025, las partes, de común acuerdo, libres de todo vicio del consentimiento, pactan como día de descanso
        obligatorio el día {{ $diaDescansoObligatorio }}.</p>

    <p>En consecuencia, si el trabajador llegare a laborar durante dicho día de descanso obligatorio, el empleador
        reconocerá y pagará los recargos correspondientes.</p>

    <p class="clausula"><span class="clausula-titulo">NOVENA: DURACIÓN DEL CONTRATO.</span> El término de duración del
        presente contrato es a término indefinido.</p>

    <p class="clausula"><span class="clausula-titulo">DÉCIMA: TERMINACIÓN UNILATERAL.</span> Son justas causas para dar
        por terminado unilateralmente este contrato, por cualquiera de las partes, las enumeradas en el Art. 62 y 63 del
        C.S.T., y además, por parte del Empleador, las faltas que para el efecto se califiquen como graves en
        reglamentos y demás documentos que contengan reglamentaciones, ordenes, instrucciones o prohibiciones de
        carácter general o particular, pactos, convenciones colectivas, laudos arbitrales y las que expresamente
        convengan calificar así en escritos que formaran parte integrante del presente contrato. Expresamente se
        califican en este acto como faltas graves la violación a las obligaciones y prohibiciones contenidas en la
        cláusula primera del presente contrato, en el reglamento interno de trabajo y en la circular normativa.</p>

    <p class="clausula"><span class="clausula-titulo">DÉCIMA PRIMERA: OBLIGACIONES DEL (DE LA) TRABAJADOR(A).</span> En
        relación con la actividad propia del (de la) Trabajador(a), éste(a) la ejecutará dentro de las siguientes
        modalidades que implican claras obligaciones para el mismo trabajador, así:</p>

    <ol>
        <li>Poner al servicio del patrono toda su capacidad normal de trabajo, en forma exclusiva, en orden al desempeño
            de las funciones propias del cargo u oficio para el cual ha sido contratado(a), incluyendo las labores
            anexas y complementarias, de conformidad con las órdenes e instrucciones que le imparta el Empleador o sus
            representantes.</li>
        <li>Realizar las funciones, obligaciones y responsabilidades establecidas en el manual de funciones y
            responsabilidades del cargo del trabajador, el cual se adjuntará al presente contrato como el Anexo No.1.
        </li>
        <li>Guardar estricta reserva de toda la información que esté bajo la responsabilidad y el manejo del cargo.</li>
        <li>Hacer uso adecuado de los recursos tanto físicos como económicos que la empresa le otorgue y promover y
            divulgar la confidencialidad en el manejo de documentos y datos de la compañía.</li>
        <li>Asistir con carácter obligatorio al desarrollo del plan de Inducción General y a todas las actividades de
            capacitación que programe la empresa, definidas como fundamentales para el desarrollo de las más altas
            condiciones de Seguridad, y demás actividades convocadas y que sean de orden administrativo.</li>
        <li>Cumplir permanentemente las instrucciones impuestas por la empresa y la Gerencia para asegurar la seguridad
            individual y colectiva.</li>
        <li>Cumplir con el reglamento interno de trabajo y el de higiene y seguridad industrial.</li>
        <li>No prestar directa ni indirectamente servicios laborales a otros empleadores, ni trabajar por cuenta propia
            en el mismo oficio.</li>
        <li>Observar rigurosamente las normas que le fije el Empleador, para la realización de la labor a que se refiere
            el presente contrato de trabajo.</li>
        <li>No atender durante las horas de trabajo, asuntos y ocupaciones distintas a las que el Empleador le
            encomiende, sin previa autorización de éste y evitar fuera de dichas horas de trabajo, otras labores que
            afecten su salud u ocasionen el desgaste de su organismo en forma que le impida prestar eficazmente el
            servicio convenido.</li>
        <li>Cumplir a cabalidad con los deberes y obligaciones que la ley, el reglamento interno y el presente contrato
            le impongan en su condición de trabajador, así como abstenerse de ejecutar las conductas que se encuentren
            prohibidas en esos mismos ordenamientos.</li>
        <li>Guardar absoluta reserva, salvo autorización expresa del Empleador, de todas aquellas informaciones que
            lleguen a su conocimiento, en razón a su trabajo, y que sean por naturaleza privadas, en beneficio de los
            intereses del Empleador.</li>
        <li>Aceptar los traslados que determine el Empleador dentro de sus agencias, sucursales, establecimientos o
            simples dependencias en todo el territorio nacional.</li>
        <li>Cumplir las comisiones de servicio que se indiquen cuando se le requiera en otros lugares diferentes a aquél
            donde habitualmente se debe desempeñar.</li>
        <li>Ejecutar por sí mismo las funciones asignadas y cumplir estrictamente las instrucciones que le sean dadas
            por el Empleador o por quienes lo representen, respecto al desarrollo de sus actividades.</li>
        <li>Conservar y restituir en buen estado, salvo el deterioro normal, los vehículos, instrumentos, y útiles, que
            le sean entregados, para el ejercicio de sus funciones, así como los que posteriormente se le asignen, o
            sean prestados temporalmente para realizar sus funciones.</li>
        <li>No tomar el nombre del Empleador para contraer obligaciones.</li>
        <li>Cuidar permanentemente de los intereses y bienes del Empleador.</li>
        <li>Dedicar la totalidad de su jornada de trabajo a cumplir a cabalidad con sus funciones.</li>
        <li>Programar diariamente su trabajo y asistir puntualmente a las reuniones que efectúe el Empleador a las
            cuales hubiere sido citado.</li>
        <li>Observar una completa armonía y comprensión con los clientes, con sus superiores y compañeros de trabajo, en
            sus relaciones personales y en la ejecución de su labor.</li>
        <li>Cumplir permanentemente con espíritu de lealtad, colaboración y disciplina.</li>
        <li>Asistir puntualmente al trabajo y cumplir con los turnos de trabajo asignados.</li>
        <li>Prestar sus servicios de manera exclusiva para el Empleador.</li>
        <li>No presentarse embriagado al trabajo o bajo los efectos de alcohol, cualquier droga psicoactiva, sustancias
            estupefacientes, alucinógenas o hipnóticas, ni ingerir licores o drogas psicoactivas, sustancias
            estupefacientes, alucinógenas o hipnóticas durante las horas de trabajo. El (La) Trabajador(a) se obliga a
            realizarse, y dejarse realizar, los exámenes de alcoholemia o de consumo de sustancias psicoactivas, que
            sean solicitados por el Empleador o las autoridades. La negativa a ello constituirá falta grave a las
            obligaciones laborales.</li>
        <li>Abstenerse de solicitar o recibir cualquier dádiva o contraprestación al cliente o terceros.</li>
        <li>Mantener perfecta disciplina y respeto dentro y fuera del lugar de trabajo para con sus compañeros,
            superiores, clientes, etc.</li>
        <li>Presentación de informes cuando le sean solicitados verbalmente o por escrito.</li>
        <li>Permitir toda clase de supervisiones e inspecciones y colaborar con las mismas, sin ocultar ningún tipo de
            hecho o información.</li>
        <li>Aceptar y poner en práctica todas las medidas de seguridad medico laboral.</li>
        <li>Abstenerse de realizar actos diferentes a los propios del servicio contratado.</li>
        <li>Manejar escrupulosamente los valores e intereses que se le encomienden por razón de su cargo y a rendir
            cuenta rigurosa de ellos al Empleador.</li>
        <li>En general realizará todas las gestiones propias de su cargo, al igual que todas aquellas que afines o no a
            él, se le encomienden y soliciten por parte de sus superiores jerárquicos, en forma verbal o escrita.</li>
        <li>Las evaluaciones periódicas hacen parte integrante de la hoja de vida y son confidenciales. Al momento de
            evidenciar cualquier acceso a dicha información, deberá reportar de ello a la Gerencia.</li>
    </ol>

    <p class="clausula"><span class="clausula-titulo">DÉCIMA SEGUNDA: FALTAS GRAVES.</span> Los contratantes de común
        acuerdo califican como graves las siguientes faltas del (de la) Trabajador(a) que constituirán justas causas
        para que el Empleador pueda dar por terminado unilateralmente en cualquier tiempo el presente contrato de
        trabajo, además de las establecidas en el literal a) del artículo 7o. del Decreto 2351 de 1965, en las
        disposiciones reglamentarias y en el reglamento interno de trabajo:</p>

    <ol>
        <li>El incumplimiento de cualquiera de las obligaciones especiales establecidas por el artículo 58 del C.S.T.
        </li>
        <li>El incumplimiento de cualquiera de las obligaciones estipuladas en la cláusula anterior.</li>
        <li>La realización de actividades en contravención de órdenes superiores o de reglamento, de carácter culposo,
            negligente, omisivo o doloso que atenten o incidan negativamente contra los intereses del Empleador o de
            terceros.</li>
        <li>El presentarse al trabajo bajo los efectos de bebidas embriagantes, drogas alucinógenas o similares o
            ingerir tales bebidas en el lugar de trabajo.</li>
        <li>El presentarse o estar dentro de la Empresa portando armas, salvo el caso en que el (la) Trabajador(a) por
            razón de sus labores esté facultado para ello.</li>
        <li>La no justificación de inasistencia del (de la) Trabajador(a) al cumplimiento de sus labores o el abandono
            de las mismas sin autorización del superior.</li>
        <li>El retardo hasta de quince (15) minutos a la hora de la entrada al trabajo sin excusa, por tercera vez.</li>
        <li>La inasistencia del (de la) Trabajador(a) a sus labores durante el día, sin excusa suficiente.</li>
        <li>La ocurrencia de cualquier acto de violencia, injuria o irrespeto injustificado por parte del (de la)
            Trabajador(a) hacia sus superiores, empleados de la Empresa o terceros, dentro de las instalaciones de la
            Empresa o fuera de ellas.</li>
        <li>La extralimitación de funciones que afecten o pongan en peligro los intereses del Empleador.</li>
        <li>La violación de la reserva de aspectos confidenciales puestos bajo la responsabilidad del (de la)
            Trabajador(a) o conocidos por éste en razón de su cargo.</li>
        <li>La utilización de información privilegiada adquirida en ejecución o con ocasión de sus funciones para la
            obtención de un provecho personal o para un tercero.</li>
        <li>La realización de actos que en cualquier forma entorpezcan o incidan negativamente en el normal desarrollo
            de las actividades patronales o en perjuicio de terceros.</li>
        <li>Cualquier acto de negligencia, descuido u omisión en que incurra el (la) Trabajador(a) en el ejercicio de
            las funciones propias de su cargo.</li>
        <li>La aceptación o solicitud de dádivas o beneficios por parte del (de la) Trabajador(a) a clientes y/o
            proveedores de la Empresa o a terceros, a cambio de tratamientos o servicios especiales.</li>
        <li>La existencia de faltante, descuadre en dinero o la pérdida, el extravío o deterioro de cualquier documento
            bajo la responsabilidad del (de la) Trabajador(a).</li>
        <li>El cobro de subsidios o beneficios a los que no tiene legalmente derecho (por hijos, padres, etc., bien sea
            porque son supuestos, han fallecido, no dependen económicamente del (de la) Trabajador(a), no cumplen la
            edad prevista en las disposiciones que rigen estos beneficios o no cumplen cualquiera de los demás
            requisitos necesarios para su válido reconocimiento).</li>
        <li>Sin ser de su competencia, la autorización o ejecución de operaciones que afecten los intereses del
            Empleador, o la negociación de bienes y/o mercancías o la negociación en cualquier forma de algún objeto de
            propiedad del Empleador.</li>
        <li>La presentación de cuentas de gastos ficticios o el reportar como cumplidas visitas o tareas no efectuadas.
        </li>
        <li>La consignación en la solicitud de empleo que se presenta cuando se va a ingresar a la Empresa, de datos
            falsos o el ocultamiento de información solicitada en el mismo documento.</li>
        <li>La omisión o consignación de datos en forma inexacta en los informes, relaciones, proyectos crediticios,
            balances, etc., que se presenten a consideración de los superiores, tendientes a obtener una aprobación o
            decisión que, a juicio del superior, habría sido diferente si los datos se ajustaran a la realidad. Para que
            se configure esta causal, basta la simple tentativa, no siendo necesario que se haya logrado la ejecución o
            que de la misma se derive un perjuicio real para la Empresa.</li>
        <li>El solicitar u obtener, de los empleados bajo su mando, concesiones o beneficios valiéndose de su posición.
        </li>
        <li>El facilitar el código de usuario y contraseña asignados al (a la) Trabajador(a) a compañeros de trabajo o
            terceros para ingresar a recursos informáticos.</li>
        <li>El envío, recibo o suministro de información en forma escrita, verbal, magnética o electrónica o en
            cualquier medio conocido o por conocerse a empleados o terceros sin la debida autorización del dueño de la
            información.</li>
        <li>La entrega de documentos sin el lleno de las formalidades legales y demás requisitos establecidos por el
            Empleador y el no aviso oportuno de estos hechos al inmediato superior.</li>
        <li>El embargo de cualquier acreencia laboral del (de la) Trabajador(a), siempre que no obtenga el levantamiento
            de dicha medida en un término máximo de treinta (30) días que se contarán a partir de la fecha en que el
            Empleador dé aviso escrito al (a la) Trabajador(a).</li>
        <li>El uso indebido por acción, omisión, error, negligencia o descuido de la firma autorizada, que incida
            negativamente contra los intereses de la Empresa o los ponga en peligro.</li>
        <li>El incumplimiento de las políticas, reglamentos, procedimientos y en general instrucciones de la Empresa.
        </li>
        <li>Las demás que se establezcan en el presente contrato, en la ley y las que se establezcan en las políticas
            internas de la compañía.</li>
        <li>No portar los documentos.</li>
    </ol>

    <p><strong>PARÁGRAFO:</strong> El (La) Trabajador(a) será responsable de todos los dineros, efectos de comercio,
        valores, recursos informáticos, documentos e información que reciba, tenga en su poder o maneje por razón de sus
        funciones, sin poder disponer de ellos en su beneficio o en beneficio de terceros, y deberá rendir estricta
        cuenta de ellos y de su manejo al EMPLEADOR, de acuerdo con las políticas/procedimientos que el Empleador tiene
        establecidos o establezca sobre el particular.</p>

    @if (($faltasGravesOrigen ?? null) === 'rit')
        <p>Así mismo, conforme al Reglamento Interno de Trabajo vigente de {{ $nombreEmpresa }}, se consideran también
            faltas graves las siguientes conductas:</p>
        <ol>
            @foreach (($faltasGravesGrave ?? []) as $conducta)
                <li>{{ $conducta }}</li>
            @endforeach
            @foreach (($faltasGravesGravisima ?? []) as $conducta)
                <li>{{ $conducta }}</li>
            @endforeach
        </ol>
    @endif

    <p class="clausula"><span class="clausula-titulo">DÉCIMA TERCERA: INCAPACIDADES.</span> Cuando se trate de comprobar
        incapacidades para el trabajo por causa de enfermedad de origen común o profesional o de accidente de trabajo o
        de accidente común, sólo se aceptarán como válidas las certificaciones expedidas por la respectiva entidad de
        seguridad social a la que el (la) Trabajador(a) se encuentre afiliado, siempre y cuando la incapacidad médica
        sea transcrita o avalada por la EPS o ARL a la cual se encuentre vinculado el (la) Trabajador(a).</p>

    <p class="clausula"><span class="clausula-titulo">DÉCIMA CUARTA: CONFIDENCIALIDAD.</span> El (La) Trabajador(a) se
        abstendrá, durante la vigencia del presente contrato o con posterioridad a su terminación por cualquier causa de
        revelar, suministrar, vender, arrendar, publicar, copiar, reproducir, remover, disponer, transferir y en general
        utilizar directa o indirectamente en favor propio o de otras personas en forma total o parcial, cualquiera que
        sea su finalidad, información confidencial o privilegiada del Empleador o de las sociedades filiales,
        subsidiarias, matrices, subordinadas, relacionadas o empresas, personas naturales, accionistas, clientes o
        terceros relacionados con éste, a la cual tenga acceso o de la cual tenga conocimiento en desarrollo de su cargo
        o con ocasión de éste sin que medie autorización previa, expresa y escrita del Empleador para el efecto.</p>

    <p>Las Partes declaran que es de carácter confidencial cualquier información, documento o procedimiento del
        Empleador o de las sociedades filiales, subsidiarias, matrices, subordinadas, relacionadas o empresas, personas
        naturales, accionistas, clientes o terceros relacionados con éste o sobre el cual tenga conocimiento el (la)
        Trabajador(a) en desarrollo de su cargo o con ocasión de éste, que no sea de conocimiento público, especialmente
        aquella información privilegiada respecto de operaciones, transacciones o negocios, o el valor de los mismos,
        que resulte sensible para la operación del Empleador o de terceros. En tal sentido, el (la) Trabajador(a) no
        sólo se obliga a no dar a conocer la información confidencial que llegue a conocer, sino que se abstendrá de
        utilizar dicha información para la obtención de un provecho personal o para terceros.</p>

    <p>El (La) Trabajador(a), a la terminación de su contrato de trabajo por cualquier causa, devolverá inmediatamente
        al Empleador cualquier documento, información o elemento que le haya sido entregado para efecto del cumplimiento
        de sus funciones.</p>

    <p>Las Partes acuerdan expresamente que el incumplimiento de las disposiciones contenidas en la presente cláusula es
        considerado como una falta grave y en tal sentido justa causa para la terminación del contrato de trabajo de
        acuerdo con lo dispuesto en el numeral 6 del literal a), Artículo 7 del Decreto Ley 2351 de 1965, en
        concordancia con el numeral 1 del Artículo 58 del C.S.T. Lo anterior sin perjuicio de las acciones civiles o
        penales que puedan emprenderse contra el (la) Trabajador(a) por parte del Empleador o de terceros como
        consecuencia de dicho incumplimiento.</p>

    <p class="clausula"><span class="clausula-titulo">DÉCIMA QUINTA: DERECHOS DE AUTOR.</span> El (La) Trabajador(a)
        cederá al Empleador todos los derechos patrimoniales de autor sobre las obras que cree en cumplimiento de sus
        funciones laborales, sobre las obras que cree utilizando las herramientas o materias primas de propiedad del
        Empleador, y/o sobre las obras creadas con ayuda o colaboración de este último o de otros compañeros de trabajo
        o de terceros vinculados de cualquier forma al Empleador.</p>

    <p>Como consecuencia de lo anterior, el Empleador tendrá derecho a solicitar, a su nombre o a nombre de terceros, y
        ante las autoridades correspondientes, el registro de las obras creadas por el (la) Trabajador(a) de conformidad
        con los supuestos del párrafo anterior. Asimismo, el Empleador podrá, durante el trámite de registro o en
        cualquier otro momento, explotar comercialmente la obra, lo que incluye, pero no se limita a, reproducirla en
        todas sus modalidades, transformarla, adaptarla, distribuirla, comunicarla públicamente y, en general,
        explotarla por cualquier medio conocido o por conocerse. Lo anterior sin perjuicio de los derechos morales de
        autor que reposan en cabeza del autor de la obra y serán respetados y reconocidos por el Empleador.</p>

    <p>Para dar cumplimiento a lo anterior, el (la) Trabajador(a) se compromete a facilitar el cumplimiento oportuno de
        las correspondientes formalidades y a firmar o a extender los poderes y los documentos necesarios para tal fin,
        en las condiciones y eventos solicitados por el Empleador, sin que éste quede obligado al pago de compensación
        alguna.</p>

    <p><strong>PARÁGRAFO:</strong> Teniendo en cuenta lo dispuesto en la Ley 23 de 1982 y a lo establecido en el numeral
        1° del artículo 132 del Código Sustantivo del Trabajo, las Partes acuerdan que la remuneración salarial
        reconocida por el Empleador como contraprestación por los servicios prestados por el (la) Trabajador(a), incluye
        y contiene la remuneración por la transferencia de los derechos patrimoniales de autor mencionados, toda vez que
        los objetos sobre los cuales recaen los derechos de propiedad intelectual son desarrollados por el (la)
        Trabajador(a) en virtud de su contrato de trabajo.</p>

    <p class="clausula"><span class="clausula-titulo">DÉCIMA SEXTA: PROPIEDAD INDUSTRIAL.</span> Todos los resultados
        obtenidos por el (la) Trabajador(a) en desarrollo de sus funciones o utilizando bienes, herramientas, equipos,
        materias primas, datos o medios conocidos o utilizados en razón de la labor que desempeña y en general
        utilizando cualquier apoyo o ayuda del Empleador o de personal vinculado al mismo, que sean susceptibles de ser
        protegidos por propiedad industrial se entenderán que pertenecen al Empleador, lo que incluye pero no se limita
        a patentes, modelos de utilidad, diseño industrial, secreto comercial, y cualquier otra forma de protección de
        la propiedad industrial disponible ahora o en el futuro.</p>

    <p>Como consecuencia de lo anterior, el Empleador como titular de los derechos de propiedad industrial tendrá
        derecho a obtener la protección que considere necesaria, para lo cual el (la) Trabajador(a) deberá facilitar el
        cumplimiento oportuno de las correspondientes formalidades y firmar o extender los poderes y los documentos
        necesarios para tal fin, en las condiciones y eventos solicitados por el Empleador.</p>

    <p><strong>PARÁGRAFO:</strong> Teniendo en cuenta lo dispuesto en la Decisión 486 de 2000 de la Comunidad Andina de
        Naciones y lo dispuesto en el numeral 1° del artículo 132 del Código Sustantivo del Trabajo, las Partes acuerdan
        que la remuneración salarial reconocida por el Empleador como contraprestación por los servicios prestados por
        el (la) Trabajador(a), incluye y contiene la remuneración por la transferencia de los derechos de propiedad
        industrial mencionados, toda vez que los objetos sobre los cuales recaen los derechos de propiedad intelectual
        son desarrollados por el (la) Trabajador(a) en virtud de su contrato de trabajo.</p>

    <p class="clausula"><span class="clausula-titulo">DÉCIMA SÉPTIMA: USO DE SOFTWARE.</span> El (La) Trabajador(a) se
        obliga a cumplir la Política de Uso de Software del Empleador. El (La) Trabajador(a) que llegue a tener
        conocimiento de cualquier uso indebido del software, deberá notificar este hecho a su respectivo e inmediato
        superior jerárquico.</p>

    <p>Así mismo, se le prohíbe al (a la) Trabajador(a) el uso de equipos de computación y del respectivo software para
        elaboración de trabajos personales.</p>

    <p class="clausula"><span class="clausula-titulo">DÉCIMA OCTAVA: CAPACITACIONES.</span> El (La) Trabajador(a) se
        obliga a recibir y asimilar la capacitación que el Empleador considere necesaria o conveniente para el correcto
        desempeño del cargo o para efecto de ascensos o promociones, o el cubrimiento de nuevas necesidades del
        Empleador, ya sea mediante cursos dictados en el lugar de trabajo o en otras instalaciones, directamente por el
        Empleador o por entidades especializadas en los temas que interesen a ella.</p>

    <p class="clausula"><span class="clausula-titulo">DÉCIMA NOVENA: AUTORIZACIÓN PARA LA GRABACIÓN DE IMAGEN.</span> El
        (La) Trabajador(a) autoriza por medio del presente documento al Empleador a utilizar su imagen para usos
        publicitarios del Empleador por tiempo indefinido y sin costo alguno. Igualmente, el (la) Trabajador(a) le cede
        expresamente al Empleador cualquier derecho sobre los documentos e imágenes obtenidas para dichos propósitos y
        susceptibles de protección mediante derecho de propiedad intelectual. Sin perjuicio de lo anterior, el (la)
        Trabajador(a) podrá oponerse por escrito al uso de su imagen.</p>

    <p class="clausula"><span class="clausula-titulo">VIGÉSIMA: MEDIOS DE TRABAJO.</span> El Empleador se obliga a
        proveer los medios de trabajo al (a la) Trabajador(a), dentro de los cuales podrán encontrarse: equipo de
        cómputo, papelería, entre otros, y demás elementos necesarios para el desarrollo de sus funciones, propiedad de
        la Empresa.</p>

    <p><strong>PARÁGRAFO PRIMERO:</strong> Las herramientas de trabajo antes citadas y las enunciadas en el acta
        &ldquo;Herramientas de Trabajo&rdquo;, se encuentran bajo la custodia y cuidado del (de la) Trabajador(a) y su
        pérdida, daño o destrucción serán responsabilidad del (de la) Trabajador(a); igualmente la pérdida, daño o
        destrucción de cualquiera de los elementos constituye una falta grave meritoria de la terminación del contrato
        de trabajo. Adicionalmente, las Partes acuerdan que las herramientas sólo podrán ser empleadas para las labores
        relacionadas con el contrato de trabajo que existe entre las Partes y para realizar sus funciones, por lo cual
        el darle una destinación o uso diferente o indebido por parte del (de la) Trabajador(a) a dichos elementos o la
        inobservancia de su deber de cuidado respecto de los mismos serán considerados falta grave de sus obligaciones,
        de conformidad con el literal a) numeral 6 del artículo 7 del decreto 2351 de 1965, el cual subrogó el artículo
        62 del C.S.T. El (La) Trabajador(a) autoriza que en caso de pérdida o extravío, daño o destrucción de cualquiera
        de los elementos por cualquier motivo, le sea deducido o descontado el valor comercial del bien de la sumas que
        se le adeuden por salarios, prestaciones sociales, vacaciones, intereses de cesantía, pagos de naturaleza
        extralegal, eventuales indemnizaciones y cualquier otra acreencia a que pueda tener derecho en vigencia del
        contrato de trabajo o al momento de terminación del contrato de trabajo por cualquier motivo.</p>

    <p><strong>PARÁGRAFO SEGUNDO:</strong> Las herramientas de trabajo entregadas al (a la) Trabajador(a) pertenecen al
        Empleador y por tanto el (la) Trabajador(a) deberá devolverlas cuando se le solicite y en todo caso al momento
        de terminación de su contrato de trabajo por cualquier causa. En caso de que el (la) Trabajador(a) incumpla su
        obligación de devolver las herramientas relacionadas al momento de la terminación de su contrato de trabajo,
        mediante el presente escrito autoriza de manera expresa al Empleador para que el valor total de las mismas se
        descuente, deduzca y/o compense del valor de la liquidación final de acreencias laborales a la cual tenga
        derecho el (la) Trabajador(a) incluyendo el valor de salarios, prestaciones sociales, vacaciones, interés de
        cesantía, pagos de naturaleza extralegal, eventuales indemnizaciones y cualquier otra acreencia a que pueda
        tener derecho al momento de terminación del contrato de trabajo por cualquier causa.</p>

    <p><strong>PARÁGRAFO TERCERO:</strong> El suministro de las herramientas de trabajo no constituye salario ni
        beneficio legal o extralegal alguno, pues son herramientas de trabajo, y en este sentido las Partes reiteran que
        no constituyen retribución alguna, ni salario en dinero o en especie, ni tendrán incidencia salarial,
        prestacional, parafiscal o indemnizatoria conforme lo previsto en los artículos 15 y 16 de la ley 50 de 1990 que
        subrogaron los artículos 128 y 129 del C.S.T. en concordancia con el artículo 17 de la ley 344 de 1996. Por
        tratarse de herramientas de trabajo que facilita el Empleador por mera liberalidad, el Empleador se reserva la
        facultad de modificar, adicionar o suprimir este suministro sin que constituya ningún tipo de desmejora para el
        (la) Trabajador(a), situación que el (la) Trabajador(a) desde ya acepta.</p>

    <p class="clausula"><span class="clausula-titulo">VIGÉSIMA PRIMERA: DATOS PERSONALES.</span> El (La) Trabajador(a)
        autoriza de manera libre y voluntaria al Empleador a recopilar, utilizar, transferir, almacenar, consultar,
        procesar, y en general a dar tratamiento a la información personal que éste ha suministrado al Empleador, de
        conformidad con lo dispuesto en la ley 1581 de 2012 y el Decreto 1377 de 2013, la cual se encuentra contenida en
        las bases de datos y archivo de propiedad del Empleador, para los fines internos que sean necesarios, tales como
        asuntos relacionados con su documento de identificación, número de identificación, nacionalidad, país de
        residencia, dirección, teléfono, genero, estado civil, fecha y lugar de nacimiento, correo electrónico
        corporativo y personal, salario, banco y cuenta bancaria. De igual forma, se faculta a transferir a la empresa,
        los datos básicos del (de la) Trabajador(a) para efectos de cumplir con las obligaciones legales y
        contractuales.</p>

    <p class="clausula"><span class="clausula-titulo">VIGÉSIMA SEGUNDA: DIRECCIÓN DEL TRABAJADOR.</span> El (La)
        Trabajador(a) para todos los efectos legales y en especial para la aplicación del parágrafo 1 del artículo 29 de
        la ley 789/02, norma que modificó el 65 del C.S.T., se compromete a informar al Empleador cualquier cambio en su
        dirección de residencia, teniéndose en todo caso como suya la dirección registrada en este contrato o la que el
        (la) Trabajador(a), utilizando su código de usuario y contraseña, haya actualizado en la intranet de la Empresa
        o le haya comunicado por medio escrito. Toda comunicación o notificación que el Empleador deba hacer al (a la)
        Trabajador(a) por virtud del desarrollo, ejecución o terminación de este contrato, se considera legal y válida
        si se hace a la última dirección de residencia que el (la) Trabajador(a) haya registrado o que se le entregue
        personalmente en las instalaciones de la Empresa, dejando el (la) Trabajador(a) constancia por medio de su firma
        de haberla recibido.</p>

    <p class="clausula"><span class="clausula-titulo">VIGÉSIMA TERCERA: CONOCIMIENTO Y ACTUALIZACIÓN DE
            POLÍTICAS.</span> El (La) Trabajador(a) de manera libre y espontánea declara que conoce y entiende la
        totalidad de las políticas/procedimientos que debe tener en cuenta para la correcta ejecución de sus funciones,
        los cuales ya han sido dados a conocer al (a la) Trabajador(a).</p>

    <p><strong>PARÁGRAFO PRIMERO:</strong> Las Partes de común acuerdo disponen que el (la) Trabajador(a) se obliga a
        cumplir la totalidad de los procedimientos antes mencionados y a actualizar constantemente su conocimiento de
        las disposiciones contenidas en tales documentos de acuerdo con las modificaciones o adiciones que sean
        realizadas y así mismo a investigar, enterarse y mantenerse actualizado de otros procedimientos, políticas y
        normas que se tienen establecidos o se establezcan en el futuro y resulten aplicables a la ejecución de sus
        funciones.</p>

    <p><strong>PARÁGRAFO SEGUNDO:</strong> Las Partes de común acuerdo disponen que el (la) Trabajador(a), en el caso de
        desempeñarse como jefe o persona que tiene a su cargo otros empleados, se obliga a propender porque sus
        subordinados se mantengan informados y actualizados sobre las normas, procedimientos y políticas del Empleador.
    </p>

    <p class="clausula"><span class="clausula-titulo">VIGÉSIMA CUARTA: AUTORIZACIÓN DE DESCUENTO.</span> El (La)
        Trabajador(a) autoriza a la Empresa para que, realice las deducciones o descuentos de sus acreencias laborales,
        por los conceptos y en la forma que establezca el artículo 150 del C.S.T., modificado por el artículo 22 de la
        ley 1911 de 2018.</p>

    <p><strong>PARÁGRAFO PRIMERO:</strong> El (La) Trabajador(a) podrá autorizar por escrito las deducciones o
        descuentos de sus acreencias laborales, conforme a lo consagrado en el artículo 151 del C.S.T., modificado por
        el artículo 19 de la ley 1429 de 2010.</p>

    <p><strong>PARÁGRAFO SEGUNDO:</strong> El (La) Trabajador(a) podrá autorizar por escrito a la Empresa para que, a la
        terminación del presente contrato, de las prestaciones sociales que le correspondan y cualquier suma adicional a
        su favor, deduzca el valor de las obligaciones o valores a su cargo y a favor de la Empresa por cualquier
        concepto.</p>

    <p><strong>PARÁGRAFO TERCERO:</strong> El (La) Trabajador(a) acepta desde ahora que si a la finalización del
        presente contrato de trabajo al Empleador se le presentaren circunstancias que le impidieren efectuar
        oportunamente la liquidación del contrato, el Empleador dispondrá de quince (15) días hábiles contados desde la
        aludida terminación, para tales efectos.</p>

    <p class="clausula"><span class="clausula-titulo">VIGÉSIMA QUINTA: CORREO CORPORATIVO.</span> En caso de que el
        Empleador suministre al Trabajador(a) una cuenta de correo electrónico, el Trabajador(a) se obliga a utilizarla
        única y exclusivamente como herramienta de trabajo para la correcta ejecución de las funciones propias de su
        cargo, sin que sea posible valerse de la cuenta de correo para la realización de actividades ajenas al objeto
        del presente contrato. Con base en lo anterior y teniendo en cuenta que se trata de una herramienta de trabajo,
        el (la) Trabajador(a) autoriza para que el Empleador revise las comunicaciones y mensajes de datos entrantes y
        salientes de dicha cuenta, actividad que se realiza en ejercicio del poder subordinante de conformidad con lo
        dispuesto en la Ley, teniendo en cuenta que el contenido de los mensajes de datos únicamente será utilizado
        dentro del marco de la relación laboral existente entre las Partes.</p>

    <p class="clausula"><span class="clausula-titulo">VIGÉSIMA SEXTA: SISTEMA DE AUTOCONTROL Y GESTIÓN DEL RIESGO DE
            LAVADO DE ACTIVOS Y FINANCIACIÓN DEL TERRORISMO. SARLAFT-SAGRILAFT.</span> El (la) Trabajador(a) declara que
        conoce y se obliga a cumplir de manera estricta las políticas, procedimientos, manuales, códigos y demás
        disposiciones internas adoptadas por EL EMPLEADOR en materia de prevención y control del riesgo de lavado de
        activos, financiación del terrorismo y demás delitos asociados, de conformidad con la normativa legal vigente y
        las instrucciones impartidas por las autoridades competentes.</p>

    <p>En desarrollo de lo anterior, el Trabajador(a) se compromete a:</p>

    <ol>
        <li>Abstenerse de realizar, directa o indirectamente, actos u operaciones que puedan constituir o facilitar
            actividades relacionadas con el lavado de activos, la financiación del terrorismo, la proliferación de armas
            de destrucción masiva o cualquier otra conducta ilícita.</li>
        <li>Suministrar información veraz, completa, comprobable y actualizada cuando le sea requerida por EL EMPLEADOR,
            especialmente aquella relacionada con su identificación, actividad económica, origen de recursos y demás
            datos necesarios para el cumplimiento de los sistemas de prevención de riesgos.</li>
        <li>Reportar de manera inmediata, confidencial y por los canales internos establecidos, cualquier operación
            inusual, sospechosa o conducta que pueda comprometer a LA EMPRESA en riesgos asociados al lavado de activos
            o financiación del terrorismo.</li>
        <li>Autorizar expresamente AL EMPLEADOR para verificar, consultar y reportar la información suministrada ante
            las bases de datos públicas y privadas, listas restrictivas nacionales e internacionales, así como ante las
            autoridades competentes, incluida la UIAF, cuando a ello hubiere lugar.</li>
    </ol>

    <p>El incumplimiento de las obligaciones aquí previstas, así como la vinculación del Trabajador(a) con actividades
        ilícitas relacionadas con lavado de activos o financiación del terrorismo, constituirá falta grave, en los
        términos del Reglamento Interno de Trabajo y del presente contrato, y dará lugar a la aplicación de las
        sanciones disciplinarias correspondientes, incluida la terminación del contrato de trabajo con justa causa, sin
        perjuicio de las acciones civiles, penales o administrativas a que haya lugar.</p>

    <p class="clausula"><span class="clausula-titulo">VIGÉSIMA SÉPTIMA: AUTORIZACIÓN PARA EL TRATAMIENTO DE DATOS
            PERSONALES.</span> El Trabajador(a), de manera libre, previa, expresa, informada e inequívoca, autoriza AL
        EMPLEADOR, en su calidad de responsables del tratamiento, para recolectar, almacenar, usar, circular,
        actualizar, suprimir y, en general, realizar el tratamiento de sus datos personales, incluidos los datos
        sensibles y los datos relacionados con su historia laboral, académica, familiar, de contacto, biométrica, de
        seguridad social y aquellos que se generen con ocasión de la ejecución del presente contrato de trabajo.</p>

    <p>El tratamiento de los datos personales tendrá como finalidades, entre otras, las siguientes:</p>

    <ol>
        <li>Dar cumplimiento a las obligaciones legales, contractuales y reglamentarias derivadas de la relación
            laboral.</li>
        <li>La administración del talento humano, nómina, seguridad social, bienestar laboral y prevención de riesgos.
        </li>
        <li>El cumplimiento de obligaciones ante autoridades administrativas, judiciales y de control.</li>
        <li>La gestión de procesos disciplinarios, evaluaciones de desempeño y control interno.</li>
        <li>El cumplimiento de políticas internas y del Reglamento Interno de Trabajo.</li>
        <li>Y cualquier otra finalidad legítima relacionada directa o indirectamente con la relación laboral.</li>
    </ol>

    <p>El Trabajador(a) declara conocer que sus datos personales serán tratados conforme a lo dispuesto en la Ley 1581
        de 2012, el Decreto 1377 de 2013 y demás normas concordantes, así como de acuerdo con la Política de Tratamiento
        de Datos Personales que pudiesen tener EL EMPLEADOR, la cual se encuentra a su disposición para consulta
        permanente.</p>

    <p>Así mismo, el Trabajador(a) reconoce que le asisten los derechos de conocer, actualizar, rectificar, suprimir sus
        datos personales y revocar la presente autorización, cuando ello sea procedente, mediante solicitud dirigida a
        EL EMPLEADOR, de conformidad con el procedimiento establecido en la normativa vigente y ante la autoridad
        competente, esto es, la Superintendencia de Industria y Comercio.</p>

    <p>La presente autorización permanecerá vigente durante la ejecución del contrato de trabajo y con posterioridad a
        su terminación, por el tiempo necesario para el cumplimiento de obligaciones legales, contractuales, contables,
        laborales, de seguridad social y de archivo.</p>

    <p class="clausula"><span class="clausula-titulo">VIGÉSIMA OCTAVA: EXÁMENES MÉDICOS.</span> El Trabajador(a) se
        someterá a la práctica de los exámenes médicos o sanitarios o pruebas de laboratorio que El Empleador exija en
        cualquier momento y se obliga a suministrar los documentos que con ocasión de la relación laboral ésta le exija.
    </p>

    <p><strong>PARÁGRAFO:</strong> En caso de que el (la) Trabajador(a) se niegue a practicar antes mencionados exámenes
        médicos o pruebas de laboratorio a solicitud del Empleador, constituye falta grave y será meritoria de la
        terminación del contrato de trabajo con justa causa.</p>

    <p class="clausula"><span class="clausula-titulo">VIGÉSIMA NOVENA: MODIFICACIÓN DE LAS CONDICIONES LABORALES.</span>
        El (La) Trabajador(a) acepta desde ahora expresamente todas las modificaciones de sus condiciones laborales
        determinadas por el Empleador en ejercicio de su poder subordinante, tales como jornadas de trabajo, el lugar de
        prestación de servicio, el cargo u oficio y/o funciones y la forma de remuneración, siempre que tales
        modificaciones no afecten su honor, dignidad o sus derechos mínimos, ni impliquen desmejoras sustanciales o
        graves perjuicios para él, de conformidad con lo dispuesto por el Art. 23 del C.S.T. modificado por el Art. 1º
        de la Ley 50/90.</p>

    <p class="clausula"><span class="clausula-titulo">TRIGÉSIMA: NORMATIVIDAD.</span> Las Partes, conforme lo aceptan en
        este contrato, se someterán a todo lo estatuido en el C.S.T. y demás normas sobre derechos, obligaciones y
        prohibiciones emanados del contrato de trabajo.</p>

    <p class="clausula"><span class="clausula-titulo">TRIGÉSIMA PRIMERA: EFECTOS.</span> El presente contrato reemplaza
        en su integridad y deja sin efecto cualquier otro contrato, acuerdo u oferta, verbal o escrito, celebrado entre
        las Partes con anterioridad, pudiendo las Partes convenir por escrito modificaciones al mismo, las que formarán
        parte integrante de este contrato.</p>

    <p class="clausula"><span class="clausula-titulo">TRIGÉSIMA SEGUNDA: VIGENCIA.</span> Las Partes acuerdan que el
        presente contrato deja sin efectos cualquier acuerdo verbal o escrito celebrado entre las partes con
        anterioridad en cuanto le resulte contrario.</p>

    <p>Para constancia de lo anterior se firma por las Partes en {{ $lugarContratacion }}, el día {{ $fechaFirma }}.
    </p>

    <table class="firma">
        <tr>
            <td>
                {{ $nombreEmpresa }}<br>
                NIT. {{ $nit }}<br>
                {{ $representanteLegal }}<br>
                REPRESENTANTE LEGAL
            </td>
            <td>
                {{ $nombreTrabajador }}<br>
                {{ ucfirst($tipoDocumentoLabel) }} No. {{ $numeroDocumento }}
            </td>
        </tr>
    </table>

</body>

</html>
