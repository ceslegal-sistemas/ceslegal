@php
    use Illuminate\Support\HtmlString;

    $nombre = auth()->user()?->name ?? 'usuario';

    $subtitle = new HtmlString(
        'Bienvenido/a, <strong class="t-gold" style="font-weight:500;">' . e($nombre) . '</strong>. '
        . 'Le guiaremos paso a paso para generar el contrato conforme al '
        . '<span class="t-gold" style="font-weight:500;">Código Sustantivo del Trabajo</span>.'
    );

    $nextHint = new HtmlString(
        'Al hacer clic en <strong>Siguiente,</strong> comenzará el <strong>Paso 1</strong>. '
        . 'El proceso completo toma aproximadamente 5 minutos.'
    );

    $steps = [
        ['n' => '1', 'sc' => '#e11d48', 'ib' => 'rgba(225,29,72,.12)', 'ibc' => 'rgba(225,29,72,.25)', 'tag' => 'Paso 1', 'title' => 'Información Básica', 'body' => 'Seleccione la empresa, el tipo de contrato y la fecha de la solicitud.'],
        ['n' => '2', 'sc' => '#f97316', 'ib' => 'rgba(249,115,22,.12)', 'ibc' => 'rgba(249,115,22,.25)', 'tag' => 'Paso 2', 'title' => 'Datos del Trabajador', 'body' => 'Elija un trabajador ya registrado o ingrese sus datos si es nuevo.'],
        ['n' => '3', 'sc' => '#0ea5e9', 'ib' => 'rgba(14,165,233,.12)', 'ibc' => 'rgba(14,165,233,.25)', 'tag' => 'Paso 3', 'title' => 'Detalles del Cargo', 'body' => 'Cargo, responsabilidades y condiciones - use "Completar con IA" para agilizar.'],
        ['n' => '4', 'sc' => '#22c55e', 'ib' => 'rgba(34,197,94,.12)', 'ibc' => 'rgba(34,197,94,.25)', 'tag' => 'Paso 4', 'title' => 'Documentos', 'body' => 'Adjunte los archivos de soporte y finalice para generar el contrato con IA.'],
    ];
@endphp

@include('filament.components.partials.bienvenida-hero', [
    'eyebrow'   => 'Solicitud de Contrato Laboral',
    'title'     => 'Asistente de Generación de Contratos',
    'subtitle'  => $subtitle,
    'ruleLabel' => 'El proceso completo - 4 pasos',
    'steps'     => $steps,
    'nextHint'  => $nextHint,
])
