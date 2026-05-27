<?php

namespace App\Console\Commands;

use App\Models\ArticuloLegal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Descarga artículos del Código Sustantivo del Trabajo desde leyes.co
 * y los importa con embeddings Gemini en articulos_legales.
 * El comando es idempotente: si un artículo ya existe, se actualiza su título y descripción sin modificar el embedding a menos que se use --force.
 *
 * Uso:
 *   php artisan cst:scraper                # Importa/actualiza todos
 *   php artisan cst:scraper --force        # Regenera embeddings aunque ya existan
 *   php artisan cst:scraper --solo=62      # Solo el artículo 62
 */
class ScrapearArticulosCst extends Command
{
    protected $signature   = 'cst:scraper
                                {--force  : Regenerar embeddings aunque ya existan}
                                {--solo=  : Scrapear solo el artículo indicado (ej: --solo=62)}';
    protected $description = 'Descarga artículos del CST desde leyes.co e importa con embeddings para RAG';

    private const BASE_URL  = 'https://leyes.co/codigo_sustantivo_del_trabajo/';
    private const FUENTE    = 'CST';

    /**
     * Artículos que NO se scraperan porque tienen versión manual más actualizada.
     * Mantener sincronizado con ImportarArticulosCst.php.
     */
    private array $excluidos = [];

    /**
     * Lista de artículos a importar.
     * Formato: numero => [categoria, orden]
     */
    /**
     * Artículos cuya URL en leyes.co difiere del número puro.
     * Clave: número del artículo | Valor: sufijo de la URL antes de ".htm"
     */
    private array $urlOverrides = [
        // Artículos 1-9: leyes.co usa ordinal con "o" (1o.htm, 2o.htm, …)
        '1'   => '1o',
        '2'   => '2o',
        '3'  => '3o',
        '4'  => '4o',
        '5'  => '5o',
        '6'   => '6o',
        '7'   => '7o',
        '8'  => '8o',
        '9'  => '9o',
    ];

    private array $articulos = [

        // ── PRINCIPIOS GENERALES Y CAMPO DE APLICACIÓN ───────────────────────
        '1'   => ['principios',                   1],  // Objeto
        '2'   => ['principios',                   2],  // Aplicacion territorial
        '3'   => ['principios',                   3],  // Relaciones que regula
        '4'   => ['principios',                   4],  // Empleados Públicos
        '5'   => ['principios',                   5],  // Definicion de trabajo
        '6'   => ['principios',                   6],  // Trabajo ocasional
        '7'   => ['principios',                   7],  // Obligatoriedad del trabajo
        '8'   => ['principios',                   8],  // Libertad de trabajo
        '9'   => ['principios',                   9],  // Proteccion al trabajo
        10   => ['principios',                  10],  // Igualdad de los trabajadores y las trabajadoras
        11   => ['principios',                  11],  // Derecho al trabajo
        12   => ['principios',                  12],  // Derechos de asociacion y huelga
        13   => ['principios',                  13],  // Minimo de derechos y garantias
        14   => ['principios',                  14],  // Caracter de orden publico. irrenunciabilidad
        15   => ['principios',                  15],  // Validez de la transaccion
        16   => ['principios',                  16],  // Efecto
        17   => ['principios',                  17],  // Organos de control
        18   => ['principios',                  18],  // Norma general de interpretacion
        19   => ['principios',                  19],  // Normas de aplicacion supletoria
        20   => ['principios',                  20],  // Conflictos de leyes
        21   => ['principios',                  21],  // Normas mas favorables

        // ── CONTRATO DE TRABAJO ──────────────────────────────────────────────
        22   => ['contrato',                    22],  // Definicion
        23   => ['contrato',                    23],  // Elementos esenciales
        24   => ['contrato',                    24],  // Presuncion
        25   => ['contrato',                    25],  // Concurrencia de contratos
        26   => ['contrato',                    26],  // Coexistencia de contratos
        27   => ['contrato',                    27],  // Remuneracion del trabajo
        28   => ['contrato',                    28],  // Utilidades y perdidas
        29   => ['contrato',                    29],  // Capacidad
        30   => ['contrato',                    30],  // Incapacidad
        31   => ['contrato',                    31],  // Trabajo sin autorizacion
        32   => ['contrato',                    32],  // Representantes del empleador
        33   => ['contrato',                    33],  // Sucursales
        34   => ['contrato',                    34],  // Contratistas y subcontratistas
        35   => ['contrato',                    35],  // Simple intermediario
        36   => ['contrato',                    36],  // Responsabilidad solidaria
        37   => ['contrato',                    37],  // Forma
        38   => ['contrato',                    38],  // Contrato verbal
        39   => ['contrato',                    39],  // Contrato escrito
        40   => ['contrato',                    40],  // Carné
        41   => ['contrato',                    41],  // Registro de ingreso de trabajadores
        42   => ['contrato',                    42],  // Certificacion del contrato
        43   => ['contrato',                    43],  // Clausulas ineficaces
        44   => ['contrato',                    44],  // Clausula de no concurrencia
        45   => ['contrato',                    45],  // Duracion
        46   => ['contrato',                    46],  // Contratos a término fijo y de obra o labor determinada
        47   => ['contrato',                    47],  // El contrato laboral a término indefinido
        48   => ['contrato',                    48],  // Clausula de reserva
        49   => ['contrato',                    49],  // Prorroga
        50   => ['contrato',                    50],  // Revision

        // ── SUSPENSIÓN DEL CONTRATO ──────────────────────────────────────────
        51   => ['contrato',                    51],  // Suspension
        52   => ['contrato',                    52],  // Reanudacion del trabajo
        53   => ['contrato',                    53],  // Efectos de la suspension
        54   => ['contrato',                    54],  // Prueba del contrato

        // ── OBLIGACIONES, PROHIBICIONES Y TERMINACIÓN ────────────────────────
        55   => ['obligaciones',                55],  // Ejecucion de buena fe
        56   => ['obligaciones',                56],  // Obligaciones de las partes en general
        57   => ['obligaciones',                57],  // Obligaciones especiales del empleador
        58   => ['obligaciones',                58],  // Obligaciones especiales del trabajador
        59   => ['prohibiciones',               59],  // Prohibiciones a los empleadores
        '59A' => ['prohibiciones',             591],  // Prohibición al empleador sobre maniobras de elusión
        60   => ['prohibiciones',               60],  // Prohibiciones a los trabajadores
        61   => ['terminacion',                 61],  // Terminacion del contrato
        62   => ['terminacion',                 62],  // Terminacion del contrato por justa causa
        63   => ['terminacion',                 63],  // Terminación con previo aviso
        64   => ['terminacion',                 64],  // Terminacion unilateral del contrato de trabajo sin justa causa
        65   => ['terminacion',                 65],  // Indemnizacion por falta de pago
        66   => ['terminacion',                 66],  // Manifestacion del motivo de la terminacion
        67   => ['terminacion',                 67],  // Definicion
        68   => ['terminacion',                 68],  // Mantenimiento del contrato de trabajo
        69   => ['terminacion',                 69],  // Responsabilidad de los empleadores
        70   => ['terminacion',                 70],  // Estipulaciones entre los empleadores

        // ── TRABAJADORES EN MISIÓN ───────────────────────────────────────────
        71   => ['mision',                      71],  // Definicion
        72   => ['mision',                      72],  // Enganche para el exterior
        73   => ['mision',                      73],  // Gastos de movilizacion
        74   => ['mision',                      74],  // Proporcion e igualdad de condiciones
        75   => ['mision',                      75],  // Autorizaciones para variar la proporcion

        // ── PERÍODO DE PRUEBA ────────────────────────────────────────────────
        76   => ['contrato',                    76],  // Definicion
        77   => ['contrato',                    77],  // Estipulacion
        78   => ['contrato',                    78],  // Duración máxima
        79   => ['contrato',                    79],  // Prorroga
        80   => ['contrato',                    80],  // Efecto juridico
        81   => ['aprendizaje',                 81],  // Naturaleza y características de la relación de aprendizaje
        '81A' => ['aprendizaje',              811],  // Internos de medicina
        82   => ['aprendizaje',                 82],  // Capacidad
        83   => ['aprendizaje',                 83],  // Estipulaciones esenciales
        84   => ['aprendizaje',                 84],  // Forma
        85   => ['aprendizaje',                 85],  // Obligaciones especiales del aprendiz
        86   => ['aprendizaje',                 86],  // Obligaciones especiales del empleador
        87   => ['aprendizaje',                 87],  // Duracion
        88   => ['aprendizaje',                 88],  // Efecto juridico
        89   => ['especiales',                  89],  // Contrato de trabajo
        90   => ['especiales',                  90],  // Autorizacion previa
        91   => ['especiales',                  91],  // Libro de trabajadores
        92   => ['especiales',                  92],  // Libreta de salario
        93   => ['especiales',                  93],  // Informes
        94   => ['especiales',                  94],  // Agentes colocadores de pólizas de seguros y títulos de capitalización
        95   => ['especiales',                  95],  // Clases de agentes
        96   => ['especiales',                  96],  // Agentes dependientes
        97   => ['especiales',                  97],  // Agentes independientes
        '97-A' => ['especiales',               971],  // Colocadores de apuestas permanentes
        98   => ['especiales',                  98],  // Contrato de trabajo
        99   => ['especiales',                  99],  // Hay contrato de trabajo entre los trabajadores de las Notarías
        100  => ['especiales',                 100],  // Responsabilidad de los notarios y registradores
        101  => ['especiales',                 101],  // Duracion del contrato de trabajo
        102  => ['especiales',                 102],  // Vacaciones y cesantias
        103  => ['especiales',                 103],  // Terminacion del contrato
        '103C' => ['especiales',             1031],  // Protección al trabajo femenino rural y campesino

        // ── REGLAMENTO INTERNO DE TRABAJO ────────────────────────────────────
        104  => ['reglamento_interno',         104],  // Definicion
        105  => ['reglamento_interno',         105],  // Obligacion de adoptarlo
        106  => ['reglamento_interno',         106],  // Elaboracion
        107  => ['reglamento_interno',         107],  // Efecto juridico
        108  => ['reglamento_interno',         108],  // Contenido
        109  => ['reglamento_interno',         109],  // Clausulas ineficaces
        110  => ['reglamento_interno',         110],  // Normas excluidas

        // ── SANCIONES DISCIPLINARIAS ─────────────────────────────────────────
        111  => ['procedimiento_disciplinario', 111],  // Sanciones disciplinarias
        112  => ['procedimiento_disciplinario', 112],  // Suspension del trabajo
        113  => ['procedimiento_disciplinario', 113],  // Multas
        114  => ['procedimiento_disciplinario', 114],  // Sanciones no previstas
        115  => ['procedimiento_disciplinario', 115],  // Procedimiento para aplicar sanciones
        116  => ['procedimiento_disciplinario', 116],  // Aprobacion y procedimiento
        117  => ['procedimiento_disciplinario', 117],  // Forma de presentacion
        118  => ['procedimiento_disciplinario', 118],  // Investigacion
        119  => ['procedimiento_disciplinario', 119],  // Objeciones
        120  => ['procedimiento_disciplinario', 120],  // Publicación
        121  => ['procedimiento_disciplinario', 121],  // Vigencia
        122  => ['procedimiento_disciplinario', 122],  // Prueba de la publicacion
        123  => ['procedimiento_disciplinario', 123],  // Plazo para la presentacion
        124  => ['procedimiento_disciplinario', 124],  // Revision
        125  => ['procedimiento_disciplinario', 125],  // Procedimiento de revision
        126  => ['procedimiento_disciplinario', 126],  // Prohibiciones

        // ── SALARIO ──────────────────────────────────────────────────────────
        127  => ['salario',                    127],  // Elementos integrantes
        128  => ['salario',                    128],  // Pagos que no constituyen salarios
        129  => ['salario',                    129],  // Salario en especie
        130  => ['salario',                    130],  // Viaticos
        131  => ['salario',                    131],  // Propinas
        132  => ['salario',                    132],  // Formas y libertad de estipulacion
        133  => ['salario',                    133],  // Jornal y sueldo
        134  => ['salario',                    134],  // Periodos de pago
        135  => ['salario',                    135],  // Estipulacion en moneda extranjera
        136  => ['salario',                    136],  // Prohibicion de trueque
        137  => ['salario',                    137],  // Venta de mercancias y viveres por parte del empleador
        138  => ['salario',                    138],  // Lugar y tiempo de pago
        139  => ['salario',                    139],  // A quien se hace el pago
        140  => ['salario',                    140],  // Salario sin prestacion del servicio
        141  => ['salario',                    141],  // Salarios basicos para prestaciones
        142  => ['salario',                    142],  // Irrenunciabilidad y prohibicion de cederlo
        143  => ['salario',                    143],  // A trabajo de igual valor, salario igual
        144  => ['salario',                    144],  // Falta de estipulacion
        145  => ['salario_minimo',             145],  // Definicion
        146  => ['salario_minimo',             146],  // Factores para fijarlo
        147  => ['salario_minimo',             147],  // Procedimiento de fijacion
        148  => ['salario_minimo',             148],  // Efecto juridico
        149  => ['salario',                    149],  // Descuentos prohibidos
        150  => ['salario',                    150],  // Descuentos permitidos
        151  => ['salario',                    151],  // Autorizacion especial
        152  => ['salario',                    152],  // Prestamos para viviendas
        153  => ['salario',                    153],  // Intereses de los prestamos
        154  => ['salario',                    154],  // Regla general
        155  => ['salario',                    155],  // Embargo parcial del excedente
        156  => ['salario',                    156],  // Excepcion a favor de cooperativas y pensiones alimenticias
        157  => ['salario',                    157],  // Prelacion de creditos por salarios, prestaciones sociales e indemnizaciones laborales

        // ── JORNADA LABORAL ──────────────────────────────────────────────────
        158  => ['jornada',                    158],  // Jornada ordinaria
        159  => ['jornada',                    159],  // Trabajo suplementario
        160  => ['jornada',                    160],  // Trabajo ordinario y nocturno
        161  => ['jornada',                    161],  // Duracion
        162  => ['jornada',                    162],  // Excepciones en determinadas actividades
        163  => ['jornada',                    163],  // Excepciones en casos especiales
        164  => ['jornada',                    164],  // Descanso en la tarde del sabado
        165  => ['jornada',                    165],  // Trabajo por turnos
        166  => ['jornada',                    166],  // Trabajo sin solucion de continuidad
        167  => ['jornada',                    167],  // Distribucion de las horas de trabajo
        168  => ['jornada',                    168],  // Tasas y liquidacion de recargos
        169  => ['jornada',                    169],  // Base del recargo nocturno
        170  => ['jornada',                    170],  // Salario en caso de turnos

        // ── TRABAJADORES DE MENORES EDADES ───────────────────────────────────
        171  => ['menores',                    171],  // Edad minima
        172  => ['menores',                    172],  // Norma general
        173  => ['menores',                    173],  // Remuneracion
        174  => ['menores',                    174],  // Valor de la remuneracion
        175  => ['menores',                    175],  // Excepciones
        176  => ['menores',                    176],  // Salarios variables
        177  => ['menores',                    177],  // Remuneracion
        178  => ['menores',                    178],  // Suspension del trabajo en otros dias de fiesta

        // ── DESCANSOS Y DOMINICALES ──────────────────────────────────────────
        179  => ['descansos',                  179],  // Remuneración en días de descanso obligatorio
        180  => ['descansos',                  180],  // Trabajo excepcional
        181  => ['descansos',                  181],  // Descanso compensatorio
        182  => ['descansos',                  182],  // Tecnicos
        183  => ['descansos',                  183],  // Formas del descanso compensatorio
        184  => ['descansos',                  184],  // Labores no susceptibles de suspension
        185  => ['descansos',                  185],  // Aviso sobre trabajo dominical

        // ── VACACIONES ───────────────────────────────────────────────────────
        186  => ['vacaciones',                 186],  // Duracion
        187  => ['vacaciones',                 187],  // Epoca de vacaciones
        188  => ['vacaciones',                 188],  // Interrupcion
        189  => ['vacaciones',                 189],  // Compensacion en dinero de las vacaciones
        190  => ['vacaciones',                 190],  // Acumulacion

        // ── CESANTÍA ──────────────────────────────────────────────────────────
        191  => ['cesantia',                   191],  // Empleados de manejo
        192  => ['cesantia',                   192],  // Remuneracion
        193  => ['cesantia',                   193],  // Regla general
        194  => ['cesantia',                   194],  // Definicion de empresas
        195  => ['cesantia',                   195],  // Definicion y prueba del capital de la empresa
        196  => ['cesantia',                   196],  // Coexistencia de prestaciones
        197  => ['cesantia',                   197],  // Trabajadores de jornada incompleta
        198  => ['cesantia',                   198],  // Fraude a la ley

        // ── RIESGOS PROFESIONALES ────────────────────────────────────────────
        199  => ['riesgos_profesionales',      199],  // Definicion de accidentes
        200  => ['riesgos_profesionales',      200],  // Definicion de enfermedad profesional
        201  => ['riesgos_profesionales',      201],  // Tabla de enfermedades profesionales
        202  => ['riesgos_profesionales',      202],  // Presuncion de enfermedad profesional
        203  => ['riesgos_profesionales',      203],  // Consecuencias
        204  => ['riesgos_profesionales',      204],  // Prestaciones
        205  => ['riesgos_profesionales',      205],  // Primeros auxilios
        206  => ['riesgos_profesionales',      206],  // Asistencia inmediata
        207  => ['riesgos_profesionales',      207],  // Contratacion de la asistencia
        208  => ['riesgos_profesionales',      208],  // Oposicion del trabajador a la asistencia
        209  => ['riesgos_profesionales',      209],  // Valuacion de incapacidades permanentes de accidentes de trabajo
        210  => ['riesgos_profesionales',      210],  // Aplicacion de la tabla
        211  => ['riesgos_profesionales',      211],  // Casos no comprendidos en la tabla
        212  => ['riesgos_profesionales',      212],  // Pago de la prestacion por muerte
        213  => ['riesgos_profesionales',      213],  // Muerte posterior al accidente o enfermedad
        214  => ['riesgos_profesionales',      214],  // Seguro de vida como prestacion por muerte
        215  => ['riesgos_profesionales',      215],  // Estado anterior de salud
        216  => ['riesgos_profesionales',      216],  // Culpa del empleador
        217  => ['riesgos_profesionales',      217],  // Calificacion de incapacidades
        218  => ['riesgos_profesionales',      218],  // Salario base para las prestaciones
        219  => ['riesgos_profesionales',      219],  // Seguro por riesgos profesionales
        220  => ['riesgos_profesionales',      220],  // Aviso al juez sobre la ocurrencia del accidente
        221  => ['riesgos_profesionales',      221],  // Aviso que debe dar el accidentado
        222  => ['riesgos_profesionales',      222],  // Revision de la calificacion
        223  => ['riesgos_profesionales',      223],  // Exoneracion de pago
        224  => ['riesgos_profesionales',      224],  // Empresas de capital inferior a diez mil pesos ($10,000)
        225  => ['riesgos_profesionales',      225],  // Empresas de capital mayor de diez mil pesos ($10,000) y menos de cincuenta mil pesos ($50,000)
        226  => ['riesgos_profesionales',      226],  // Empresas de capital mayor de cincuenta mil pesos ($50,000) y menor de ciento veiticinco mil pesos ($125,000)
        227  => ['riesgos_profesionales',      227],  // Valor de auxilio
        228  => ['riesgos_profesionales',      228],  // Salario variable
        229  => ['riesgos_profesionales',      229],  // Excepciones
        230  => ['riesgos_profesionales',      230],  // Suministro de calzado y vestido de labor
        231  => ['riesgos_profesionales',      231],  // Consideracion de hijos y otras personas
        232  => ['riesgos_profesionales',      232],  // Fecha de entrega
        233  => ['riesgos_profesionales',      233],  // Uso del calzado y vestido de labor
        234  => ['riesgos_profesionales',      234],  // Prohibicion de la compensacion en dinero
        235  => ['riesgos_profesionales',      235],  // Reglamentacion

        // ── PROTECCIÓN A LA MATERNIDAD ───────────────────────────────────────
        236  => ['grupos_protegidos',         236],  // Licencia en la época del parto e incentivos para la adecuada atención y cuidado del recién nacido
        237  => ['grupos_protegidos',         237],  // Descanso remunerado en caso de aborto
        238  => ['grupos_protegidos',         238],  // Descanso remunerado durante la lactancia
        239  => ['grupos_protegidos',         239],  // Prohibición de despido
        240  => ['grupos_protegidos',         240],  // Permiso para despedir
        241  => ['grupos_protegidos',         241],  // Nulidad del despido
        '241A' => ['grupos_protegidos',      2411],  // Medidas antidiscriminatorias en materia laboral
        242  => ['grupos_protegidos',         242],  // Trabajos prohibidos
        243  => ['grupos_protegidos',         243],  // Incumplimiento
        244  => ['grupos_protegidos',         244],  // Certificados medicos
        245  => ['grupos_protegidos',         245],  // Sala cunas
        246  => ['grupos_protegidos',         246],  // Computo de numero de trabajadoras
        247  => ['grupos_protegidos',         247],  // Regla general
        248  => ['grupos_protegidos',         248],  // Salario variable
        249  => ['grupos_protegidos',         249],  // Regla general
        250  => ['grupos_protegidos',         250],  // Perdida del derecho
        251  => ['grupos_protegidos',         251],  // Excepciones a la regla general
        252  => ['grupos_protegidos',         252],  // Cesantia restringida
        253  => ['grupos_protegidos',         253],  // Salario base para la liquidacion de la cesantia
        254  => ['grupos_protegidos',         254],  // Prohibicion de pagos parciales
        255  => ['grupos_protegidos',         255],  // Trabajadores llamados a filas
        256  => ['grupos_protegidos',         256],  // Financiacion de viviendas
        257  => ['grupos_protegidos',         257],  // Patrimonio de familia
        258  => ['grupos_protegidos',         258],  // Muerte del trabajador
        259  => ['grupos_protegidos',         259],  // Regla general
        260  => ['grupos_protegidos',         260],  // Derecho a la pension
        261  => ['grupos_protegidos',         261],  // Congelacion del salario base
        262  => ['grupos_protegidos',         262],  // Desde cuando se debe
        263  => ['grupos_protegidos',         263],  // Procedimiento
        264  => ['grupos_protegidos',         264],  // Archivos de las empresas
        265  => ['grupos_protegidos',         265],  // Prueba de la supervivencia
        266  => ['grupos_protegidos',         266],  // Concurrencia de jubilación y cesantía
        267  => ['grupos_protegidos',         267],  // Pension-sancion
        268  => ['grupos_protegidos',         268],  // Ferroviarios
        269  => ['grupos_protegidos',         269],  // Radioperadores
        270  => ['grupos_protegidos',         270],  // Otras excepciones
        271  => ['grupos_protegidos',         271],  // Pension con quince (15) años de servicio y cincuenta (50) años de edad
        272  => ['grupos_protegidos',         272],  // Excepcion especial
        273  => ['grupos_protegidos',         273],  // Nocion de continuidad
        274  => ['grupos_protegidos',         274],  // Suspension y retencion
        275  => ['grupos_protegidos',         275],  // Pension en caso de muerte
        276  => ['grupos_protegidos',         276],  // Seguros
        277  => ['grupos_protegidos',         277],  // Derecho al auxilio por enfermedad no profesional
        278  => ['grupos_protegidos',         278],  // Auxilio de invalidez
        279  => ['grupos_protegidos',         279],  // Valor de la pension
        280  => ['grupos_protegidos',         280],  // Declaratoria y calificacion
        281  => ['grupos_protegidos',         281],  // Pago de la pension
        282  => ['grupos_protegidos',         282],  // Tratamiento obligatorio
        283  => ['grupos_protegidos',         283],  // Recuperacion o reeducacion
        284  => ['grupos_protegidos',         284],  // Imcompatibilidad con el auxilio por enfermedad
        285  => ['grupos_protegidos',         285],  // Escuelas primarias
        286  => ['grupos_protegidos',         286],  // Estudios de especializacion tecnica
        287  => ['grupos_protegidos',         287],  // Escuelas de alfabetizacion
        288  => ['grupos_protegidos',         288],  // Reglamentacion
        289  => ['grupos_protegidos',         289],  // Empresas obligadas
        290  => ['grupos_protegidos',         290],  // Nomina
        291  => ['grupos_protegidos',         291],  // Caracter permanente
        292  => ['grupos_protegidos',         292],  // Valor
        293  => ['grupos_protegidos',         293],  // Beneficiarios
        294  => ['grupos_protegidos',         294],  // Demostracion del caracter del beneficiario y pago del seguro
        295  => ['grupos_protegidos',         295],  // Controversias entre beneficiarios
        296  => ['grupos_protegidos',         296],  // Causas de exclusion
        297  => ['grupos_protegidos',         297],  // Coexistencia de seguros
        298  => ['grupos_protegidos',         298],  // Cesacion del seguro
        299  => ['grupos_protegidos',         299],  // Designacion de beneficiarios
        300  => ['grupos_protegidos',         300],  // La empresa como aseguradora
        301  => ['grupos_protegidos',         301],  // Sustitucion de permisos anteriores
        302  => ['grupos_protegidos',         302],  // Seguros en compañias
        303  => ['grupos_protegidos',         303],  // Certificado
        304  => ['grupos_protegidos',         304],  // Pignoracion para vivienda
        305  => ['grupos_protegidos',         305],  // Muerte por accidente o enfermedad profesional
        306  => ['grupos_protegidos',         306],  // De la prima de servicios a favor de todo empleado
        307  => ['grupos_protegidos',         307],  // Caracter juridico
        308  => ['grupos_protegidos',         308],  // Primas convencionales y reglamentarias
        309  => ['grupos_protegidos',         309],  // Definiciones
        310  => ['grupos_protegidos',         310],  // Cesantia y vacaciones
        311  => ['grupos_protegidos',         311],  // Asistencia medica
        312  => ['grupos_protegidos',         312],  // Empresas constructoras
        313  => ['grupos_protegidos',         313],  // Suspension del trabajo por lluvia
        314  => ['grupos_protegidos',         314],  // Campo de aplicacion
        315  => ['grupos_protegidos',         315],  // Habitaciones y saneamiento
        316  => ['grupos_protegidos',         316],  // Alimentacion. costo de vida
        317  => ['grupos_protegidos',         317],  // Asistencia medica
        318  => ['grupos_protegidos',         318],  // Hospitales e higiene
        319  => ['grupos_protegidos',         319],  // Hospitalizacion
        320  => ['grupos_protegidos',         320],  // Enfermos no hospitalizados
        321  => ['grupos_protegidos',         321],  // Medidas profilacticas
        322  => ['grupos_protegidos',         322],  // Negativa al tratamiento
        323  => ['grupos_protegidos',         323],  // Enfermedades venereas
        324  => ['grupos_protegidos',         324],  // Comisiones de conciliacion y arbitraje
        325  => ['grupos_protegidos',         325],  // Centros mixtos de salud
        326  => ['grupos_protegidos',         326],  // Asistencia medica
        327  => ['grupos_protegidos',         327],  // Asistencia medica
        328  => ['grupos_protegidos',         328],  // Incapacidad
        329  => ['grupos_protegidos',         329],  // Definicion
        330  => ['grupos_protegidos',         330],  // Periodos de pago
        331  => ['grupos_protegidos',         331],  // Prevencion de enfermedades
        332  => ['grupos_protegidos',         332],  // Higiene
        333  => ['grupos_protegidos',         333],  // Actividades discontinuas, intermitentes y de simple vigilancia
        334  => ['grupos_protegidos',         334],  // Alojamiento y medicamentos
        335  => ['grupos_protegidos',         335],  // Enfermedades tropicales
        336  => ['grupos_protegidos',         336],  // Reglamentacion
        337  => ['grupos_protegidos',         337],  // Local para escuela
        338  => ['grupos_protegidos',         338],  // Prestaciones sociales
        339  => ['grupos_protegidos',         339],  // Cooperativas
        340  => ['grupos_protegidos',         340],  // Principio general y excepciones
        341  => ['grupos_protegidos',         341],  // Definicion y clasificacion de invalidez y enfermedad
        342  => ['grupos_protegidos',         342],  // Prestaciones renunciables
        343  => ['grupos_protegidos',         343],  // Prohibicion de cederlas
        344  => ['grupos_protegidos',         344],  // Principio y excepciones
        345  => ['grupos_protegidos',         345],  // Prelacion de creditos por salarios, prestaciones sociales e indemnizaciones laborales
        346  => ['grupos_protegidos',         346],  // Norma general
        347  => ['grupos_protegidos',         347],  // Causahabientes o beneficiarios
        348  => ['grupos_protegidos',         348],  // Medidas de higiene y seguridad
        349  => ['grupos_protegidos',         349],  // Reglamento de higiene y seguridad
        350  => ['grupos_protegidos',         350],  // Contenido del reglamento
        351  => ['grupos_protegidos',         351],  // Publicacion
        352  => ['grupos_protegidos',         352],  // Vigilancia y sanciones

        // ── DERECHOS DE ASOCIACIÓN Y SINDICACIÓN ──────────────────────────────
        353  => ['derechos_sindicales',        353],  // Derechos de asociacion
        354  => ['derechos_sindicales',        354],  // Proteccion del derecho de asociacion
        355  => ['derechos_sindicales',        355],  // Actividades lucrativas
        356  => ['derechos_sindicales',        356],  // Sindicatos de trabajadores
        357  => ['derechos_sindicales',        357],  // Sindicatos de base
        358  => ['derechos_sindicales',        358],  // Libertad de afiliacion
        359  => ['derechos_sindicales',        359],  // Numero minimo de afiliados
        360  => ['derechos_sindicales',        360],  // Afiliacion a varios sindicatos
        361  => ['derechos_sindicales',        361],  // Fundacion
        362  => ['derechos_sindicales',        362],  // Estatutos
        363  => ['derechos_sindicales',        363],  // Notificacion
        364  => ['derechos_sindicales',        364],  // Personeria juridica
        365  => ['derechos_sindicales',        365],  // Registro sindical
        366  => ['derechos_sindicales',        366],  // Tramitacion
        367  => ['derechos_sindicales',        367],  // Publicacion
        368  => ['derechos_sindicales',        368],  // Publicacion
        369  => ['derechos_sindicales',        369],  // Modificacion de los estatutos
        370  => ['derechos_sindicales',        370],  // Validez de la modificacion
        371  => ['derechos_sindicales',        371],  // Cambios en la junta directiva
        372  => ['derechos_sindicales',        372],  // Efecto juridico de la inscripcion
        373  => ['derechos_sindicales',        373],  // Funciones en general
        374  => ['derechos_sindicales',        374],  // Otras funciones
        375  => ['derechos_sindicales',        375],  // Atencion por parte de las autoridades y empleadores
        376  => ['derechos_sindicales',        376],  // Atribuciones exclusivas de la asamblea
        377  => ['derechos_sindicales',        377],  // Prueba del cumplimiento de disposiciones legales o estatutarias
        378  => ['derechos_sindicales',        378],  // Libertad de trabajo
        379  => ['derechos_sindicales',        379],  // Prohibiciones
        380  => ['derechos_sindicales',        380],  // Sanciones
        381  => ['derechos_sindicales',        381],  // Sanciones a los directores
        382  => ['derechos_sindicales',        382],  // Nombre social
        383  => ['derechos_sindicales',        383],  // Edad minima
        384  => ['derechos_sindicales',        384],  // Nacionalidad
        385  => ['derechos_sindicales',        385],  // Reuniones de la asamblea
        386  => ['derechos_sindicales',        386],  // Quorum de la asamblea
        387  => ['derechos_sindicales',        387],  // Representacion de los socios en la asamblea
        388  => ['derechos_sindicales',        388],  // Condiciones para los miembros de la junta directiva
        389  => ['derechos_sindicales',        389],  // Empleados directivos
        390  => ['derechos_sindicales',        390],  // Periodo de directivas
        391  => ['derechos_sindicales',        391],  // Eleccion de directivas
        392  => ['derechos_sindicales',        392],  // Constancia en el acta, votacion secreta
        393  => ['derechos_sindicales',        393],  // Libros
        394  => ['derechos_sindicales',        394],  // Presupuesto
        395  => ['derechos_sindicales',        395],  // Caucion del tesorero
        396  => ['derechos_sindicales',        396],  // Deposito de los fondos
        397  => ['derechos_sindicales',        397],  // Contabilidad
        398  => ['derechos_sindicales',        398],  // Expulsion de miembros
        399  => ['derechos_sindicales',        399],  // Separacion de miembros
        400  => ['derechos_sindicales',        400],  // Retencion de cuotas sindicales
        401  => ['derechos_sindicales',        401],  // Casos de disolucion
        402  => ['derechos_sindicales',        402],  // Liquidacion
        403  => ['derechos_sindicales',        403],  // Adjudicacion del remanente
        404  => ['derechos_sindicales',        404],  // Aprobacion oficial

        // ── FUERO SINDICAL ───────────────────────────────────────────────────
        405  => ['grupos_protegidos',         405],  // Definicion
        406  => ['grupos_protegidos',         406],  // Trabajadores amparados por el fuero sindical
        407  => ['grupos_protegidos',         407],  // Miembros de la junta directiva amparados
        408  => ['grupos_protegidos',         408],  // Contenido de la sentencia
        409  => ['grupos_protegidos',         409],  // Excepciones
        410  => ['grupos_protegidos',         410],  // Justas causas del despido
        411  => ['grupos_protegidos',         411],  // Terminacion del contrato sin previa calificacion judicial
        412  => ['grupos_protegidos',         412],  // Suspension del contrato de trabajo
        413  => ['grupos_protegidos',         413],  // Sanciones disciplinarias
        414  => ['grupos_protegidos',         414],  // Derecho de asociacion
        415  => ['grupos_protegidos',         415],  // Atencion por parte de las autoridades
        416  => ['grupos_protegidos',         416],  // Limitacion de las funciones
        '416-A' => ['grupos_protegidos',      4161],  // Las organizaciones sindicales de los servidores públicos tienen
        417  => ['grupos_protegidos',         417],  // Derecho de federacion
        418  => ['grupos_protegidos',         418],  // Funciones adicionales
        419  => ['grupos_protegidos',         419],  // Autorizacion a los fundadores
        420  => ['grupos_protegidos',         420],  // Acta de fundacion
        421  => ['grupos_protegidos',         421],  // Fuero sindical
        422  => ['grupos_protegidos',         422],  // Junta directiva
        423  => ['grupos_protegidos',         423],  // Registro sindical
        424  => ['grupos_protegidos',         424],  // Directiva provisional
        425  => ['grupos_protegidos',         425],  // Estatutos
        426  => ['grupos_protegidos',         426],  // Asesoria por asociaciones superiores
        427  => ['grupos_protegidos',         427],  // Informes para el ministerio
        428  => ['grupos_protegidos',         428],  // Congresos sindicales

        // ── DERECHO DE HUELGA ────────────────────────────────────────────────
        429  => ['huelga',                     429],  // Definicion de huelga
        430  => ['huelga',                     430],  // Prohibicion de huelga en los servicios publicos
        431  => ['huelga',                     431],  // Requisitos
        432  => ['huelga',                     432],  // Delegados
        433  => ['huelga',                     433],  // Iniciacion de conversaciones
        434  => ['huelga',                     434],  // Duracion de las conversaciones
        435  => ['huelga',                     435],  // Acuerdo
        436  => ['huelga',                     436],  // Desacuerdo
        437  => ['huelga',                     437],  // Mediacion
        438  => ['huelga',                     438],  // Artículo derogado
        439  => ['huelga',                     439],  // Artículo derogado
        440  => ['huelga',                     440],  // Artículo derogado
        441  => ['huelga',                     441],  // Artículo derogado
        442  => ['huelga',                     442],  // Artículo derogado
        443  => ['huelga',                     443],  // Copias
        444  => ['huelga',                     444],  // Decision de los trabajadores
        445  => ['huelga',                     445],  // Desarrollo de la huelga
        446  => ['huelga',                     446],  // Forma de la huelga
        447  => ['huelga',                     447],  // Comites de huelga
        448  => ['huelga',                     448],  // Funciones de las autoridades
        449  => ['huelga',                     449],  // Efectos juridicos de la huelga
        450  => ['huelga',                     450],  // Casos de ilegalidad y sanciones
        451  => ['huelga',                     451],  // Declaracion de ilegalidad
        452  => ['huelga',                     452],  // Procedencia del arbitramento
        453  => ['huelga',                     453],  // Tribunales especiales
        454  => ['huelga',                     454],  // Personas que no pueden se arbitros
        455  => ['huelga',                     455],  // Tribunales voluntarios
        456  => ['huelga',                     456],  // Quorum
        457  => ['huelga',                     457],  // Facultades del tribunal
        458  => ['huelga',                     458],  // Decision
        459  => ['huelga',                     459],  // Termino para fallar
        460  => ['huelga',                     460],  // Notificacion
        461  => ['huelga',                     461],  // Efecto juridico y vigencia de los fallos
        462  => ['huelga',                     462],  // Responsabilidad penal
        463  => ['huelga',                     463],  // Personas que no pueden intervenir
        464  => ['huelga',                     464],  // Empresas de servicios publicos
        465  => ['huelga',                     465],  // Intervencion del gobierno
        466  => ['huelga',                     466],  // Empresas que no son de servicio publico

        // ── CONVENCIONES COLECTIVAS ──────────────────────────────────────────
        467  => ['convenciones_colectivas',   467],  // Definicion
        468  => ['convenciones_colectivas',   468],  // Contenido
        469  => ['convenciones_colectivas',   469],  // Forma
        470  => ['convenciones_colectivas',   470],  // Campo de aplicación
        471  => ['convenciones_colectivas',   471],  // Extension a terceros
        472  => ['convenciones_colectivas',   472],  // Extension por acto gubernamental
        473  => ['convenciones_colectivas',   473],  // Separacion del empleador del sindicato patronal
        474  => ['convenciones_colectivas',   474],  // Disolucion del sindicato contratante
        475  => ['convenciones_colectivas',   475],  // Acciones de los sindicatos
        476  => ['convenciones_colectivas',   476],  // Acciones de los trabajadores
        477  => ['convenciones_colectivas',   477],  // Plazo presuntivo
        478  => ['convenciones_colectivas',   478],  // Prorroga automatica
        479  => ['convenciones_colectivas',   479],  // Denuncia
        480  => ['convenciones_colectivas',   480],  // Revision

        // ── PACTOS COLECTIVOS ────────────────────────────────────────────────
        481  => ['pactos_colectivos',         481],  // Celebracion y efectos
        482  => ['pactos_colectivos',         482],  // Definicion
        483  => ['pactos_colectivos',         483],  // Responsabilidad
        484  => ['pactos_colectivos',         484],  // Disolucion del sindicato

        // ── DISPOSICIONES FINALES ────────────────────────────────────────────
        485  => ['disposiciones_finales',     485],  // Autoridades que los ejercitan
        486  => ['disposiciones_finales',     486],  // Atribuciones y sanciones
        487  => ['disposiciones_finales',     487],  // Funcionarios de instruccion
        488  => ['disposiciones_finales',     488],  // Regla general
        489  => ['disposiciones_finales',     489],  // Interrupcion de la prescripcion
        490  => ['disposiciones_finales',     490],  // Fecha de vigencia
        491  => ['disposiciones_finales',     491],  // Disposiciones suspendidas
        492  => ['disposiciones_finales',     492],  // Disposiciones no suspendidas
    ];

    public function handle(): int
    {
        $this->info('Descargando artículos del CST desde leyes.co...');

        $apiKey = config('services.ia.gemini.api_key') ?? config('services.gemini.api_key');
        if (!$apiKey) {
            $this->error('No se encontró GEMINI_API_KEY en la configuración.');
            return self::FAILURE;
        }

        $force    = $this->option('force');
        $soloNum  = $this->option('solo') ? (int) $this->option('solo') : null;
        $lista    = $soloNum
            ? [$soloNum => $this->articulos[$soloNum] ?? ['general', $soloNum]]
            : $this->articulos;

        $ok = $skip = $errores = 0;

        foreach ($lista as $numero => $meta) {
            // Respetar exclusiones (solo si no se usa --solo)
            if (!$soloNum && in_array($numero, $this->excluidos)) {
                $this->line("  [excluido] Artículo. {$numero} — usar versión manual (más actualizada)");
                $skip++;
                continue;
            }

            [$categoria, $orden] = $meta;
            $codigo = "Artículo. {$numero} CST";

            // Verificar si ya existe con embedding (skip si no --force)
            $existente = ArticuloLegal::where('codigo', $codigo)
                ->where('fuente', self::FUENTE)
                ->whereNull('empresa_id')
                ->first();

            if ($existente && !$force && $existente->embedding) {
                $this->line("  [skip] {$codigo}");
                $skip++;
                continue;
            }

            $resultado = $this->scrapearArticulo($numero);

            if (!$resultado) {
                $this->warn("  [error] {$codigo} — no se pudo obtener de leyes.co");
                Log::warning("cst:scraper — sin resultado para Artículo. {$numero}");
                $errores++;
                continue;
            }

            $embedding = $this->generarEmbedding($resultado['texto'], $apiKey);

            ArticuloLegal::updateOrCreate(
                [
                    'codigo'     => $codigo,
                    'fuente'     => self::FUENTE,
                    'empresa_id' => null,
                ],
                [
                    'titulo'         => $resultado['titulo'],
                    'descripcion'    => $resultado['texto'],
                    'texto_completo' => $resultado['texto'],
                    'categoria'      => $categoria,
                    'orden'          => $orden,
                    'activo'         => true,
                    'embedding'      => $embedding,
                ]
            );

            $estado = $embedding ? '[ok]' : '[guardado sin embedding]';
            $this->info("  {$estado} {$codigo} — {$resultado['titulo']}");
            $ok++;

            usleep(400_000); // 400 ms entre requests
        }

        $this->info("Listo. {$ok} importados, {$skip} omitidos, {$errores} errores.");
        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scraping y parsing
    // ──────────────────────────────────────────────────────────────────────────

    private function scrapearArticulo(int|string $numero): ?array
    {
        $urlSegmento = $this->urlOverrides[(string) $numero] ?? (string) $numero;
        $url = self::BASE_URL . "{$urlSegmento}.htm";

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; CES-Legal/1.0; +https://ceslegal.co)',
                    'Accept'     => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'es-CO,es;q=0.9',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning("cst:scraper HTTP {$response->status()} Artículo. {$numero}");
                return null;
            }

            return $this->parsearHtml($response->body(), $numero);
        } catch (\Exception $e) {
            Log::error("cst:scraper excepción Artículo. {$numero}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function parsearHtml(string $html, int|string $numero): ?array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        $xpath = new \DOMXPath($dom);

        // El contenido del artículo está en div#statya (estructura de leyes.co)
        $statya = $xpath->query('//div[@id="statya"]');
        if (!$statya || $statya->length === 0) {
            Log::warning("cst:scraper — div#statya no encontrado Artículo. {$numero}");
            return null;
        }

        $nodo = $statya->item(0);

        // Extraer y limpiar el título desde el h1
        $h1s   = $xpath->query('.//h1', $nodo);
        $titulo = '';
        if ($h1s->length > 0) {
            $h1Text = trim(preg_replace('/\s+/', ' ', $h1s->item(0)->textContent));
            // Quitar "Código Sustantivo del Trabajo" y "Artículo X."
            $titulo = preg_replace('/^Código\s+Sustantivo\s+del\s+Trabajo\s*/ui', '', $h1Text);
            $titulo = preg_replace('/^Art[ií]culo\s+\d+[\.\-\s]*/ui', '', $titulo);
            $titulo = trim($titulo);
        }

        // Extraer texto completo del div#statya
        $texto = $this->extraerTextoNodo($nodo);

        // Eliminar pie de página que leyes.co agrega al final
        $texto = preg_replace(
            '/Colombia\s+Art\.?\s+' . $numero . '\.?\s+Código\s+Sustantivo\s+del\s+Trabajo[^\n]*/ui',
            '',
            $texto
        );

        // Normalizar espacios y saltos
        $texto = preg_replace('/[ \t]+/', ' ', $texto);
        $texto = preg_replace('/\n[ \t]+/', "\n", $texto);
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);
        $texto = html_entity_decode(trim($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (mb_strlen($texto) < 15) {
            Log::warning("cst:scraper — texto demasiado corto Artículo. {$numero}");
            return null;
        }

        return [
            'titulo' => $titulo ?: "Artículo {$numero}",
            'texto'  => $texto,
        ];
    }

    /**
     * Extrae el texto de un nodo DOM preservando estructura básica (saltos de línea).
     */
    private function extraerTextoNodo(\DOMNode $nodo): string
    {
        $partes = [];

        foreach ($nodo->childNodes as $hijo) {
            if ($hijo instanceof \DOMText) {
                $t = trim($hijo->textContent);
                if ($t !== '') {
                    $partes[] = $t;
                }
                continue;
            }

            if (!($hijo instanceof \DOMElement)) {
                continue;
            }

            $tag = strtolower($hijo->tagName);

            // Ignorar elementos no relevantes
            if (in_array($tag, ['script', 'style', 'nav', 'footer', 'iframe', 'noscript'])) {
                continue;
            }

            // El h1 contiene el título — lo formateamos especial
            if ($tag === 'h1') {
                $t = trim(preg_replace('/\s+/', ' ', $hijo->textContent));
                if ($t !== '') {
                    $partes[] = 'ARTICULO ' . $t;
                }
                continue;
            }

            // Elementos de bloque: agregar como párrafo
            if (in_array($tag, ['p', 'div', 'li', 'h2', 'h3', 'h4', 'h5', 'blockquote'])) {
                $t = trim(preg_replace('/\s+/', ' ', $hijo->textContent));
                if ($t !== '') {
                    $partes[] = $t;
                }
                continue;
            }

            // br → salto de línea
            if ($tag === 'br') {
                $partes[] = '';
                continue;
            }

            // Cualquier otro elemento inline
            $t = trim(preg_replace('/\s+/', ' ', $hijo->textContent));
            if ($t !== '') {
                $partes[] = $t;
            }
        }

        return implode("\n", $partes);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Embedding Gemini
    // ──────────────────────────────────────────────────────────────────────────

    private function generarEmbedding(string $texto, string $apiKey): ?array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}";

        try {
            $response = Http::timeout(15)->post($url, [
                'content'  => ['parts' => [['text' => mb_substr($texto, 0, 8000)]]],
                'taskType' => 'RETRIEVAL_DOCUMENT',
            ]);

            if (!$response->successful()) {
                Log::warning('cst:scraper — embedding fallido', ['status' => $response->status()]);
                return null;
            }

            $values = $response->json('embedding.values');
            return is_array($values) && !empty($values) ? $values : null;
        } catch (\Exception $e) {
            Log::error('cst:scraper — excepción embedding', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
