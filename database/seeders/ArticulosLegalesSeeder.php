<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ArticuloLegal;

class ArticulosLegalesSeeder extends Seeder
{
    public function run(): void
    {
        $articulos = [

            // ── CST Art. 58 — Obligaciones del trabajador ──────────────────────
            [
                'codigo'        => 'CST-58',
                'titulo'        => 'Obligaciones especiales del trabajador',
                'descripcion'   => 'Obligaciones del trabajador frente al empleador',
                'texto_completo'=> 'Son obligaciones especiales del trabajador: 1. Realizar personalmente la labor en los términos estipulados; observar los preceptos del reglamento y acatar y cumplir las órdenes e instrucciones que de modo particular le impartan el empleador o sus representantes. 2. No comunicar con terceros, salvo autorización expresa, las informaciones que tenga sobre su trabajo, especialmente sobre las cosas que sean de naturaleza reservada o cuya divulgación pueda ocasionar perjuicios al empleador.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Obligaciones',
                'orden'         => 1,
            ],

            // ── CST Art. 60 — Prohibiciones al trabajador ──────────────────────
            [
                'codigo'        => 'CST-60',
                'titulo'        => 'Prohibiciones a los trabajadores',
                'descripcion'   => 'Prohibiciones generales a los trabajadores',
                'texto_completo'=> 'Se prohíbe a los trabajadores: 1. Sustraer de la fábrica, taller o establecimiento los útiles de trabajo, las materias primas o productos elaborados sin permiso del empleador. 2. Presentarse al trabajo en estado de embriaguez o bajo la influencia de narcóticos o drogas enervantes. 3. Conservar armas de cualquier clase en el sitio de trabajo. 4. Faltar al respeto y consideración debidos al empleador, a los miembros de su familia, a los representantes o a los compañeros de trabajo. 5. Hacer colectas, rifas o suscripciones en los lugares de trabajo. 6. Usar los útiles o herramientas suministrados por el empleador en objetos distintos del trabajo contratado.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Prohibiciones',
                'orden'         => 2,
            ],
            [
                'codigo'        => 'CST-60-1',
                'titulo'        => 'Prohibición: sustraer útiles o materias primas',
                'descripcion'   => 'Sustraer de la fábrica, taller o establecimiento los útiles de trabajo, las materias primas o productos elaborados',
                'texto_completo'=> 'Artículo 60, numeral 1 CST: Está prohibido al trabajador sustraer de la fábrica, taller o establecimiento los útiles de trabajo, las materias primas o productos elaborados sin permiso del empleador.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Prohibiciones',
                'orden'         => 3,
            ],
            [
                'codigo'        => 'CST-60-2',
                'titulo'        => 'Prohibición: presentarse en estado de embriaguez o bajo narcóticos',
                'descripcion'   => 'Presentarse al trabajo en estado de embriaguez o bajo la influencia de narcóticos o drogas enervantes',
                'texto_completo'=> 'Artículo 60, numeral 2 CST: Está prohibido al trabajador presentarse al trabajo en estado de embriaguez o bajo la influencia de narcóticos o drogas enervantes.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Prohibiciones',
                'orden'         => 4,
            ],
            [
                'codigo'        => 'CST-60-3',
                'titulo'        => 'Prohibición: conservar armas en el sitio de trabajo',
                'descripcion'   => 'Conservar armas de cualquier clase en el sitio de trabajo',
                'texto_completo'=> 'Artículo 60, numeral 3 CST: Está prohibido al trabajador conservar armas de cualquier clase en el sitio de trabajo, a excepción de las que con autorización legal puedan llevar los celadores.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Prohibiciones',
                'orden'         => 5,
            ],
            [
                'codigo'        => 'CST-60-4',
                'titulo'        => 'Prohibición: faltar al respeto al empleador o compañeros',
                'descripcion'   => 'Faltar al respeto y consideración debidos al empleador, a los miembros de su familia, a los representantes o a los compañeros de trabajo',
                'texto_completo'=> 'Artículo 60, numeral 4 CST: Está prohibido al trabajador faltar al respeto y consideración debidos al empleador, a los miembros de su familia, a los representantes o a los compañeros de trabajo.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Prohibiciones',
                'orden'         => 6,
            ],

            // ── CST Art. 62 — Terminación por justa causa ──────────────────────
            [
                'codigo'        => 'CST-62',
                'titulo'        => 'Terminación del contrato por justa causa',
                'descripcion'   => 'Causas que justifican la terminación del contrato de trabajo por parte del empleador sin lugar a indemnización',
                'texto_completo'=> 'Artículo 62 CST: Son justas causas para dar por terminado unilateralmente el contrato de trabajo, por parte del empleador: las conductas tipificadas en los numerales que siguen, cuando sean suficientemente graves a juicio del empleador.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Terminación contrato',
                'orden'         => 10,
            ],
            [
                'codigo'        => 'CST-62-2',
                'titulo'        => 'Justa causa: violencia, malos tratos o amenazas graves',
                'descripcion'   => 'Todo acto de violencia, malos tratamientos o amenazas graves inferidas por el trabajador contra el empleador, su familia, directivos o compañeros',
                'texto_completo'=> 'Artículo 62, numeral 2 CST: Es justa causa para terminar el contrato: Todo acto de violencia, malos tratamientos o amenazas graves inferidas por el trabajador en su servicio contra el empleador, los miembros de su familia, el personal directivo o los compañeros de trabajo.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Terminación contrato',
                'orden'         => 11,
            ],
            [
                'codigo'        => 'CST-62-3',
                'titulo'        => 'Justa causa: actos inmorales o delictuosos en el trabajo',
                'descripcion'   => 'Todo acto inmoral o delictuoso que el trabajador cometa en el lugar de trabajo o en el desempeño de sus labores',
                'texto_completo'=> 'Artículo 62, numeral 3 CST: Es justa causa para terminar el contrato: Todo acto inmoral o delictuoso que el trabajador cometa en el taller, establecimiento o lugar de trabajo o en el desempeño de sus labores. Esto incluye conductas de acoso sexual, actos obscenos, comentarios o tocamientos de naturaleza sexual no consentidos en el lugar de trabajo.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Terminación contrato',
                'orden'         => 12,
            ],
            [
                'codigo'        => 'CST-62-4',
                'titulo'        => 'Justa causa: daño material intencional o negligencia grave',
                'descripcion'   => 'Todo daño material causado intencionalmente a los edificios, maquinaria o materias primas, o toda grave negligencia que ponga en peligro la seguridad',
                'texto_completo'=> 'Artículo 62, numeral 4 CST: Es justa causa para terminar el contrato: Todo daño material causado intencionalmente a los edificios, obras, maquinaria y materias primas, instrumentos y demás objetos relacionados con el trabajo, y toda grave negligencia que ponga en peligro la seguridad de las personas o de las cosas.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Terminación contrato',
                'orden'         => 13,
            ],
            [
                'codigo'        => 'CST-62-6',
                'titulo'        => 'Justa causa: violación grave de obligaciones o reglamento interno',
                'descripcion'   => 'Cualquier violación grave de las obligaciones o prohibiciones especiales del trabajador según el CST o el reglamento interno',
                'texto_completo'=> 'Artículo 62, numeral 6 CST: Es justa causa para terminar el contrato: Cualquier violación grave de las obligaciones o prohibiciones especiales que incumben al trabajador de acuerdo con los artículos 58 y 60 del Código Sustantivo del Trabajo, o cualquier falta grave calificada como tal en pactos o convenciones colectivas, fallos arbitrales, contratos individuales o reglamentos.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Terminación contrato',
                'orden'         => 14,
            ],
            [
                'codigo'        => 'CST-62-9',
                'titulo'        => 'Justa causa: deficiencia en el rendimiento laboral',
                'descripcion'   => 'El deficiente rendimiento en el trabajo en relación con la capacidad del trabajador y con el rendimiento promedio en labores análogas',
                'texto_completo'=> 'Artículo 62, numeral 9 CST: Es justa causa para terminar el contrato: El deficiente rendimiento en el trabajo en relación con la capacidad del trabajador y con el rendimiento promedio en labores análogas, cuando no se corrija en un plazo razonable a pesar del requerimiento del empleador.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Terminación contrato',
                'orden'         => 15,
            ],
            [
                'codigo'        => 'CST-62-10',
                'titulo'        => 'Justa causa: tardanzas o ausencias reiteradas',
                'descripcion'   => 'La sistemática inejecución, sin razones válidas, de las obligaciones convencionales o legales',
                'texto_completo'=> 'Artículo 62, numeral 10 CST: Es justa causa para terminar el contrato: La sistemática inejecución, sin razones válidas, por parte del trabajador, de las obligaciones convencionales o legales. Incluye las tardanzas reiteradas y las ausencias injustificadas repetidas.',
                'fuente'        => 'Código Sustantivo del Trabajo',
                'categoria'     => 'Terminación contrato',
                'orden'         => 16,
            ],

            // ── Ley 1010 de 2006 — Acoso laboral ───────────────────────────────
            [
                'codigo'        => 'L1010-2',
                'titulo'        => 'Definición de acoso laboral',
                'descripcion'   => 'Ley 1010/2006 Art. 2: Definición y modalidades de acoso laboral',
                'texto_completo'=> 'Ley 1010 de 2006, Artículo 2: Para efectos de la presente ley se entenderá por acoso laboral toda conducta persistente y demostrable, ejercida sobre un empleado, trabajador por parte de un empleador, un jefe o superior jerárquico inmediato o mediato, un compañero de trabajo o un subalterno, encaminada a infundir miedo, intimidación, terror y angustia, a causar perjuicio laboral, generar desmotivación en el trabajo, o inducir la renuncia del mismo. Las modalidades son: maltrato laboral, persecución laboral, discriminación laboral, entorpecimiento laboral, inequidad laboral, desprotección laboral.',
                'fuente'        => 'Ley 1010 de 2006',
                'categoria'     => 'Acoso laboral',
                'orden'         => 20,
            ],
            [
                'codigo'        => 'L1010-7',
                'titulo'        => 'Conductas que constituyen acoso laboral',
                'descripcion'   => 'Ley 1010/2006 Art. 7: Conductas atributivas de acoso laboral',
                'texto_completo'=> 'Ley 1010 de 2006, Artículo 7: Conductas que constituyen acoso laboral: Los actos de agresión física, independientemente de sus consecuencias; las expresiones injuriosas o ultrajantes sobre la persona, con utilización de palabras soeces o con alusión a la raza, el género, el origen familiar o nacional, la preferencia política o el estatus social; los comentarios hostiles y humillantes de descalificación profesional expresados en presencia de los compañeros de trabajo; las injustificadas amenazas de despido expresadas en presencia de los compañeros de trabajo; las múltiples denuncias disciplinarias de ninguna de las cuales se demuestra la veracidad; la descalificación humillante y en presencia de los compañeros de trabajo de las propuestas u opiniones de trabajo; las burlas sobre la apariencia física o la forma de vestir, formuladas en público; la alusión pública a hechos pertenecientes a la vida privada de la persona.',
                'fuente'        => 'Ley 1010 de 2006',
                'categoria'     => 'Acoso laboral',
                'orden'         => 21,
            ],
            [
                'codigo'        => 'L1010-10',
                'titulo'        => 'Tratamiento sancionatorio del acoso laboral',
                'descripcion'   => 'Ley 1010/2006 Art. 10: Sanciones aplicables al acoso laboral',
                'texto_completo'=> 'Ley 1010 de 2006, Artículo 10: El acoso laboral, cuando estuviere debidamente acreditado, se sancionará así: Si la víctima del acoso es un trabajador particular, el empleador que lo ocasione deberá pagar a la víctima una indemnización entre dos (2) y diez (10) salarios mínimos legales vigentes, sin perjuicio de las demás indemnizaciones a que hubiere lugar con arreglo a las disposiciones del Código Sustantivo del Trabajo y demás normas que lo modifiquen, adicionen o complementen. El trabajador que sea víctima del acoso laboral y que a consecuencia de éste se vea obligado a dar por terminado el contrato de trabajo, tendrá derecho a que el empleador le pague los perjuicios que dicha terminación le cause. Cuando el acoso laboral conlleve incumplimiento grave de las obligaciones de la empresa, el inspector de trabajo podrá sancionar a la empresa con multas.',
                'fuente'        => 'Ley 1010 de 2006',
                'categoria'     => 'Acoso laboral',
                'orden'         => 22,
            ],

            // ── Ley 1257 de 2008 — Acoso sexual ────────────────────────────────
            [
                'codigo'        => 'L1257-2',
                'titulo'        => 'Definición de violencia y acoso sexual',
                'descripcion'   => 'Ley 1257/2008 Art. 2: Definición de daño contra la mujer e incluye el acoso sexual',
                'texto_completo'=> 'Ley 1257 de 2008, Artículo 2: Para efectos de la presente ley se entiende por daño contra la mujer, cualquier acción u omisión que le cause muerte, daño o sufrimiento físico, sexual, psicológico, económico o patrimonial por su condición de mujer, así como las amenazas de tales actos, la coacción o la privación arbitraria de la libertad, bien sea que se presente en el ámbito público o en el privado. El acoso sexual en el lugar de trabajo está expresamente incluido como una forma de daño que debe ser prevenida, atendida y sancionada.',
                'fuente'        => 'Ley 1257 de 2008',
                'categoria'     => 'Acoso sexual',
                'orden'         => 30,
            ],
            [
                'codigo'        => 'L1257-12',
                'titulo'        => 'Medidas de protección en el ámbito laboral contra el acoso sexual',
                'descripcion'   => 'Ley 1257/2008: Obligaciones del empleador frente al acoso sexual en el trabajo',
                'texto_completo'=> 'Ley 1257 de 2008, ámbito laboral: El empleador está obligado a implementar mecanismos adecuados y efectivos para dar a conocer a los trabajadores la normativa sobre no discriminación y no violencia. El acoso sexual en el lugar de trabajo consiste en cualquier comportamiento verbal, no verbal o físico no deseado de índole sexual que tenga como objeto o efecto atentar contra la dignidad de la víctima, crear un entorno intimidatorio, hostil, degradante, humillante u ofensivo. El empleador que permita, tolere o encubra el acoso sexual puede ser sancionado administrativamente. La conducta puede configurar también el numeral 3 del artículo 62 del CST (actos inmorales en el trabajo) como causal de terminación del contrato del acosador.',
                'fuente'        => 'Ley 1257 de 2008',
                'categoria'     => 'Acoso sexual',
                'orden'         => 31,
            ],

            // ── Resolución 2646 de 2008 — Riesgo psicosocial ───────────────────
            [
                'codigo'        => 'RES2646-3',
                'titulo'        => 'Factores de riesgo psicosocial en el trabajo',
                'descripcion'   => 'Resolución 2646/2008: Define el acoso laboral como factor de riesgo psicosocial',
                'texto_completo'=> 'Resolución 2646 de 2008 del Ministerio de la Protección Social, Artículo 3: Define como factor de riesgo psicosocial intralaboral el acoso laboral, entendido como cualquier conducta persistente y demostrable ejercida sobre un empleado o trabajador por parte de un empleador, jefe o superior jerárquico, un compañero de trabajo o subalterno, encaminada a infundir miedo, intimidación, terror y angustia, a causar perjuicio laboral, generar desmotivación o inducir la renuncia. El empleador tiene la obligación de identificar, evaluar e intervenir los factores de riesgo psicosocial, incluyendo el acoso laboral.',
                'fuente'        => 'Resolución 2646 de 2008',
                'categoria'     => 'Acoso laboral',
                'orden'         => 40,
            ],
        ];

        foreach ($articulos as $data) {
            ArticuloLegal::updateOrCreate(
                ['codigo' => $data['codigo']],
                $data
            );
        }

        $this->command->info('✅ Artículos legales cargados: ' . count($articulos));
        $this->command->info('ℹ️  Ejecute: php artisan articulos:generar-embeddings');
    }
}
