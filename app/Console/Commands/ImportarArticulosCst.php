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
            'texto_completo' => 'Son obligaciones especiales del trabajador: 1. Realizar personalmente la labor en los términos estipulados; observar los preceptos del reglamento y acatar y cumplir las órdenes e instrucciones que de modo particular le impartan el empleador o sus representantes, según el orden jerárquico establecido. 2. No comunicar con terceros, salvo autorización expresa, las informaciones que tenga sobre su trabajo, especialmente sobre las cosas que sean de naturaleza reservada o cuya divulgación pueda ocasionar perjuicios al empleador. 3. Conservar y restituir en buen estado, salvo el deterioro natural, los instrumentos y útiles que le hayan sido facilitados y las materias primas sobrantes. 4. Guardar rigurosamente la moral en las relaciones con sus superiores y compañeros. 5. Comunicar oportunamente al empleador las observaciones que estime conducentes a evitarle daños y perjuicios. 6. Prestar la colaboración posible en casos de siniestro o de riesgo inminentes que afecten o amenacen las personas o las cosas de la empresa. 7. Observar las medidas preventivas higiénicas prescritas por el médico del empleador o por las autoridades del ramo.',
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
            'titulo'         => 'Terminación unilateral sin justa causa — indemnización',
            'categoria'      => 'terminacion',
            'fuente'         => 'CST',
            'orden'          => 64,
            'texto_completo' => 'En todo contrato de trabajo va envuelta la condición resolutoria por incumplimiento de lo pactado, con indemnización de perjuicios a cargo de la parte responsable. Esta indemnización comprende el lucro cesante y el daño emergente. Para los trabajadores con contrato a término indefinido, la indemnización se liquidará así: cuarenta y cinco (45) días de salario cuando el trabajador tuviere un tiempo de servicio no mayor de un (1) año; si el trabajador tuviere más de un (1) año de servicio continuo se le pagarán quince (15) días adicionales de salario sobre los cuarenta y cinco (45) básicos, por cada uno de los años de servicio subsiguientes al primero y proporcionalmente por fracción. Para los trabajadores que devenguen un salario superior a diez (10) salarios mínimos legales mensuales, el valor de la indemnización equivaldrá a veinte (20) días de salario por el primer año de servicios y veinte (20) días de salario adicionales por cada uno de los años de servicio subsiguientes al primero y proporcionalmente por fracción.',
        ],

        // ──────────────────────────────────────────────────────────────────────
        // REGLAMENTO INTERNO DE TRABAJO Y SANCIONES
        // ──────────────────────────────────────────────────────────────────────
        [
            'codigo'         => 'Art. 111 CST',
            'titulo'         => 'Definición y obligatoriedad del reglamento interno de trabajo',
            'categoria'      => 'reglamento_interno',
            'fuente'         => 'CST',
            'orden'          => 111,
            'texto_completo' => 'El reglamento de trabajo es el conjunto de normas que determinan las condiciones a que deben sujetarse el empleador y sus trabajadores en la prestación del servicio. Todo empleador que ocupe más de cinco (5) trabajadores de carácter permanente en empresas comerciales, o más de diez (10) en empresas industriales, o más de veinte (20) en empresas agrícolas, ganaderas o forestales, está obligado a tener un reglamento de trabajo inscrito en el Ministerio de Trabajo.',
        ],
        [
            'codigo'         => 'Art. 112 CST',
            'titulo'         => 'Contenido del reglamento de trabajo',
            'categoria'      => 'reglamento_interno',
            'fuente'         => 'CST',
            'orden'          => 112,
            'texto_completo' => 'El reglamento de trabajo debe contener disposiciones normativas sobre: 1. Identificación del empleador y del establecimiento. 2. Condiciones de admisión, aprendizaje y período de prueba. 3. Horas de entrada y salida; turnos; tiempos de descanso y comida. 4. Horas extras y trabajo nocturno; su autorización, reconocimiento y pago. 5. Días de descanso legalmente obligatorio; vacaciones remuneradas; permisos. 6. Salario mínimo legal o convencional. 7. Lugar, día, hora de pagos y período que los regula. 8. Prescripciones de orden y seguridad. 9. Indicaciones para evitar riesgos profesionales. 10. Orden jerárquico de los representantes del empleador. 11. Sanciones disciplinarias y forma de aplicarlas. 12. La persona ante quien deben presentarse los reclamos del personal.',
        ],
        [
            'codigo'         => 'Art. 113 CST',
            'titulo'         => 'Procedimiento de aprobación del reglamento interno',
            'categoria'      => 'reglamento_interno',
            'fuente'         => 'CST',
            'orden'          => 113,
            'texto_completo' => 'El empleador que esté obligado a tener reglamento interno de trabajo debe redactarlo y publicarlo en el lugar de trabajo en lugar visible, y presentarlo ante el Ministerio de Trabajo para su inscripción. El Ministerio puede objetar disposiciones del reglamento que sean contrarias a la ley. Una vez inscrito el reglamento, entrará en vigencia ocho (8) días después de su publicación. El reglamento puede ser modificado en cualquier tiempo por el empleador, con sujeción al mismo procedimiento.',
        ],
        [
            'codigo'         => 'Art. 114 CST',
            'titulo'         => 'Efectos jurídicos del reglamento de trabajo',
            'categoria'      => 'reglamento_interno',
            'fuente'         => 'CST',
            'orden'          => 114,
            'texto_completo' => 'El reglamento de trabajo aprobado por el Ministerio de Trabajo obliga al empleador y a los trabajadores y forma parte del contrato individual de trabajo de cada uno de ellos. Las disposiciones del reglamento de trabajo se incorporan al contrato individual y son exigibles tanto al empleador como a los trabajadores.',
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
            'titulo'         => 'Descanso remunerado en la época de parto — licencia de maternidad',
            'categoria'      => 'grupos_protegidos',
            'fuente'         => 'CST',
            'orden'          => 236,
            'texto_completo' => 'Toda trabajadora en estado de embarazo tiene derecho a una licencia de dieciocho (18) semanas en la época de parto, remunerada con el salario que devengue al entrar a disfrutar del descanso. Si se tratare de un salario que no sea fijo, como en el caso del trabajo a destajo o por tarea, se toma en cuenta el salario promedio devengado por la trabajadora en el último año de servicios, o en todo el tiempo si fuere menor. Adicionalmente, el empleador debe conceder a la trabajadora en período de lactancia dos (2) descansos de treinta (30) minutos cada uno dentro de la jornada para amamantar a su hijo, durante los primeros seis (6) meses de vida del bebé.',
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
            'titulo'         => 'Estabilidad reforzada por discapacidad o limitación física',
            'categoria'      => 'grupos_protegidos',
            'fuente'         => 'Ley 361/1997',
            'orden'          => 1,
            'texto_completo' => 'En ningún caso la limitación de una persona podrá ser motivo para obstaculizar una vinculación laboral, a menos que dicha limitación sea claramente demostrada como incompatible e insuperable en el cargo que se va a desempeñar. Así mismo, ninguna persona limitada podrá ser despedida o su contrato terminado por razón de su limitación, salvo que medie autorización de la oficina de Trabajo. No obstante, quienes fueren despedidos o su contrato terminado por razón de su limitación, sin el cumplimiento del requisito previsto en el inciso anterior, tendrán derecho a una indemnización equivalente a ciento ochenta (180) días del salario, sin perjuicio de las demás prestaciones e indemnizaciones a que hubiere lugar de acuerdo con el Código Sustantivo del Trabajo. La Corte Constitucional ha extendido esta protección a todas las personas en situación de debilidad manifiesta por razones de salud, incluso sin calificación formal de discapacidad (Sentencia C-458/15, T-041/14 y otras).',
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
