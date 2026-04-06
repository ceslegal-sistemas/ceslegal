<?php

namespace App\Console\Commands;

use App\Models\ArticuloLegal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importa artículos clave del Código Sustantivo del Trabajo (CST) colombiano
 * como artículos universales (empresa_id = null) en articulos_legales.
 *
 * Es idempotente: sólo inserta/actualiza por (codigo, fuente='CST').
 */
class ImportarArticulosCst extends Command
{
    protected $signature   = 'cst:importar {--force : Regenerar embeddings aunque ya existan}';
    protected $description = 'Importa artículos del Código Sustantivo del Trabajo con embeddings para RAG';

    // Artículos clave del CST colombiano
    private array $articulos = [
        [
            'codigo'         => 'Art. 57 CST',
            'titulo'         => 'Obligaciones especiales del empleador',
            'categoria'      => 'obligaciones',
            'orden'          => 57,
            'texto_completo' => 'Son obligaciones especiales del empleador: 1. Poner a disposición de los trabajadores, salvo estipulación en contrario, los instrumentos adecuados y las materias primas necesarias para la realización de las labores. 2. Procurar a los trabajadores locales apropiados y elementos adecuados de protección contra accidentes y enfermedades profesionales en forma que se garanticen razonablemente la seguridad y la salud. 3. Prestar de inmediato los primeros auxilios en caso de accidentes o de enfermedad. 4. Pagar la remuneración pactada en las condiciones, períodos y lugares convenidos. 5. Guardar absoluto respeto a la dignidad personal del trabajador, a sus creencias y sentimientos. 6. Conceder al trabajador las licencias necesarias para el ejercicio del sufragio; para el desempeño de cargos oficiales transitorios de forzosa aceptación; en caso de grave calamidad doméstica debidamente comprobada; para desempeñar comisiones sindicales inherentes a la organización o para asistir al entierro de sus compañeros, siempre que avisen con la debida oportunidad al empleador o a su representante y que en los dos últimos casos el número de los que se ausenten no sea tal que perjudique el funcionamiento de la empresa. 7. Dar al trabajador que lo solicite, a la expiración del contrato, una certificación en que consten el tiempo de servicio, la índole de la labor y el salario devengado; e igualmente, si el trabajador lo solicita, hacerle practicar examen sanitario y darle certificación sobre el particular. 8. Pagar al trabajador los gastos razonables de venida y de regreso, si para prestar sus servicios lo hizo cambiar de residencia, salvo si la terminación del contrato se origina por culpa o voluntad del trabajador. 9. Cumplir el reglamento y mantener el orden, la moralidad y el respeto a las leyes. 10. Conceder licencia remunerada al trabajador que esté en periodo de lactancia, para amamantar a su hijo.',
        ],
        [
            'codigo'         => 'Art. 58 CST',
            'titulo'         => 'Obligaciones especiales del trabajador',
            'categoria'      => 'obligaciones',
            'orden'          => 58,
            'texto_completo' => 'Son obligaciones especiales del trabajador: 1. Realizar personalmente la labor en los términos estipulados; observar los preceptos del reglamento y acatar y cumplir las órdenes e instrucciones que de modo particular le impartan el empleador o sus representantes, según el orden jerárquico establecido. 2. No comunicar con terceros, salvo autorización expresa, las informaciones que tenga sobre su trabajo, especialmente sobre las cosas que sean de naturaleza reservada o cuya divulgación pueda ocasionar perjuicios al empleador. 3. Conservar y restituir en buen estado, salvo el deterioro natural, los instrumentos y útiles que le hayan sido facilitados y las materias primas sobrantes. 4. Guardar rigurosamente la moral en las relaciones con sus superiores y compañeros. 5. Comunicar oportunamente al empleador las observaciones que estime conducentes a evitarle daños y perjuicios. 6. Prestar la colaboración posible en casos de siniestro o de riesgo inminentes que afecten o amenacen las personas o las cosas de la empresa o establecimiento. 7. Observar las medidas preventivas higiénicas prescritas por el médico del empleador o por las autoridades del ramo y observar con suma diligencia y cuidado las instrucciones y órdenes preventivas de accidentes o de enfermedades profesionales.',
        ],
        [
            'codigo'         => 'Art. 59 CST',
            'titulo'         => 'Prohibiciones al empleador',
            'categoria'      => 'prohibiciones',
            'orden'          => 59,
            'texto_completo' => 'Se prohíbe al empleador: 1. Deducir, retener o compensar suma alguna del monto de los salarios y prestaciones en dinero que corresponda a los trabajadores, sin autorización previa escrita de éstos para cada caso, o sin mandamiento judicial, con excepción de los descuentos autorizados en los artículos 113, 150, 151, 152 y 400. 2. Obligar a los trabajadores, cualquiera que sea el medio, a adquirir mercancías o víveres en almacenes o proveedurías que establezca el empleador. 3. Exigir o aceptar dinero del trabajador como gratificación para que se le admita en el trabajo o por motivo cualquiera que se refiera a las condiciones de éste. 4. Limitar o presionar en cualquier forma a los trabajadores en el ejercicio de su derecho de asociación. 5. Imponer a los trabajadores obligaciones de carácter religioso o político o dificultarles o impedirles el ejercicio del derecho al sufragio. 6. Hacer, autorizar, o tolerar propaganda política en los sitios de trabajo. 7. Hacer o permitir todo género de juegos de suerte y azar en los sitios de trabajo. 8. Ejecutar o autorizar cualquier acto que vulnere o restrinja los derechos de los trabajadores o que ofenda su dignidad.',
        ],
        [
            'codigo'         => 'Art. 60 CST',
            'titulo'         => 'Prohibiciones al trabajador',
            'categoria'      => 'prohibiciones',
            'orden'          => 60,
            'texto_completo' => 'Se prohíbe al trabajador: 1. Sustraer de la fábrica, taller o establecimiento, los útiles de trabajo, las materias primas o productos elaborados, sin permiso del empleador. 2. Presentarse al trabajo en estado de embriaguez o bajo la influencia de narcóticos o de drogas enervantes. 3. Conservar armas de cualquier clase en el sitio de trabajo, a excepción de las que con autorización legal puedan llevar los celadores. 4. Faltar al trabajo sin justa causa de impedimento o sin permiso del empleador, excepto en los casos de huelga, en los cuales deben abandonar el lugar del trabajo. 5. Disminuir intencionalmente el ritmo de ejecución del trabajo, suspender labores, promover suspensiones intempestivas del trabajo e incitar a su declaración o mantenimiento, sea que participe o no en ellas. 6. Hacer colectas, rifas y suscripciones o cualquier clase de propaganda en los lugares de trabajo. 7. Coartar la libertad para trabajar o no trabajar, o para afiliarse o no con una asociación sindical. 8. Usar los útiles o herramientas suministrados por el empleador en objetos distintos del trabajo contratado.',
        ],
        [
            'codigo'         => 'Art. 62 CST',
            'titulo'         => 'Terminación del contrato por justa causa',
            'categoria'      => 'terminacion',
            'orden'          => 62,
            'texto_completo' => 'Son justas causas para dar por terminado unilateralmente el contrato de trabajo por parte del empleador: 1. El haberle suministrado el trabajador al empleador datos o documentos falsos a la celebración del contrato. 2. Todo acto de violencia, injuria, malos tratamientos o grave indisciplina en que incurra el trabajador en sus labores, contra el empleador, los miembros de su familia, el personal directivo o los compañeros de trabajo. 3. Todo acto grave de violencia, injuria o malos tratamientos en que incurra el trabajador fuera del servicio, en contra del empleador, de los miembros de su familia o de sus representantes y socios, jefes de taller, vigilantes o celadores. 4. Todo daño material causado intencionalmente a los edificios, obras, maquinaria y materias primas, instrumentos y demás objetos relacionados con el trabajo, y toda grave negligencia que ponga en peligro la seguridad de las personas o de las cosas. 5. Todo acto inmoral o delictuoso que el trabajador cometa en el taller, establecimiento o lugar de trabajo, o en el desempeño de sus labores. 6. Cualquier violación grave de las obligaciones o prohibiciones especiales que incumben al trabajador de acuerdo con los artículos 58 y 60 del Código Sustantivo del Trabajo, o cualquier falta grave calificada como tal en pactos o convenciones colectivas, fallos arbitrales, contratos individuales o reglamentos. 7. La detención preventiva del trabajador por más de treinta (30) días, a menos que posteriormente sea absuelto; o el arresto correccional que exceda de ocho (8) días, o aun siendo de menor duración suspenda el trabajo por más de ocho (8) días; o cualquier otra pena privativa de la libertad por más de ocho (8) días. 8. El que el trabajador revele los secretos técnicos o comerciales o dé a conocer asuntos de carácter reservado, con perjuicio de la empresa. 9. El deficiente rendimiento en el trabajo en relación con la capacidad del trabajador y con el rendimiento promedio en labores análogas, cuando no se corrija en un plazo razonable a pesar del requerimiento del empleador. 10. La sistemática inejecución, sin razones válidas, por parte del trabajador, de las obligaciones convencionales o legales. 11. Todo vicio del trabajador que perturbe la disciplina del establecimiento. 12. La renuencia sistemática del trabajador a aceptar las medidas preventivas, profilácticas o curativas, prescritas por el médico del empleador o por las autoridades para evitar enfermedades o accidentes. 13. La ineptitud del trabajador para realizar la labor encomendada. 14. El reconocimiento al trabajador de la pensión de jubilación o invalidez estando al servicio de la empresa. 15. La enfermedad contagiosa o crónica del trabajador, que no tenga carácter de profesional, así como cualquier otra enfermedad o lesión que lo incapacite para el trabajo, cuya curación no haya sido posible durante ciento ochenta (180) días.',
        ],
        [
            'codigo'         => 'Art. 111 CST',
            'titulo'         => 'Definición de reglamento interno de trabajo',
            'categoria'      => 'reglamento_interno',
            'orden'          => 111,
            'texto_completo' => 'El reglamento de trabajo es el conjunto de normas que determinan las condiciones a que deben sujetarse el empleador y sus trabajadores en la prestación del servicio. Todo empleador que ocupe más de cinco (5) trabajadores de carácter permanente, en empresas comerciales, o más de diez (10) en empresas industriales, o más de veinte (20) en empresas agrícolas, ganaderas o forestales, está obligado a tener un reglamento de trabajo, inscrito en el Ministerio de Trabajo, en determinados plazos, a partir de la iniciación de sus actividades.',
        ],
        [
            'codigo'         => 'Art. 112 CST',
            'titulo'         => 'Contenido del reglamento de trabajo',
            'categoria'      => 'reglamento_interno',
            'orden'          => 112,
            'texto_completo' => 'El reglamento de trabajo debe contener disposiciones normativas de los siguientes puntos: 1. Indicación del empleador y del establecimiento o lugar de trabajo comprendido por el reglamento. 2. Condiciones de admisión, aprendizaje y período de prueba. 3. Trabajadores accidentales o transitorios. 4. Horas de entrada y salida de los trabajadores; horas en que principia y termina cada turno si el trabajo se efectúa por equipos; tiempo destinado para las comidas y períodos de descanso durante la jornada. 5. Horas extras y trabajo nocturno; su autorización, reconocimiento y pago. 6. Días de descanso legalmente obligatorio; horas o días de descanso convencional o adicional; vacaciones remuneradas; permisos, especialmente lo relacionado con el numeral 6 del artículo 57 del Código Sustantivo del Trabajo; salario para los mismos; forma y requisitos para disfrutarlos. 7. Salario mínimo legal o convencional. 8. Lugar, día, hora de pagos y período que los regula. 9. Tiempo y forma en que los trabajadores deben sujetarse a los servicios médicos que el empleador suministre. 10. Prescripciones de orden y seguridad. 11. Indicaciones para evitar que se realicen los riesgos profesionales e instrucciones para prestar los primeros auxilios en caso de accidente. 12. Orden jerárquico de los representantes del empleador, jefes de sección, capataces y vigilantes. 13. Especificaciones de las labores que no deben ejecutar las mujeres y los menores de dieciséis (16) años. 14. Normas especiales que se deben guardar en las diversas clases de labores, de acuerdo con la edad y el sexo de los trabajadores, con miras a conseguir la mayor higiene, regularidad y seguridad en el trabajo. 15. Sanciones disciplinarias y forma de aplicarlas. 16. La persona o personas ante quienes se deben presentar los reclamos del personal y tramitación de éstos, expresando que el trabajador o los trabajadores pueden asesorarse del sindicato respectivo.',
        ],
        [
            'codigo'         => 'Art. 114 CST',
            'titulo'         => 'Efectos jurídicos del reglamento de trabajo',
            'categoria'      => 'reglamento_interno',
            'orden'          => 114,
            'texto_completo' => 'El reglamento de trabajo aprobado por el Ministerio de Trabajo obliga al empleador y a los trabajadores y forma parte del contrato individual de trabajo de cada uno de ellos. Las disposiciones del reglamento de trabajo se incorporan al contrato individual y son exigibles tanto al empleador como a los trabajadores.',
        ],
        [
            'codigo'         => 'Art. 115 CST',
            'titulo'         => 'Procedimiento disciplinario — debido proceso',
            'categoria'      => 'procedimiento_disciplinario',
            'orden'          => 115,
            'texto_completo' => 'Antes de aplicarse una sanción disciplinaria, el empleador debe dar oportunidad de ser oídos tanto al trabajador inculpado como a dos representantes del sindicato a que éste pertenezca. En las empresas donde no haya sindicato, antes de aplicarse la sanción, deberá oírse al trabajador en descargos. Cuando el reglamento contemple la posibilidad de suspender al trabajador o aplicarle descuento en el salario, estas sanciones no podrán imponerse sin que se haya formado previamente el expediente que justifique la medida, expediente que debe contener el pliego de cargos, los descargos del trabajador y las pruebas que el empleador allegue. La garantía del debido proceso es un principio constitucional que ampara al trabajador en el proceso disciplinario laboral y que el empleador debe respetar so pena de que la sanción sea ineficaz.',
        ],
        [
            'codigo'         => 'Art. 116 CST',
            'titulo'         => 'Limitación de sanciones disciplinarias',
            'categoria'      => 'sanciones',
            'orden'          => 116,
            'texto_completo' => 'Las sanciones disciplinarias que puede imponer el empleador, según el reglamento de trabajo, son: multas por retardos y faltas de asistencia al trabajo, descuentos al salario por las faltas mencionadas, suspensión en el trabajo y en los demás casos previstos en el reglamento. El empleador no puede imponer sanciones no previstas en el reglamento de trabajo. Las sanciones de suspensión y de descuento de salario solo pueden imponerse cuando el reglamento interno de trabajo las contemple expresamente. Sin reglamento interno de trabajo debidamente aprobado por el Ministerio de Trabajo, el empleador no puede aplicar sanciones de suspensión ni llamados de atención formales con efecto disciplinario, limitándose su facultad sancionatoria a la terminación del contrato por justa causa conforme al Art. 62 CST.',
        ],
    ];

    public function handle(): int
    {
        $this->info('Importando artículos del Código Sustantivo del Trabajo...');

        $apiKey = config('services.ia.gemini.api_key') ?? config('services.gemini.api_key');

        if (!$apiKey) {
            $this->error('No se encontró GEMINI_API_KEY en la configuración.');
            return self::FAILURE;
        }

        $force  = $this->option('force');
        $total  = 0;
        $nuevos = 0;

        foreach ($this->articulos as $datos) {
            $total++;
            $codigo = $datos['codigo'];

            $existente = ArticuloLegal::where('codigo', $codigo)
                ->where('fuente', 'CST')
                ->whereNull('empresa_id')
                ->first();

            if ($existente && !$force && $existente->embedding) {
                $this->line("  [skip] {$codigo} — ya existe con embedding");
                continue;
            }

            $embedding = $this->generarEmbedding($datos['texto_completo'], $apiKey);

            if (!$embedding) {
                $this->warn("  [error] {$codigo} — no se pudo generar embedding, se guarda sin él");
            }

            ArticuloLegal::updateOrCreate(
                [
                    'codigo'     => $codigo,
                    'fuente'     => 'CST',
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

            $this->info("  [ok] {$codigo}");
            $nuevos++;

            // Pausa mínima para respetar rate-limit de Gemini
            usleep(300_000); // 300 ms
        }

        $this->info("Listo. {$nuevos}/{$total} artículos importados/actualizados.");
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
