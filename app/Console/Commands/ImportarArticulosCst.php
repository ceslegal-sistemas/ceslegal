<?php

namespace App\Console\Commands;

use App\Models\ArticuloLegal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importa artículos clave del Código Sustantivo del Trabajo (CST),
 * Ley 1010/2006 (acoso laboral) y Ley 361/1997 (discapacidad)
 * como artículos universales (empresa_id = null) en articulos_legales.
 *
 * Es idempotente: inserta/actualiza por (codigo, fuente).
 *
 * Uso:
 *   php artisan cst:importar            # Importa/actualiza todo
 *   php artisan cst:importar --force    # Fuerza regenerar embeddings
 */
class ImportarArticulosCst extends Command
{
    protected $signature   = 'cst:importar {--force : Regenerar embeddings aunque ya existan}';
    protected $description = 'Importa artículos del CST, Ley 1010 y Ley 361 con embeddings para RAG';

    private array $articulos = [

        // ──────────────────────────────────────────────────────────────────────
        // CONTRATO DE TRABAJO — Elementos, buena fe, obligaciones generales
        // ──────────────────────────────────────────────────────────────────────
        [
            'codigo'         => 'Art. 10 CST',
            'titulo'         => 'Igualdad de los trabajadores — no discriminación',
            'categoria'      => 'principios',
            'fuente'         => 'CST',
            'orden'          => 10,
            'texto_completo' => 'Todos los trabajadores son iguales ante la ley, tienen la misma protección y garantías, y en consecuencia, queda abolida toda distinción jurídica entre los trabajadores por razón del carácter intelectual o material de la labor, su forma o retribución, salvo las excepciones establecidas en la ley.',
        ],
        [
            'codigo'         => 'Art. 22 CST',
            'titulo'         => 'Definición de contrato de trabajo',
            'categoria'      => 'contrato',
            'fuente'         => 'CST',
            'orden'          => 22,
            'texto_completo' => 'Contrato de trabajo es aquel por el cual una persona natural se obliga a prestar un servicio personal a otra persona, natural o jurídica, bajo la continuada dependencia o subordinación de la segunda y mediante remuneración. Quien presta el servicio se denomina trabajador, quien lo recibe y remunera, empleador, y la remuneración, cualquiera que sea su forma, salario.',
        ],
        [
            'codigo'         => 'Art. 23 CST',
            'titulo'         => 'Elementos esenciales del contrato de trabajo',
            'categoria'      => 'contrato',
            'fuente'         => 'CST',
            'orden'          => 23,
            'texto_completo' => 'Para que haya contrato de trabajo se requiere que concurran estos tres elementos esenciales: a) La actividad personal del trabajador, es decir, realizada por sí mismo; b) La continuada subordinación o dependencia del trabajador respecto del empleador, que faculta a éste para exigirle el cumplimiento de órdenes, en cualquier momento, en cuanto al modo, tiempo o cantidad de trabajo, e imponerle reglamentos, la cual debe mantenerse por todo el tiempo de duración del contrato; todo ello sin que afecte el honor, la dignidad y los derechos mínimos del trabajador en concordancia con los contratos colectivos, convenciones colectivas y fallos arbitrales; c) Un salario como retribución del servicio. Una vez reunidos los tres elementos de que trata este artículo, se entiende que existe contrato de trabajo y no deja de serlo por razón del nombre que se le dé ni de otras condiciones o modalidades que se le agreguen.',
        ],
        [
            'codigo'         => 'Art. 55 CST',
            'titulo'         => 'Ejecución de buena fe del contrato de trabajo',
            'categoria'      => 'principios',
            'fuente'         => 'CST',
            'orden'          => 55,
            'texto_completo' => 'El contrato de trabajo, como todos los contratos, debe ejecutarse de buena fe y, por consiguiente, obliga no sólo a lo que en él se expresa sino a todas las cosas que emanan precisamente de la naturaleza de la relación jurídica o que por ley pertenecen a ella.',
        ],
        [
            'codigo'         => 'Art. 56 CST',
            'titulo'         => 'Obligaciones generales de las partes',
            'categoria'      => 'obligaciones',
            'fuente'         => 'CST',
            'orden'          => 56,
            'texto_completo' => 'De modo general, incumben al empleador obligaciones de protección y de seguridad para con los trabajadores, y a éstos obligaciones de obediencia y fidelidad para con el empleador.',
        ],

        // ──────────────────────────────────────────────────────────────────────
        // OBLIGACIONES Y PROHIBICIONES DE LAS PARTES
        // ──────────────────────────────────────────────────────────────────────
        [
            'codigo'         => 'Art. 57 CST',
            'titulo'         => 'Obligaciones especiales del empleador',
            'categoria'      => 'obligaciones',
            'fuente'         => 'CST',
            'orden'          => 57,
            'texto_completo' => 'Son obligaciones especiales del empleador: 1. Poner a disposición de los trabajadores los instrumentos adecuados y las materias primas necesarias para la realización de las labores. 2. Procurar a los trabajadores locales apropiados y elementos adecuados de protección contra accidentes y enfermedades profesionales. 3. Prestar de inmediato los primeros auxilios en caso de accidentes o de enfermedad. 4. Pagar la remuneración pactada en las condiciones, períodos y lugares convenidos. 5. Guardar absoluto respeto a la dignidad personal del trabajador, a sus creencias y sentimientos. 6. Conceder al trabajador las licencias necesarias para el ejercicio del sufragio; para el desempeño de cargos oficiales transitorios de forzosa aceptación; en caso de grave calamidad doméstica debidamente comprobada; para desempeñar comisiones sindicales inherentes a la organización o para asistir al entierro de sus compañeros. 7. Dar al trabajador que lo solicite, a la expiración del contrato, una certificación en que consten el tiempo de servicio, la índole de la labor y el salario devengado. 8. Pagar al trabajador los gastos razonables de venida y de regreso, si para prestar sus servicios lo hizo cambiar de residencia. 9. Cumplir el reglamento y mantener el orden, la moralidad y el respeto a las leyes. 10. Conceder licencia remunerada al trabajador que esté en periodo de lactancia, para amamantar a su hijo.',
        ],
        [
            'codigo'         => 'Art. 58 CST',
            'titulo'         => 'Obligaciones especiales del trabajador',
            'categoria'      => 'obligaciones',
            'fuente'         => 'CST',
            'orden'          => 58,
            'texto_completo' => 'ARTICULO 58. OBLIGACIONES ESPECIALES DEL TRABAJADOR. Son obligaciones especiales del trabajador: 1a. Realizar personalmente la labor, en los términos estipulados; observar los preceptos del reglamento y acatar y cumplir las órdenes e instrucciones que de modo particular la impartan el empleador o sus representantes, según el orden jerárquico establecido. 2a. No comunicar con terceros, salvo la autorización expresa, las informaciones que tenga sobre su trabajo, especialmente sobre las cosas que sean de naturaleza reservada o cuya divulgación pueda ocasionar perjuicios al empleador, lo que no obsta para denunciar delitos comunes o violaciones del contrato o de las normas legales del trabajo ante las autoridades competentes. 3a. Conservar y restituir un buen estado, salvo el deterioro natural, los instrumentos y útiles que le hayan sido facilitados y las materias primas sobrantes. 4a. Guardar rigurosamente la moral en las relaciones con sus superiores y compañeros. 5a. Comunicar oportunamente al empleador las observaciones que estime conducentes a evitarle daños y perjuicios. 6a. Prestar la colaboración posible en casos de siniestro o de riesgo inminente que afecten o amenacen las personas o cosas de la empresa o establecimiento. 7a. Observar con suma diligencia y cuidado las instrucciones y órdenes preventivas de accidentes o de enfermedades profesionales. 8a. <Numeral adicionado por el artículo 4o. de la Ley 1468 de 2011.> La trabajadora en estado de embarazo debe empezar a disfrutar la licencia remunerada consagrada en el numeral 1 del artículo 236, al menos una semana antes de la fecha probable del parto.',
        ],
        [
            'codigo'         => 'Art. 59 CST',
            'titulo'         => 'Prohibiciones al empleador',
            'categoria'      => 'prohibiciones',
            'fuente'         => 'CST',
            'orden'          => 59,
            'texto_completo' => 'Se prohíbe al empleador: 1. Deducir, retener o compensar suma alguna del monto de los salarios y prestaciones en dinero que corresponda a los trabajadores, sin autorización previa escrita de éstos para cada caso, o sin mandamiento judicial. 2. Obligar a los trabajadores a adquirir mercancías o víveres en almacenes o proveedurías que establezca el empleador. 3. Exigir o aceptar dinero del trabajador como gratificación para que se le admita en el trabajo. 4. Limitar o presionar en cualquier forma a los trabajadores en el ejercicio de su derecho de asociación. 5. Imponer a los trabajadores obligaciones de carácter religioso o político o dificultarles o impedirles el ejercicio del derecho al sufragio. 6. Hacer, autorizar, o tolerar propaganda política en los sitios de trabajo. 7. Hacer o permitir todo género de juegos de suerte y azar en los sitios de trabajo. 8. Ejecutar o autorizar cualquier acto que vulnere o restrinja los derechos de los trabajadores o que ofenda su dignidad.',
        ],
        [
            'codigo'         => 'Art. 60 CST',
            'titulo'         => 'Prohibiciones al trabajador',
            'categoria'      => 'prohibiciones',
            'fuente'         => 'CST',
            'orden'          => 60,
            'texto_completo' => 'Se prohíbe al trabajador: 1. Sustraer de la fábrica, taller o establecimiento, los útiles de trabajo, las materias primas o productos elaborados, sin permiso del empleador. 2. Presentarse al trabajo en estado de embriaguez o bajo la influencia de narcóticos o de drogas enervantes. 3. Conservar armas de cualquier clase en el sitio de trabajo, a excepción de las que con autorización legal puedan llevar los celadores. 4. Faltar al trabajo sin justa causa de impedimento o sin permiso del empleador. 5. Disminuir intencionalmente el ritmo de ejecución del trabajo, suspender labores, promover suspensiones intempestivas del trabajo. 6. Hacer colectas, rifas y suscripciones o cualquier clase de propaganda en los lugares de trabajo. 7. Coartar la libertad para trabajar o no trabajar, o para afiliarse o no con una asociación sindical. 8. Usar los útiles o herramientas suministrados por el empleador en objetos distintos del trabajo contratado.',
        ],

        // ──────────────────────────────────────────────────────────────────────
        // TERMINACIÓN DEL CONTRATO
        // ──────────────────────────────────────────────────────────────────────
        [
            'codigo'         => 'Art. 61 CST',
            'titulo'         => 'Terminación del contrato de trabajo — modos',
            'categoria'      => 'terminacion',
            'fuente'         => 'CST',
            'orden'          => 61,
            'texto_completo' => 'El contrato de trabajo termina: a) Por muerte del trabajador; b) Por mutuo consentimiento; c) Por expiración del plazo fijo pactado; d) Por terminación de la obra o labor contratada; e) Por liquidación o clausura definitiva de la empresa o establecimiento; f) Por suspensión de actividades por parte del empleador durante más de ciento veinte (120) días; g) Por sentencia ejecutoriada; h) Por decisión unilateral en los casos de los artículos 7º del Decreto-ley 2351 de 1965 y 6º de esta misma ley; i) Por no regresar el trabajador a su empleo, al desaparecer las causas de la suspensión del contrato.',
        ],
        [
            'codigo'         => 'Art. 62 CST',
            'titulo'         => 'Terminación del contrato por justa causa — causales del empleador',
            'categoria'      => 'terminacion',
            'fuente'         => 'CST',
            'orden'          => 62,
            'texto_completo' => 'Son justas causas para dar por terminado unilateralmente el contrato de trabajo por parte del empleador: 1. El haberle suministrado el trabajador al empleador datos o documentos falsos a la celebración del contrato. 2. Todo acto de violencia, injuria, malos tratamientos o grave indisciplina en que incurra el trabajador en sus labores, contra el empleador, los miembros de su familia, el personal directivo o los compañeros de trabajo. 3. Todo acto grave de violencia, injuria o malos tratamientos en que incurra el trabajador fuera del servicio, en contra del empleador, de los miembros de su familia o de sus representantes y socios, jefes de taller, vigilantes o celadores. 4. Todo daño material causado intencionalmente a los edificios, obras, maquinaria y materias primas, instrumentos y demás objetos relacionados con el trabajo, y toda grave negligencia que ponga en peligro la seguridad de las personas o de las cosas. 5. Todo acto inmoral o delictuoso que el trabajador cometa en el taller, establecimiento o lugar de trabajo, o en el desempeño de sus labores. 6. Cualquier violación grave de las obligaciones o prohibiciones especiales que incumben al trabajador de acuerdo con los artículos 58 y 60 del Código Sustantivo del Trabajo, o cualquier falta grave calificada como tal en pactos o convenciones colectivas, fallos arbitrales, contratos individuales o reglamentos. 7. La detención preventiva del trabajador por más de treinta (30) días, a menos que posteriormente sea absuelto. 8. El que el trabajador revele los secretos técnicos o comerciales o dé a conocer asuntos de carácter reservado, con perjuicio de la empresa. 9. El deficiente rendimiento en el trabajo en relación con la capacidad del trabajador y con el rendimiento promedio en labores análogas, cuando no se corrija en un plazo razonable a pesar del requerimiento del empleador. 10. La sistemática inejecución, sin razones válidas, por parte del trabajador, de las obligaciones convencionales o legales. 11. Todo vicio del trabajador que perturbe la disciplina del establecimiento. 12. La renuencia sistemática del trabajador a aceptar las medidas preventivas, profilácticas o curativas, prescritas por el médico del empleador o por las autoridades. 13. La ineptitud del trabajador para realizar la labor encomendada. 14. El reconocimiento al trabajador de la pensión de jubilación o invalidez estando al servicio de la empresa. 15. La enfermedad contagiosa o crónica del trabajador, que no tenga carácter de profesional, así como cualquier otra enfermedad o lesión que lo incapacite para el trabajo, cuya curación no haya sido posible durante ciento ochenta (180) días.',
        ],
        [
            'codigo'         => 'Art. 64 CST',
            'titulo'         => 'Terminación unilateral del contrato de trabajo sin justa causa — indemnización (Ley 789/2002)',
            'categoria'      => 'terminacion',
            'fuente'         => 'CST',
            'orden'          => 64,
            'texto_completo' => 'ARTICULO 64. TERMINACION UNILATERAL DEL CONTRATO DE TRABAJO SIN JUSTA CAUSA. <Artículo modificado por el artículo 28 de la Ley 789 de 2002.> En todo contrato de trabajo va envuelta la condición resolutoria por incumplimiento de lo pactado, con indemnización de perjuicios a cargo de la parte responsable. Esta indemnización comprende el lucro cesante y el daño emergente. En caso de terminación unilateral del contrato de trabajo sin justa causa comprobada, por parte del empleador o si éste da lugar a la terminación unilateral por parte del trabajador por alguna de las justas causas contempladas en la ley, el primero deberá al segundo una indemnización en los términos que a continuación se señalan: En los contratos a término fijo, el valor de los salarios correspondientes al tiempo que faltare para cumplir el plazo estipulado del contrato; o el del lapso determinado por la duración de la obra o la labor contratada, caso en el cual la indemnización no será inferior a quince (15) días. En los contratos a término indefinido la indemnización se pagará así: a) Para trabajadores que devenguen un salario inferior a diez (10) salarios mínimos mensuales legales: 1. Treinta (30) días de salario cuando el trabajador tuviere un tiempo de servicio no mayor de un (1) año. 2. Si el trabajador tuviere más de un (1) año de servicio continuo se le pagarán veinte (20) días adicionales de salario sobre los treinta (30) básicos del numeral 1, por cada uno de los años de servicio subsiguientes al primero y proporcionalmente por fracción; b) Para trabajadores que devenguen un salario igual o superior a diez (10) salarios mínimos legales mensuales: 1. Veinte (20) días de salario cuando el trabajador tuviere un tiempo de servicio no mayor de un (1) año. 2. Si el trabajador tuviere más de un (1) año de servicio continuo, se le pagarán quince (15) días adicionales de salario sobre los veinte (20) días básicos del numeral 1 anterior, por cada uno de los años de servicio subsiguientes al primero y proporcionalmente por fracción. PARÁGRAFO TRANSITORIO. Los trabajadores que al momento de entrar en vigencia la presente ley, tuvieren diez (10) o más años al servicio continuo del empleador, se les aplicará la tabla de indemnización establecida en los literales b), c) y d) del artículo 6o. de la Ley 50 de 1990, exceptuando el parágrafo transitorio, el cual se aplica únicamente para los trabajadores que tenían diez (10) o más años el primero de enero de 1991.',
        ],

        // ──────────────────────────────────────────────────────────────────────
        // REGLAMENTO INTERNO DE TRABAJO Y SANCIONES DISCIPLINARIAS
        // ──────────────────────────────────────────────────────────────────────
        [
            'codigo'         => 'Art. 104 CST',
            'titulo'         => 'Definición de reglamento de trabajo',
            'categoria'      => 'reglamento_interno',
            'fuente'         => 'CST',
            'orden'          => 104,
            'texto_completo' => 'ARTICULO 104. DEFINICION. Reglamento de trabajo es el conjunto de normas que determinan las condiciones a que deben sujetarse el empleador y sus trabajadores en la prestación del servicio.',
        ],
        [
            'codigo'         => 'Art. 105 CST',
            'titulo'         => 'Obligación de adoptar reglamento de trabajo',
            'categoria'      => 'reglamento_interno',
            'fuente'         => 'CST',
            'orden'          => 105,
            'texto_completo' => 'ARTICULO 105. OBLIGACION DE ADOPTARLO. 1. Está obligado a tener un reglamento de trabajo todo empleador que ocupe más de cinco (5) trabajadores de carácter permanente en empresas comerciales, o más de diez (10) en empresas industriales, o más de veinte (20) en empresas agrícolas, ganaderas o forestales. 2. En empresas mixtas, la obligación de tener un reglamento de trabajo existe cuando el empleador ocupe más de diez (10) trabajadores.',
        ],
        [
            'codigo'         => 'Art. 108 CST',
            'titulo'         => 'Contenido del reglamento de trabajo',
            'categoria'      => 'reglamento_interno',
            'fuente'         => 'CST',
            'orden'          => 108,
            'texto_completo' => 'ARTICULO 108. CONTENIDO. El reglamento debe contener disposiciones normativas de los siguientes puntos: 1. Indicación del empleador y del establecimiento o lugares de trabajo comprendidos por el reglamento. 2. Condiciones de admisión, aprendizaje y período de prueba. 3. Trabajadores accidentales o transitorios. 4. Horas de entrada y salida de los trabajadores; horas en que principia y termina cada turno si el trabajo se efectúa por equipos; tiempo destinado para las comidas y períodos de descanso durante la jornada. 5. Horas extras y trabajo nocturno; su autorización, reconocimiento y pago. 6. Días de descanso legalmente obligatorio; horas o días de descanso convencional o adicional; vacaciones remuneradas; permisos, especialmente lo relativo a desempeño de comisiones sindicales, asistencia al entierro de compañeros de trabajo y grave calamidad doméstica. 7. Salario mínimo legal o convencional. 8. Lugar, día, hora de pagos y período que los regula. 9. Tiempo y forma en que los trabajadores deben sujetarse a los servicios médicos que el empleador suministre. 10. Prescripciones de orden y seguridad. 11. Indicaciones para evitar que se realicen los riesgos profesionales e instrucciones para prestar los primeros auxilios en caso de accidente. 12. Orden jerárquico de los representantes del empleador, jefes de sección, capataces y vigilantes. 13. Especificaciones de las labores que no deben ejecutar las mujeres y los menores de dieciséis (16) años. 14. Normas especiales que se deben guardar en las diversas clases de labores, de acuerdo con la edad y el sexo de los trabajadores, con miras a conseguir la mayor higiene, regularidad y seguridad en el trabajo. 15. Obligaciones y prohibiciones especiales para el empleador y los trabajadores. 16. Escala de faltas y procedimientos para su comprobación; escala de sanciones disciplinarias y forma de aplicación de ellas. 17. La persona o personas ante quienes se deben presentar los reclamos del personal y tramitación de éstos, expresando que el trabajador o los trabajadores pueden asesorarse del sindicato respectivo. 18. Prestaciones adicionales a las legalmente obligatorias, si existieren. 19. Publicación y vigencia del reglamento.',
        ],
        [
            'codigo'         => 'Art. 111 CST',
            'titulo'         => 'Sanciones disciplinarias — límites y dignidad del trabajador',
            'categoria'      => 'procedimiento_disciplinario',
            'fuente'         => 'CST',
            'orden'          => 111,
            'texto_completo' => 'ARTICULO 111. SANCIONES DISCIPLINARIAS. Las sanciones disciplinarias no pueden consistir en penas corporales, ni en medidas lesivas de la dignidad del trabajador.',
        ],
        [
            'codigo'         => 'Art. 112 CST',
            'titulo'         => 'Suspensión del trabajo — límites de duración',
            'categoria'      => 'procedimiento_disciplinario',
            'fuente'         => 'CST',
            'orden'          => 112,
            'texto_completo' => 'ARTICULO 112. SUSPENSION DEL TRABAJO. Cuando la sanción consista en suspensión del trabajo, ésta no puede exceder de ocho (8) días por la primera vez, ni de dos (2) meses en caso de reincidencia de cualquier grado.',
        ],
        [
            'codigo'         => 'Art. 113 CST',
            'titulo'         => 'Multas disciplinarias — límites y destinación',
            'categoria'      => 'procedimiento_disciplinario',
            'fuente'         => 'CST',
            'orden'          => 113,
            'texto_completo' => 'ARTICULO 113. MULTAS. 1. Las multas que se prevean, sólo pueden causarse por retrasos o faltas al trabajo sin excusa suficiente; no puede exceder de la quinta (5a) parte del salario de un (1) día, y su importe se consigna en cuenta especial para dedicarse exclusivamente a premios o regalos para los trabajadores del establecimiento. 2. El empleador puede descontar las multas del valor de los salarios. 3. La imposición de una multa no impide que el empleador prescinda del pago del salario correspondiente al tiempo dejado de trabajar.',
        ],
        [
            'codigo'         => 'Art. 114 CST',
            'titulo'         => 'Sanciones no previstas — prohibición al empleador',
            'categoria'      => 'procedimiento_disciplinario',
            'fuente'         => 'CST',
            'orden'          => 114,
            'texto_completo' => 'ARTICULO 114. SANCIONES NO PREVISTAS. El empleador no puede imponer a sus trabajadores sanciones no previstas en el reglamento, en pacto, en convención colectiva, en fallo arbitral o en contrato individual.',
        ],
        [
            'codigo'         => 'Art. 115 CST',
            'titulo'         => 'Procedimiento para sanciones disciplinarias — debido proceso (Ley 2466 de 2025)',
            'categoria'      => 'procedimiento_disciplinario',
            'fuente'         => 'CST',
            'orden'          => 115,
            'texto_completo' => 'ARTICULO 115. PROCEDIMIENTO PARA SANCIONES. <Artículo modificado por el artículo 7 de la Ley 2466 de 2025.> En todas las actuaciones para aplicar sanciones disciplinarias, se deberán aplicar las garantías del debido proceso, esto es, como mínimo los siguientes principios: dignidad, presunción de inocencia, in dubio pro disciplinado, proporcionalidad, derecho a la defensa, contradicción y controversia de las pruebas, intimidad, lealtad y buena fe, imparcialidad, respeto al buen nombre y a la honra, y non bis in idem. También se deberá aplicar como mínimo el siguiente procedimiento: 1. Comunicación formal de la apertura del proceso al trabajador o trabajadora. 2. La indicación de hechos, conductas u omisiones que motivan el proceso, la cual deberá ser por escrito. 3. El traslado al trabajador o trabajadora de todas y cada una de las pruebas que fundamentan los hechos, conductas u omisiones del proceso. 4. La indicación de un término durante el cual el trabajador o trabajadora pueda manifestarse frente a los motivos del proceso, controvertir las pruebas y allegar las que considere necesarias para sustentar su defensa, el cual en todo caso no podrá ser inferior a 5 días. En caso de que la defensa del trabajador frente a los hechos, conductas u omisiones que motivaron el proceso sea verbal, se hará un acta en la que se transcribirá la versión o descargos rendidos por el trabajador. 5. El pronunciamiento definitivo debidamente motivado identificando específicamente la(s) causa(s) o motivo(s) de la decisión. 6. De ser el caso, la imposición de una sanción proporcional a los hechos u omisiones que la motivaron. 7. La posibilidad del trabajador de impugnar la decisión. PARÁGRAFO 1o. Este procedimiento deberá realizarse en un término razonable atendiendo al principio de inmediatez, sin perjuicio de que esté estipulado un término diferente en Convención Colectiva, Laudo Arbitral o Reglamento Interno de Trabajo. PARÁGRAFO 2o. Si el trabajador o trabajadora se encuentra afiliado a una organización sindical, podrá estar asistido o acompañado por uno (1) o dos (2) representantes del sindicato que sean trabajadores de la empresa y se encuentren presentes al momento de la diligencia, y estos tendrán el derecho de velar por el cumplimiento de los principios de derecho de defensa y debido proceso del trabajador sindicalizado, dando fe de ellos al final del procedimiento. PARÁGRAFO 3o. El trabajador con discapacidad deberá contar con medidas y ajustes razonables que garanticen la comunicación y comprensión recíproca en el marco del debido proceso. PARÁGRAFO 4o. El empleador deberá actualizar el Reglamento Interno de Trabajo, acorde con los parámetros descritos dentro de los doce (12) meses siguientes a la entrada en vigencia de la presente Ley. PARÁGRAFO 5o. Este procedimiento podrá realizarse utilizando las tecnologías de la información y las comunicaciones, siempre y cuando el trabajador cuente con estas herramientas a disposición. PARÁGRAFO 6o. Este procedimiento no aplicará a los trabajadores del hogar, ni a las micro y pequeñas empresas de menos de diez (10) trabajadores, definidas en el Decreto 957 de 2019. Este tipo de empleadores solo tendrá la obligación de escuchar previamente al trabajador sobre los hechos que se le imputan, respetando las garantías del derecho de defensa y del debido proceso. Dentro de los doce (12) meses siguientes a la entrada en vigencia de la presente ley, el Ministerio del Trabajo impulsará un programa de acompañamiento y fortalecimiento a micro y pequeñas empresas para garantizar la aplicación del debido proceso.',
        ],
        // Art. 116 CST — DEROGADO por el parágrafo 3o. del Art. 65 de la Ley 1429 de 2010.
        // No se incluye para evitar que la IA cite legislación derogada como vigente.

        // ──────────────────────────────────────────────────────────────────────
        // GRUPOS PROTEGIDOS — MATERNIDAD
        // ──────────────────────────────────────────────────────────────────────
        [
            'codigo'         => 'Art. 236 CST',
            'titulo'         => 'Licencia en la época del parto e incentivos para la atención del recién nacido (Ley 2114/2021)',
            'categoria'      => 'grupos_protegidos',
            'fuente'         => 'CST',
            'orden'          => 236,
            'texto_completo' => 'ARTÍCULO 236. LICENCIA EN LA ÉPOCA DEL PARTO E INCENTIVOS PARA LA ADECUADA ATENCIÓN Y CUIDADO DEL RECIÉN NACIDO. <Artículo modificado por el artículo 2 de la Ley 2114 de 2021.> 1. Toda trabajadora en estado de embarazo tiene derecho a una licencia de dieciocho (18) semanas en la época de parto, remunerada con el salario que devengue al momento de iniciar su licencia. 2. Si se tratare de un salario que no sea fijo como en el caso del trabajo a destajo o por tarea, se tomará en cuenta el salario promedio devengado por la trabajadora en el último año de servicio, o en todo el tiempo si fuere menor. 3. Para los efectos de la licencia de que trata este artículo, la trabajadora debe presentar al empleador un certificado médico, en el cual debe constar: a) El estado de embarazo de la trabajadora; b) La indicación del día probable del parto, y c) La indicación del día desde el cual debe empezar la licencia, teniendo en cuenta que, por lo menos, ha de iniciarse dos semanas antes del parto. Los beneficios incluidos en este artículo, y el artículo 239 de la presente ley, no excluyen a los trabajadores del sector público. 4. Todas las provisiones y garantías establecidas en la presente ley para la madre biológica se hacen extensivas en los mismos términos y en cuanto fuere procedente a la madre adoptante, o al padre que quede a cargo del recién nacido sin apoyo de la madre, sea por enfermedad, abandono o muerte, asimilando la fecha del parto a la de la entrega oficial del menor que se ha adoptado, o del que adquiere custodia justo después del nacimiento. En ese sentido, la licencia materna se extiende al padre en caso de fallecimiento, abandono o enfermedad de la madre; el empleador del padre del niño le concederá una licencia de duración equivalente al tiempo que falta para expirar el periodo de la licencia posterior al parto concedida a la madre. 5. La licencia de maternidad para madres de niños prematuros, tendrá en cuenta la diferencia entre la fecha gestacional y el nacimiento a término, las cuales serán sumadas a las dieciocho (18) semanas que se establecen en la presente ley. Cuando se trate de madres con parto múltiple o madres de un hijo con discapacidad, la licencia se ampliará en dos semanas más. 6. La trabajadora que haga uso de la licencia en la época del parto tomará las dieciocho (18) semanas de licencia a las que tiene derecho, de la siguiente manera: a) Licencia de maternidad preparto. Esta será de una (1) semana con anterioridad a la fecha probable del parto debidamente acreditada. Si por alguna razón médica la futura madre requiere una semana adicional previa al parto podrá gozar de las dos (2) semanas, con dieciséis (16) posparto. Si en caso diferente, por razón médica no puede tomar la semana previa al parto, podrá disfrutar las dieciocho (18) semanas en el posparto inmediato. b) Licencia de maternidad posparto. Esta licencia tendrá una duración normal de diecisiete (17) semanas contadas desde la fecha del parto, o de dieciséis (16) o dieciocho (18) semanas por decisión médica, de acuerdo con lo previsto en el literal anterior. PARÁGRAFO 1o. De las dieciocho (18) semanas de licencia remunerada, la semana anterior al probable parto será de obligatorio goce a menos que el médico tratante prescriba algo diferente. La licencia remunerada de la que habla este artículo es incompatible con la licencia de calamidad doméstica y en caso de haberse solicitado esta última por el nacimiento de un hijo, estos días serán descontados de la misma. PARÁGRAFO 2o. El padre tendrá derecho a dos (2) semanas de licencia remunerada de paternidad. La licencia remunerada de paternidad opera por los hijos nacidos del cónyuge o de la compañera permanente, así como para el padre adoptante. El único soporte válido para el otorgamiento de la licencia remunerada de paternidad es el Registro Civil de Nacimiento, el cual deberá presentarse a la EPS a más tardar dentro de los 30 días siguientes a la fecha del nacimiento del menor. La licencia remunerada de paternidad estará a cargo de la EPS y será reconocida proporcionalmente a las semanas cotizadas por el padre durante el periodo de gestación. La licencia de paternidad se ampliará en una (1) semana adicional por cada punto porcentual de disminución de la tasa de desempleo estructural comparada con su nivel al momento de la entrada en vigencia de la presente ley, sin que en ningún caso pueda superar las cinco (5) semanas. PARÁGRAFO 3o. Para efectos de la aplicación del numeral 5 del presente artículo, se deberá anexar al certificado de nacido vivo la certificación expedida por el médico tratante en la cual se identifique diferencia entre la edad gestacional y el nacimiento a término, con el fin de determinar en cuántas semanas se debe ampliar la licencia de maternidad, o determinar la multiplicidad en el embarazo. PARÁGRAFO 4o. Licencia parental compartida. Los padres podrán distribuir libremente entre sí las últimas seis (6) semanas de la licencia de la madre, siempre y cuando cumplan las condiciones y requisitos dispuestos en este artículo. La madre deberá tomar como mínimo las primeras doce (12) semanas después del parto, las cuales serán intransferibles. Las restantes seis (6) semanas podrán ser distribuidas entre la madre y el padre, de común acuerdo entre los dos. En ningún caso se podrán fragmentar, intercalar ni tomar de manera simultánea los períodos de licencia salvo por enfermedad posparto de la madre, debidamente certificada por el médico. La licencia parental compartida será remunerada con base en el salario de quien disfrute de la licencia por el período correspondiente. No podrán optar por la licencia parental compartida los padres condenados por delitos contra la libertad e integridad sexual, contra la familia o que tengan vigente una medida de protección conforme a la Ley 1257 de 2008. PARÁGRAFO 5o. Licencia parental flexible de tiempo parcial. La madre y/o padre podrán optar por una licencia parental flexible de tiempo parcial, en la cual podrán cambiar un periodo determinado de su licencia de maternidad o de paternidad por un período de trabajo de medio tiempo, equivalente al doble del tiempo correspondiente al período de tiempo seleccionado. Los padres podrán usar esta figura antes de la semana dos (2) de su licencia de paternidad; las madres, no antes de la semana trece (13) de su licencia de maternidad. Los periodos seleccionados para la licencia parental flexible no podrán interrumpirse y retomarse posteriormente, deberán ser continuos salvo acuerdo entre el empleador y el trabajador. La licencia parental flexible de tiempo parcial también se aplicará con respecto a los niños prematuros y adoptivos, y es aplicable a los trabajadores del sector público.',
        ],
        [
            'codigo'         => 'Art. 239 CST',
            'titulo'         => 'Prohibición de despido por embarazo o lactancia — fuero de maternidad',
            'categoria'      => 'grupos_protegidos',
            'fuente'         => 'CST',
            'orden'          => 239,
            'texto_completo' => 'Ninguna trabajadora puede ser despedida por motivo de embarazo o lactancia. Se presume que el despido se ha efectuado por motivo de embarazo o lactancia, cuando ha tenido lugar dentro del período del embarazo o dentro de los tres meses posteriores al parto y sin autorización de las autoridades de que trata el artículo siguiente. La trabajadora despedida sin autorización de la autoridad tiene derecho al pago de una indemnización equivalente a los salarios de sesenta (60) días, fuera de las indemnizaciones y prestaciones a que hubiere lugar de acuerdo con el contrato de trabajo, y al pago de las dieciocho (18) semanas de descanso remunerado si no lo ha tomado. Para hacer efectivo el despido durante el embarazo o los tres meses posteriores al parto, el empleador requiere autorización del Inspector del Trabajo.',
        ],
        [
            'codigo'         => 'Art. 240 CST',
            'titulo'         => 'Permiso para despedir trabajadora en embarazo o lactancia',
            'categoria'      => 'grupos_protegidos',
            'fuente'         => 'CST',
            'orden'          => 240,
            'texto_completo' => 'Para poder despedir a una trabajadora durante el período de embarazo o los tres meses posteriores al parto, el empleador necesita la autorización del Inspector del Trabajo, o del Alcalde Municipal en los lugares donde no existiere aquel funcionario. El permiso de que trata este artículo sólo puede concederse con fundamento en alguna de las causas que tiene el empleador para dar por terminado el contrato de trabajo enumeradas en los artículos 62 y 63 del Código Sustantivo del Trabajo. Antes de resolver, el funcionario debe oír a la trabajadora y practicar todas las pruebas conducentes solicitadas por las partes.',
        ],

        // ──────────────────────────────────────────────────────────────────────
        // GRUPOS PROTEGIDOS — FUERO SINDICAL
        // ──────────────────────────────────────────────────────────────────────
        [
            'codigo'         => 'Art. 405 CST',
            'titulo'         => 'Definición y alcance del fuero sindical',
            'categoria'      => 'grupos_protegidos',
            'fuente'         => 'CST',
            'orden'          => 405,
            'texto_completo' => 'Se denomina fuero sindical la garantía de que gozan algunos trabajadores de no ser despedidos, ni desmejorados en sus condiciones de trabajo, ni trasladados a otros establecimientos de la misma empresa o a un municipio distinto, sin justa causa, previamente calificada por el juez del trabajo. La estabilidad que se otorga mediante el fuero sindical no impide que el empleador inicie proceso ordinario laboral, pero sólo puede hacer efectivo el despido, traslado o desmejora si obtiene previa calificación judicial de la justa causa.',
        ],
        [
            'codigo'         => 'Art. 408 CST',
            'titulo'         => 'Proceso de levantamiento del fuero sindical — calificación judicial',
            'categoria'      => 'grupos_protegidos',
            'fuente'         => 'CST',
            'orden'          => 408,
            'texto_completo' => 'Cuando un empleador desee despedir, desmejorar o trasladar a un trabajador amparado por el fuero sindical, deberá demandar al trabajador ante el Juez del Trabajo de su domicilio para que se declare que existe justa causa. El juez, en el término de cinco (5) días hábiles, correrá traslado de la demanda al trabajador. Si este no contesta la demanda o acepta los hechos, el juez fallará dentro de los diez (10) días siguientes. En caso contrario señalará día y hora para la práctica de pruebas y fallará dentro de los quince (15) días siguientes. Mientras no se haya obtenido la autorización judicial, el empleador no puede hacer efectivo el despido, traslado o desmejora.',
        ],

        // ──────────────────────────────────────────────────────────────────────
        // LEY 1010 DE 2006 — ACOSO LABORAL
        // ──────────────────────────────────────────────────────────────────────
        [
            'codigo'         => 'Art. 2 Ley 1010/2006',
            'titulo'         => 'Definición y modalidades de acoso laboral',
            'categoria'      => 'acoso_laboral',
            'fuente'         => 'Ley 1010/2006',
            'orden'          => 1,
            'texto_completo' => 'Para efectos de la presente ley se entenderá por acoso laboral toda conducta persistente y demostrable, ejercida sobre un empleado o trabajador por parte de un empleador, un jefe o superior jerárquico inmediato o mediato, un compañero de trabajo o un subalterno, encaminada a infundir miedo, intimidación, terror y angustia, a causar perjuicio laboral, generar desmotivación en el trabajo, o inducir la renuncia del mismo. El acoso laboral puede darse bajo las siguientes modalidades: a) Maltrato laboral: Todo acto de violencia contra la integridad física o moral, la libertad física o sexual y los bienes de quien se desempeñe como empleado o trabajador; toda expresión verbal injuriosa o ultrajante; todo comportamiento tendiente a menoscabar la autoestima y la dignidad. b) Persecución laboral: toda conducta cuyas características de reiteración o evidente arbitrariedad permitan inferir el propósito de inducir la renuncia del empleado o trabajador, mediante la descalificación, la carga excesiva de trabajo y cambios permanentes de horario que puedan producir desmotivación laboral. c) Discriminación laboral: todo trato diferenciado por razones de raza, género, origen familiar o nacional, credo religioso, preferencia política o situación social que carezca de toda razonabilidad desde una perspectiva laboral. d) Entorpecimiento laboral: toda acción tendiente a obstaculizar el cumplimiento de la labor o hacerla más gravosa o retardarla en perjuicio del trabajador. e) Inequidad laboral: asignación de funciones a menosprecio del trabajador. f) Desprotección laboral: toda conducta tendiente a poner en riesgo la integridad y la seguridad del trabajador sin causa justificada.',
        ],
        [
            'codigo'         => 'Art. 7 Ley 1010/2006',
            'titulo'         => 'Conductas que constituyen acoso laboral',
            'categoria'      => 'acoso_laboral',
            'fuente'         => 'Ley 1010/2006',
            'orden'          => 2,
            'texto_completo' => 'Se presumirá que hay acoso laboral si se acredita la ocurrencia repetida y pública de cualquiera de las siguientes conductas: a) Los actos de agresión física, independientemente de sus consecuencias; b) Las expresiones injuriosas o ultrajantes sobre la persona, con utilización de palabras soeces o con alusión a la raza, el género, el origen familiar o nacional, la preferencia política o el estatus social; c) Los comentarios hostiles y humillantes de descalificación profesional expresados en presencia de los compañeros de trabajo; d) Las amenazas continuadas y los llamados a renunciar al trabajo; e) La descalificación humillante y en presencia de compañeros de trabajo de las propuestas u opiniones de trabajo; f) Las burlas sobre la apariencia física o la forma de vestir, formuladas en público; g) La alusión pública a hechos pertenecientes a la intimidad de la persona; h) La imposición de deberes ostensiblemente extraños a las obligaciones laborales o exigencias abiertamente desproporcionadas; i) La exigencia de laborar en horarios excesivos sin reconocimiento de horas extras; j) El trato notoriamente discriminatorio respecto a los demás empleados en cuanto al otorgamiento de derechos y prerrogativas laborales; k) La negativa claramente injustificada a otorgar permisos, licencias por enfermedad, licencias ordinarias y vacaciones cuando se dan las condiciones legales; l) El envío de anónimos, llamadas telefónicas y mensajes con contenido injurioso, ofensivo o intimidatorio.',
        ],
        [
            'codigo'         => 'Art. 9 Ley 1010/2006',
            'titulo'         => 'Medidas preventivas y correctivas del acoso laboral',
            'categoria'      => 'acoso_laboral',
            'fuente'         => 'Ley 1010/2006',
            'orden'          => 3,
            'texto_completo' => 'El empleador debe adoptar medidas preventivas del acoso laboral, entre ellas: incluir en el reglamento de trabajo mecanismos de prevención de las conductas que constituyen acoso laboral y el establecimiento de un procedimiento interno, confidencial, conciliatorio y efectivo para superar las que ocurran en el lugar de trabajo. Los comités de convivencia laboral conformados en las empresas privadas y públicas deberán intervenir en los casos de acoso laboral. El empleador que tolere el acoso laboral en su empresa incurre en las sanciones previstas en esta ley. La víctima de acoso laboral puede terminar unilateralmente el contrato de trabajo sin justa causa a su cargo, con derecho a recibir la indemnización establecida en el artículo 64 del Código Sustantivo del Trabajo.',
        ],

        // ──────────────────────────────────────────────────────────────────────
        // LEY 361 DE 1997 — ESTABILIDAD REFORZADA POR DISCAPACIDAD
        // ──────────────────────────────────────────────────────────────────────
        [
            'codigo'         => 'Art. 26 Ley 361/1997',
            'titulo'         => 'No discriminación a persona en situación de discapacidad — estabilidad laboral reforzada',
            'categoria'      => 'grupos_protegidos',
            'fuente'         => 'Ley 361/1997',
            'orden'          => 1,
            'texto_completo' => 'ARTÍCULO 26. NO DISCRIMINACIÓN A PERSONA EN SITUACIÓN DE DISCAPACIDAD. En ningún caso la limitación (discapacidad) de una persona, podrá ser motivo para obstaculizar una vinculación laboral, a menos que dicha limitación (discapacidad) sea claramente demostrada como incompatible e insuperable en el cargo que se va a desempeñar. Así mismo, ninguna persona limitada (en situación de discapacidad) podrá ser despedida o su contrato terminado por razón de su limitación (discapacidad), salvo que medie autorización de la oficina de Trabajo. No obstante, quienes fueren despedidos o su contrato terminado por razón de su limitación (discapacidad), sin el cumplimiento del requisito previsto en el inciso anterior, tendrán derecho a una indemnización equivalente a ciento ochenta días del salario, sin perjuicio de las demás prestaciones e indemnizaciones a que hubiere lugar de acuerdo con el Código Sustantivo del Trabajo y demás normas que lo modifiquen, adicionen, complementen o aclaren.',
        ],
    ];

    public function handle(): int
    {
        $this->info('Importando artículos legales para RAG (CST, Ley 1010, Ley 361)...');

        $apiKey = config('services.ia.gemini.api_key') ?? config('services.gemini.api_key');

        if (!$apiKey) {
            $this->error('No se encontró GEMINI_API_KEY en la configuración.');
            return self::FAILURE;
        }

        $force  = $this->option('force');
        $total  = count($this->articulos);
        $ok     = 0;
        $skip   = 0;

        foreach ($this->articulos as $datos) {
            $codigo = $datos['codigo'];
            $fuente = $datos['fuente'];

            $existente = ArticuloLegal::where('codigo', $codigo)
                ->where('fuente', $fuente)
                ->whereNull('empresa_id')
                ->first();

            if ($existente && !$force && $existente->embedding) {
                $this->line("  [skip] {$codigo}");
                $skip++;
                continue;
            }

            $embedding = $this->generarEmbedding($datos['texto_completo'], $apiKey);

            ArticuloLegal::updateOrCreate(
                [
                    'codigo'     => $codigo,
                    'fuente'     => $fuente,
                    'empresa_id' => null,
                ],
                [
                    'titulo'         => $datos['titulo'],
                    'descripcion'    => mb_substr($datos['texto_completo'], 0, 255),
                    'texto_completo' => $datos['texto_completo'],
                    'categoria'      => $datos['categoria'],
                    'orden'          => $datos['orden'],
                    'activo'         => true,
                    'embedding'      => $embedding,
                ]
            );

            $estado = $embedding ? '[ok]' : '[sin embedding]';
            $this->info("  {$estado} {$codigo}");
            $ok++;

            usleep(300_000); // 300 ms — respetar rate-limit Gemini
        }

        $this->info("Listo. {$ok} importados, {$skip} omitidos (ya existían), {$total} total.");
        return self::SUCCESS;
    }

    private function generarEmbedding(string $texto, string $apiKey): ?array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}";

        try {
            $response = Http::timeout(15)->post($url, [
                'content'  => ['parts' => [['text' => mb_substr($texto, 0, 8000)]]],
                'taskType' => 'RETRIEVAL_DOCUMENT',
            ]);

            if (!$response->successful()) {
                Log::warning('cst:importar — embedding fallido', [
                    'codigo' => mb_substr($texto, 0, 50),
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $values = $response->json('embedding.values');
            return is_array($values) && !empty($values) ? $values : null;
        } catch (\Exception $e) {
            Log::error('cst:importar — excepción embedding', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
