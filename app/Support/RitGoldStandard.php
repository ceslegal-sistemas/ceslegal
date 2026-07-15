<?php

namespace App\Support;

/**
 * Estándar de oro del RIT: lista curada de ELEMENTOS DE CONTENIDO que un Reglamento Interno
 * de Trabajo de primer nivel debe cubrir en cada capítulo canónico.
 *
 * Es conocimiento experto codificado UNA vez (no lo inventa la IA) → seguro frente a la regla
 * anti-alucinación: son TEMAS que deben estar presentes, no citas legales. Lo consumen:
 *  - Auditoría (detecta elementos faltantes como hallazgos).
 *  - Generación y Mejora del RIT (asegura que ningún elemento quede por fuera).
 *
 * Para actualizarlo basta editar CHECKLIST (revisado por un abogado). No cita artículos ni
 * cifras: eso sigue viniendo exclusivamente de la base normativa (articulos_legales / RAG).
 */
class RitGoldStandard
{
    /** Elementos de contenido obligatorios por capítulo canónico (I..XVI). */
    private const CHECKLIST = [
        'I' => [
            'Identificación de la empresa (razón social, NIT, domicilio)',
            'Ámbito de aplicación: a quiénes y en qué lugares aplica',
            'Objeto y carácter obligatorio del reglamento',
            'Incorporación del reglamento a los contratos de trabajo',
        ],
        'II' => [
            'Requisitos y documentos de admisión',
            'No discriminación en la selección de personal',
            'Duración del período de prueba y su estipulación por escrito',
            'Efectos del período de prueba (terminación sin indemnización)',
            'Prohibición de exigir documentos no permitidos (p. ej. prueba de embarazo)',
        ],
        'III' => [
            'Duración máxima diaria y semanal de la jornada y su reducción gradual vigente',
            'Distribución de la jornada y días de descanso',
            'Franjas horarias de jornada diurna y nocturna',
            'Mecanismo de control y registro de asistencia',
            'Jornadas especiales o flexibles cuando apliquen',
        ],
        'IV' => [
            'Definición y autorización previa del trabajo suplementario',
            'Límite de horas extra permitidas',
            'Recargos por trabajo nocturno, suplementario, dominical y festivo',
            'Derecho a descanso compensatorio',
            'Forma de liquidación y pago de los recargos',
        ],
        'V' => [
            'Forma, lugar, período y fechas de pago del salario',
            'Salario en dinero y en especie',
            'Prohibición de deducciones no autorizadas',
            'Entrega de constancia o desprendible de pago',
            'Tratamiento de comisiones, viáticos y auxilios cuando apliquen',
        ],
        'VI' => [
            'Duración de las vacaciones y forma de disfrute',
            'Época de concesión y aviso previo',
            'Acumulación y compensación en dinero con sus límites',
            'Registro de vacaciones',
            'Permisos remunerados (votación, sindicales, estudio, etc.)',
        ],
        'VII' => [
            'Licencia de maternidad y de paternidad',
            'Licencia por luto',
            'Licencia por calamidad doméstica',
            'Licencias no remuneradas',
            'Requisitos y soportes para su otorgamiento',
        ],
        'VIII' => [
            'Definición de faltas leves, graves y muy graves',
            'Enumeración concreta de conductas por cada nivel de gravedad',
            'Criterios de graduación: atenuantes, agravantes y reincidencia',
            'Relación clara entre cada falta y su sanción',
        ],
        'IX' => [
            'Tipos de sanción (llamado de atención, suspensión, terminación)',
            'Límites legales de cada sanción',
            'Prohibición de penas corporales y de doble sanción por el mismo hecho',
            'Debido proceso previo a sancionar (citación, descargos, defensa)',
            'Autoridad competente para imponer la sanción y para resolver la impugnación',
        ],
        'X' => [
            'Instancia o persona ante quien se presentan reclamos y quejas',
            'Trámite y plazos de respuesta',
            'Derecho de impugnación ante el superior y ante el Inspector del Trabajo',
            'Conducto regular a seguir',
        ],
        'XI' => [
            'Obligaciones especiales del trabajador y del empleador',
            'Prohibiciones especiales',
            'Orden jerárquico y representantes del empleador',
            'Manejo confidencial y adecuado de la información de la empresa',
        ],
        'XII' => [
            'Compromiso de la empresa con el Sistema de Gestión de SST',
            'COPASST o Vigía de Seguridad y Salud en el Trabajo',
            'Entrega y uso de elementos de protección personal (EPP)',
            'Reporte e investigación de accidentes e incidentes',
            'Exámenes médicos de ingreso, periódicos y de retiro',
            'Política de prevención de alcohol y sustancias psicoactivas',
        ],
        'XIII' => [
            'Entrega y cuidado de dotación y uniformes',
            'Uso y custodia de equipos y herramientas de trabajo',
            'Responsabilidad por pérdida o daño de bienes de la empresa',
            'Devolución de elementos al terminar el contrato',
        ],
        'XIV' => [
            'Conformación y funciones del Comité de Convivencia Laboral',
            'Definición y modalidades del acoso laboral',
            'Prevención y atención del acoso sexual',
            'Procedimiento confidencial de queja y su trámite',
            'Medidas de protección para quien denuncia',
        ],
        'XV' => [
            'Estabilidad laboral reforzada (maternidad, discapacidad, fuero)',
            'Garantías del fuero de maternidad y paternidad',
            'Protección y ajustes razonables para personas con discapacidad',
            'Labores prohibidas o restringidas (embarazadas, menores de edad)',
            'No discriminación de grupos de especial protección',
        ],
        'XVI' => [
            'Vigencia y fecha de entrada en vigor del reglamento',
            'Publicación y divulgación a los trabajadores',
            'Mecanismo para modificar el reglamento',
            'Ineficacia de las cláusulas contrarias a la ley',
        ],
    ];

    /**
     * Mapa sección de auditoría → capítulos canónicos (espejo de
     * RITMejoradoService::CAPITULO_A_SECCION, invertido).
     */
    private const SECCION_A_CAPITULOS = [
        'admision'          => ['II'],
        'jornada'           => ['III', 'IV'],
        'descansos'         => ['VI', 'VII'],
        'salario'           => ['V'],
        'disciplina'        => ['VIII', 'IX', 'X'],
        'sst'               => ['XII'],
        'acoso'             => ['XIV'],
        'grupos_protegidos' => ['XV'],
    ];

    /** Elementos obligatorios de un capítulo canónico (I..XVI). */
    public static function paraCapitulo(string $numero): array
    {
        return self::CHECKLIST[strtoupper($numero)] ?? [];
    }

    /** Elementos obligatorios de una sección de auditoría (une los capítulos que la componen). */
    public static function paraSeccion(string $seccion): array
    {
        $items = [];
        foreach (self::SECCION_A_CAPITULOS[$seccion] ?? [] as $cap) {
            $items = array_merge($items, self::CHECKLIST[$cap] ?? []);
        }
        return array_values(array_unique($items));
    }

    /** Formatea una lista de elementos como viñetas numeradas para inyectar en un prompt. */
    public static function comoLista(array $items): string
    {
        $out = [];
        foreach (array_values($items) as $i => $item) {
            $out[] = ($i + 1) . ') ' . $item;
        }
        return implode("\n", $out);
    }
}
