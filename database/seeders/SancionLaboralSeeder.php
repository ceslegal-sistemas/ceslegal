<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SancionLaboral;

class SancionLaboralSeeder extends Seeder
{
    /**
     * Run the database seeder.
     * Incluye las relaciones de reincidencia (sancion_padre_id, orden_reincidencia)
     */
    public function run(): void
    {
        // Definir grupos de reincidencia para vincular después
        $gruposReincidencia = [
            'retardo_15min' => null,
            'salir_sin_autorizacion' => null,
            'cambio_horario' => null,
            'violacion_leve' => null,
            'mal_uso_herramientas' => null,
            'no_usar_uniforme' => null,
            'no_asistir_capacitaciones' => null,
        ];

        $sanciones = [
            // ==================== FALTAS LEVES ====================

            // Grupo: Retardo de 15 minutos (1ra, 2da, 3ra vez) + 4ta vez es grave
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Retardo de 15 minutos (1ra vez)',
                'descripcion' => 'El retardo hasta de quince (15) minutos en la hora de entrada al trabajo sin excusa suficiente, cuando no cause perjuicio de consideración a la empresa, por primera vez.',
                'tipo_sancion' => 'llamado_atencion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 1,
                'orden_reincidencia' => 1,
                'grupo' => 'retardo_15min',
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Retardo de 15 minutos (2da vez)',
                'descripcion' => 'El retardo hasta de quince (15) minutos en la hora de entrada al trabajo sin excusa suficiente, cuando no cause perjuicio de consideración a La Empresa por segunda vez.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 3,
                'orden' => 2,
                'orden_reincidencia' => 2,
                'grupo' => 'retardo_15min',
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Retardo de 15 minutos (3ra vez)',
                'descripcion' => 'El retardo hasta de quince (15) minutos en la hora de entrada al trabajo sin excusa suficiente, cuando no cause perjuicio de consideración a La Empresa por tercera vez.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 5,
                'orden' => 3,
                'orden_reincidencia' => 3,
                'grupo' => 'retardo_15min',
            ],

            // Sin reincidencia
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Falta en mañana o tarde sin excusa',
                'descripcion' => 'Falta total al trabajo en la mañana o en la tarde, sin excusa suficiente, cuando no cause perjuicio de consideración a la empresa.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 7,
                'orden' => 4,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Falta total de 1 día sin excusa',
                'descripcion' => 'Falta total al trabajo durante un (1) día o jornada completa, sin excusa suficiente, cuando no cause perjuicio de consideración a la Empresa.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 40,
                'orden' => 5,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],

            // Grupo: Salir sin autorización (1ra, 2da vez)
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Salir sin autorización (1ra vez)',
                'descripcion' => 'Salir de las dependencias de la Empresa durante las horas de trabajo por poco tiempo, sin autorización previa cuando no cause perjuicio de consideración a la empresa.',
                'tipo_sancion' => 'llamado_atencion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 6,
                'orden_reincidencia' => 1,
                'grupo' => 'salir_sin_autorizacion',
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Salir sin autorización (2da vez)',
                'descripcion' => 'Salir de las dependencias de la Empresa durante las horas de trabajo en segunda ocasión y por poco tiempo, sin autorización previa cuando no cause perjuicio de consideración a la empresa.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 15,
                'orden' => 7,
                'orden_reincidencia' => 2,
                'grupo' => 'salir_sin_autorizacion',
            ],

            // Grupo: Cambio de horario sin autorización (1ra, 2da vez) + 3ra vez es grave
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Cambio de horario sin autorización (1ra vez)',
                'descripcion' => 'Cambio en el horario de trabajo asignado sin autorización.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 3,
                'orden' => 8,
                'orden_reincidencia' => 1,
                'grupo' => 'cambio_horario',
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Cambio de horario sin autorización (2da vez)',
                'descripcion' => 'Cambio en el horario de trabajo asignado sin autorización, por segunda ocasión.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 15,
                'orden' => 9,
                'orden_reincidencia' => 2,
                'grupo' => 'cambio_horario',
            ],

            // Grupo: Violación leve de obligaciones (1ra, 2da vez)
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Violación leve de obligaciones (1ra vez)',
                'descripcion' => 'Violación leve por parte del trabajador de las obligaciones contractuales o reglamentarias si se presenta por primera vez.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 8,
                'orden' => 10,
                'orden_reincidencia' => 1,
                'grupo' => 'violacion_leve',
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Violación leve de obligaciones (2da vez)',
                'descripcion' => 'Violación leve por parte del trabajador de las obligaciones contractuales o reglamentarias si se presenta por segunda vez.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 15,
                'orden' => 11,
                'orden_reincidencia' => 2,
                'grupo' => 'violacion_leve',
            ],

            // Sin reincidencia
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'No acatar indicaciones del jefe',
                'descripcion' => 'No acatar las indicaciones del jefe inmediato, siempre que éstas no lesionen su dignidad.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 3,
                'orden' => 12,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Trabajos diferentes sin autorización',
                'descripcion' => 'Realizar trabajos diferentes a los propios de su oficio, sin la debida autorización.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 5,
                'orden' => 13,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Rifas, ventas o juegos en instalaciones',
                'descripcion' => 'Hacer o participar en rifas, suscripciones, propagandas, ventas o juegos de azar, dentro de las Instalaciones.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 8,
                'orden' => 14,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Perder tiempo o entorpecer trabajo',
                'descripcion' => 'Perder el tiempo o entorpecer el trabajo a otros.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 8,
                'orden' => 15,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Falta de respeto leve',
                'descripcion' => 'Faltar al respeto de forma leve a los visitantes, empleados, compañeros de trabajo, clientes de la Empresa o cualquier persona con la que tenga contacto o relación en razón de sus actividades laborales.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 3,
                'orden' => 16,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],

            // Grupo: Mal uso herramientas (1ra, 2da vez) + 3ra vez es grave
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Mal uso herramientas (1ra vez)',
                'descripcion' => 'Hacer mal uso o dañar las herramientas y el material de trabajo de la Empresa de forma culposa o negligente por primera vez.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 8,
                'orden' => 17,
                'orden_reincidencia' => 1,
                'grupo' => 'mal_uso_herramientas',
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Mal uso herramientas (2da vez)',
                'descripcion' => 'Hacer mal uso o dañar las herramientas y el material de trabajo de la Empresa de forma culposa o negligente por segunda vez.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 15,
                'orden' => 18,
                'orden_reincidencia' => 2,
                'grupo' => 'mal_uso_herramientas',
            ],

            // Sin reincidencia
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Disminuir ritmo de trabajo',
                'descripcion' => 'Disminuir el ritmo de trabajo intencionalmente que no causare perjuicio a la empresa.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 8,
                'orden' => 19,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'No trabajar según metodología',
                'descripcion' => 'No trabajar de acuerdo con la metodología y sistemas definidos en la propuesta educativa de la Empresa.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 5,
                'orden' => 20,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'No informar sobre faltas de otros',
                'descripcion' => 'No informar sobre faltas o acciones indebidas, cometidas por algún trabajador, en contra de la Empresa, siendo conocedor de las mismas.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 5,
                'orden' => 21,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],

            // Grupo: No usar uniforme (1ra, 2da, 3ra vez)
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'No usar uniforme (1ra vez)',
                'descripcion' => 'No usar el uniforme adecuadamente por primera vez.',
                'tipo_sancion' => 'llamado_atencion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 22,
                'orden_reincidencia' => 1,
                'grupo' => 'no_usar_uniforme',
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'No usar uniforme (2da vez)',
                'descripcion' => 'No usar el uniforme adecuadamente por segunda vez.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 5,
                'orden' => 23,
                'orden_reincidencia' => 2,
                'grupo' => 'no_usar_uniforme',
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'No usar uniforme (3ra vez)',
                'descripcion' => 'No usar el uniforme adecuadamente por tercera vez.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 15,
                'orden' => 24,
                'orden_reincidencia' => 3,
                'grupo' => 'no_usar_uniforme',
            ],

            // Grupo: No asistir a capacitaciones (1ra, 2da vez) + 3ra vez es grave
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'No asistir a capacitaciones (1ra vez)',
                'descripcion' => 'No asistir por primera vez a conferencias, charlas y capacitaciones programadas por la Empresa, sin la debida justificación.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 5,
                'orden' => 25,
                'orden_reincidencia' => 1,
                'grupo' => 'no_asistir_capacitaciones',
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'No asistir a capacitaciones (2da vez)',
                'descripcion' => 'No asistir por segunda vez a conferencia, charlas y capacitaciones programadas por la Empresa, sin la debida justificación.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 30,
                'orden' => 26,
                'orden_reincidencia' => 2,
                'grupo' => 'no_asistir_capacitaciones',
            ],

            // Sin reincidencia
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Incurrir en prohibición del reglamento',
                'descripcion' => 'Incurrir en una prohibición establecida en el presente reglamento o cualquier circular, contrato o comunicado por parte de la empresa.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 8,
                'orden' => 27,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'leve',
                'nombre_claro' => 'Omitir cumplir obligación del reglamento',
                'descripcion' => 'Omitir cumplir una obligación establecida en el presente reglamento o cualquier circular, contrato o comunicado por parte de la empresa.',
                'tipo_sancion' => 'suspension',
                'dias_suspension_min' => null,
                'dias_suspension_max' => 8,
                'orden' => 28,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],

            // ==================== FALTAS GRAVES ====================

            // Reincidencia de retardo (4ta vez)
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Retardo de 15 minutos (4ta vez)',
                'descripcion' => 'Retardo hasta de quince (15) minutos en la hora de entrada al trabajo sin excusa suficiente por cuarta vez.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 29,
                'orden_reincidencia' => 4,
                'grupo' => 'retardo_15min',
            ],

            // Sin reincidencia
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Violación grave de obligaciones o reincidencia',
                'descripcion' => 'La violación grave por parte del trabajador de las obligaciones o de las prohibiciones contractuales o reglamentarias, o la repetición en la violación de las mismas obligaciones o prohibiciones.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 30,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Ausentarse del puesto sin reemplazo',
                'descripcion' => 'Ausentarse injustificadamente del puesto de trabajo sin ser reemplazado por el compañero de trabajo. Sea dentro del turno o al terminar éste.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 31,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Laborar embriagado o drogado',
                'descripcion' => 'Laborar en las instalaciones de la empresa en estado de embriaguez, bajo la influencia de narcóticos o drogas enervantes.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 32,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Falta injustificada de un día o más',
                'descripcion' => 'Faltar injustificadamente a laborar durante un (1) día o más.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 33,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Vender loterías, rifas o juegos de azar',
                'descripcion' => 'Vender, distribuir en cualquier forma loterías, chances, rifas, colectas, jugar dinero u otros objetos o en general juegos de azar, dentro de las instalaciones de la empresa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 34,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Abandonar sitio de trabajo sin autorización',
                'descripcion' => 'Abandonar sin autorización del jefe, antes de tiempo, el sitio de trabajo.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 35,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'No usar uniforme o EPP recurrentemente',
                'descripcion' => 'La no utilización recurrente del uniforme completo y/o equipos de protección personal suministrada por la empresa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 36,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Pedir o recibir propinas',
                'descripcion' => 'Pedir y/o recibir propinas de cualquier clase a los proveedores, transportadores, clientes o a cualquier persona o entidad que tenga negocios con la empresa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 37,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Negarse a laborar en turno asignado',
                'descripcion' => 'Negarse a laborar en el turno que en cualquier momento le asigne la empresa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 38,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Negociaciones no autorizadas',
                'descripcion' => 'Realizar negociaciones con proveedores o clientes no autorizadas por la empresa en sus políticas de compras o de ventas.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 39,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],

            // Reincidencia de mal uso herramientas (3ra vez)
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Mal uso herramientas (3ra vez)',
                'descripcion' => 'Hacer mal uso o dañar las herramientas y el material de trabajo de la Empresa de forma culposa o negligente por tercera vez.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 40,
                'orden_reincidencia' => 3,
                'grupo' => 'mal_uso_herramientas',
            ],

            // Reincidencia de no asistir capacitaciones (3ra vez)
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'No asistir a capacitaciones (3ra vez)',
                'descripcion' => 'No asistir por tercera vez a conferencias, charlas y capacitaciones programadas por la Empresa, sin la debida justificación.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 41,
                'orden_reincidencia' => 3,
                'grupo' => 'no_asistir_capacitaciones',
            ],

            // Sin reincidencia
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Agresión física en instalaciones',
                'descripcion' => 'Agredir físicamente a otra persona durante las horas de trabajo dentro de las dependencias de la Empresa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 42,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Ocultar información o mentir',
                'descripcion' => 'Ocultar información o suministrar una versión contraria a la verdad que pueda ocasionar perjuicios a la Empresa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 43,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Divulgar información confidencial',
                'descripcion' => 'Sustraer, copiar, transferir, divulgar o utilizar, sin la debida autorización, información, documentos, bases de datos, archivos físicos o digitales, de propiedad de la Empresa o de cualquiera de sus Clientes.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 44,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Extraer elementos sin autorización',
                'descripcion' => 'Extraer elementos o herramientas de la Empresa o del Cliente sin autorización.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 45,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Falta de respeto grave',
                'descripcion' => 'Faltar al respeto de forma grave a los visitantes, superiores, compañeros de trabajo, cliente de la empresa o cualquier persona con la cual tenga relación con ocasión a sus actividades laborales.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 46,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Actitud grosera en descargos',
                'descripcion' => 'Incurrir en forma amenazante, grosera e irrespetuosa en el momento en que se realice un procedimiento de descargos.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 47,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],

            // Reincidencia de cambio de horario (3ra vez)
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Cambio de horario sin autorización (3ra vez)',
                'descripcion' => 'Cambio en el horario de trabajo asignado sin autorización por tercera ocasión.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 48,
                'orden_reincidencia' => 3,
                'grupo' => 'cambio_horario',
            ],

            // Sin reincidencia
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Negarse a requisas o actitud agresiva',
                'descripcion' => 'Negarse a las requisas que la empresa establezca y adopte una actitud agresiva, grosera e irrespetuosa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 49,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Agresión física o verbal',
                'descripcion' => 'Agredir física o verbalmente a compañeros de trabajo, superiores, clientes, directivos, proveedores y aquellas personas con las cuales tenga relación con ocasión al desarrollo de sus actividades laborales.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 50,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Acoso sexual',
                'descripcion' => 'Ejercer actos de acoso sexual en contra de compañeros de trabajo, supervisores, clientes, directivos o cualquier persona con la cual tenga relación con ocasión del desarrollo de sus actividades laborales.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 51,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Daño intencional de herramientas',
                'descripcion' => 'Dañar con intención dolosa, herramientas de trabajo, muebles, objetos u enseres de la empresa, compañeros de trabajo, de clientes o proveedores.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 52,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Hurto',
                'descripcion' => 'Hurtar herramientas, muebles, objetos u enseres de la empresa, superiores, compañeros de trabajo, clientes, proveedores o cualquier persona con la que tenga relación en el ejercicio de sus actividades laborales.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 53,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Salir causando perjuicio',
                'descripcion' => 'Salir de las dependencias de la Empresa durante las horas de trabajo y por poco o mucho tiempo, sin autorización previa cuando cause perjuicio de consideración a la empresa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 54,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Omitir actividad que retrase operaciones',
                'descripcion' => 'Omitir realizar una actividad laboral asignada por el empleador, por un período de tiempo prolongado que retrase la realización de actividades al interior de la empresa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 55,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Acto imprudente que ocasione lesión',
                'descripcion' => 'Realizar un acto imprudente o negligente, que ocasione la lesión o herida a alguno de sus compañeros de trabajo, superiores, clientes, proveedores o cualquier persona con la cual tenga relación en razón de sus actividades laborales.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 56,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Omisión que ocasione perjuicio',
                'descripcion' => 'Omitir la realización de una actividad laboral que le fuese asignada por su empleador, que ocasione un perjuicio para la empresa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 57,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Negarse a prueba de alcoholimetría',
                'descripcion' => 'Negarse a practicar la prueba de alcoholimetría que le ordene su empleador a realizarse.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 58,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Reincidir 3 veces en faltas leves',
                'descripcion' => 'Reincidir en tres (3) oportunidades en la comisión de faltas leves.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 59,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Pedir o solicitar dinero',
                'descripcion' => 'Pedir y/o solicitar dinero de cualquier clase a los compañeros de trabajo, superiores, proveedores, transportadores, clientes o a cualquier persona o entidad que tenga negocios con la empresa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 60,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Hacer colectas sin autorización',
                'descripcion' => 'Hacer colectas dentro de la jornada laboral sin autorización de la empresa.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 61,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Golpear animales',
                'descripcion' => 'Golpear de forma dolosa o culposa a un animal doméstico o salvaje durante su jornada laboral.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 62,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
            [
                'tipo_falta' => 'grave',
                'nombre_claro' => 'Falta que cause perjuicio considerable',
                'descripcion' => 'La falta total del trabajador a sus labores durante el día, sin excusa suficiente y causare perjuicio de consideración a la empresa por primera vez.',
                'tipo_sancion' => 'terminacion',
                'dias_suspension_min' => null,
                'dias_suspension_max' => null,
                'orden' => 63,
                'orden_reincidencia' => null,
                'grupo' => null,
            ],
        ];

        // Primera pasada: insertar todas las sanciones y guardar IDs de los padres (1ra vez)
        $inserted = 0;
        $sancionesCreadas = [];

        foreach ($sanciones as $sancionData) {
            $grupo = $sancionData['grupo'] ?? null;
            unset($sancionData['grupo']);

            // Determinar sancion_padre_id
            $sancionPadreId = null;
            if ($grupo && $sancionData['orden_reincidencia'] > 1) {
                // Es una reincidencia, buscar el padre
                $sancionPadreId = $gruposReincidencia[$grupo] ?? null;
            }

            $sancionData['sancion_padre_id'] = $sancionPadreId;

            $sancion = SancionLaboral::updateOrCreate(
                ['nombre_claro' => $sancionData['nombre_claro']],
                $sancionData
            );

            // Si es la primera vez (orden_reincidencia = 1), guardar como padre del grupo
            if ($grupo && $sancionData['orden_reincidencia'] === 1) {
                $gruposReincidencia[$grupo] = $sancion->id;
            }

            $sancionesCreadas[$sancionData['nombre_claro']] = $sancion->id;

            if ($sancion->wasRecentlyCreated) {
                $inserted++;
            }
        }

        // Segunda pasada: actualizar sancion_padre_id de las reincidencias que se crearon antes que sus padres
        foreach ($sanciones as $sancionData) {
            $grupo = $sancionData['grupo'] ?? null;
            if ($grupo && ($sancionData['orden_reincidencia'] ?? 0) > 1) {
                $sancionPadreId = $gruposReincidencia[$grupo] ?? null;
                if ($sancionPadreId) {
                    SancionLaboral::where('nombre_claro', $sancionData['nombre_claro'])
                        ->update(['sancion_padre_id' => $sancionPadreId]);
                }
            }
        }

        $this->command->info("Sanciones laborales: {$inserted} nuevas, " . (count($sanciones) - $inserted) . " actualizadas.");
    }
}
