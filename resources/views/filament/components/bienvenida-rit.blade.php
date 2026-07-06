@php
    use Illuminate\Support\HtmlString;

    $nombre = auth()->user()?->name ?? 'usuario';

    $subtitle = new HtmlString(
        'Bienvenido/a, <strong class="t-gold" style="font-weight:500;">' . e($nombre) . '</strong>. '
        . 'Construiremos su Reglamento Interno paso a paso, anclado en el '
        . '<span class="t-gold" style="font-weight:500;">Código Sustantivo del Trabajo</span> y la normativa vigente.'
    );

    $nextHint = new HtmlString(
        'Al hacer clic en <strong>Siguiente,</strong> comenzará el <strong>Paso 1</strong>. '
        . 'Sus respuestas se guardan a medida que avanza, así que puede continuar después si lo necesita.'
    );

    $steps = [
        ['n' => '1', 'sc' => '#60a5fa', 'ib' => 'rgba(96,165,250,.12)', 'ibc' => 'rgba(96,165,250,.25)', 'tag' => 'Paso 1', 'title' => 'Empresa', 'body' => 'Confirme los datos de su empresa y su actividad económica (CIIU). Esto define los riesgos laborales que el Reglamento debe cubrir.'],
        ['n' => '2', 'sc' => '#34d399', 'ib' => 'rgba(52,211,153,.12)', 'ibc' => 'rgba(52,211,153,.25)', 'tag' => 'Paso 2', 'title' => 'Estructura', 'body' => 'Indique los cargos de su empresa, los tipos de contrato que usa y si existe sindicato, convención o pacto colectivo.'],
        ['n' => '3', 'sc' => '#c9a84c', 'ib' => 'rgba(201,168,76,.12)', 'ibc' => 'rgba(201,168,76,.25)', 'tag' => 'Paso 3', 'title' => 'Jornada', 'body' => 'Defina los horarios, turnos, trabajo en domingos o festivos y el control de asistencia y horas extras.'],
        ['n' => '4', 'sc' => '#fb7185', 'ib' => 'rgba(251,113,133,.12)', 'ibc' => 'rgba(251,113,133,.25)', 'tag' => 'Paso 4', 'title' => 'Salario', 'body' => 'Especifique la forma y periodicidad de pago, comisiones, beneficios y la política de permisos e incapacidades.'],
        ['n' => '5', 'sc' => '#fb923c', 'ib' => 'rgba(251,146,60,.12)', 'ibc' => 'rgba(251,146,60,.25)', 'tag' => 'Paso 5', 'title' => 'Disciplina', 'body' => 'Revise el catálogo de conductas sancionables y ajuste el tipo de falta y la sanción de cada una conforme a la ley.'],
        ['n' => '6', 'sc' => '#f472b6', 'ib' => 'rgba(244,114,182,.12)', 'ibc' => 'rgba(244,114,182,.25)', 'tag' => 'Paso 6', 'title' => 'SST y Conducta', 'body' => 'Registre su Sistema de Gestión de SST, los riesgos principales y las normas de convivencia y uso de recursos.'],
        ['n' => '7', 'sc' => '#22d3ee', 'ib' => 'rgba(34,211,238,.12)', 'ibc' => 'rgba(34,211,238,.25)', 'tag' => 'Paso 7', 'title' => 'Revisión y generación', 'body' => 'Revise el resumen completo. La IA redactará su Reglamento capítulo por capítulo, anclado en la normativa vigente.'],
    ];
@endphp

@include('filament.components.partials.bienvenida-hero', [
    'eyebrow'   => 'Reglamento Interno de Trabajo',
    'title'     => 'Asistente de Construcción del RIT',
    'subtitle'  => $subtitle,
    'ruleLabel' => 'El proceso completo — 7 pasos',
    'steps'     => $steps,
    'nextHint'  => $nextHint,
])
