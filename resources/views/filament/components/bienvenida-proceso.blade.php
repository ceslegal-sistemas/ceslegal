@php
    use Illuminate\Support\HtmlString;

    $nombre = auth()->user()?->name ?? 'usuario';

    $subtitle = new HtmlString(
        'Bienvenido/a, <strong class="t-gold" style="font-weight:500;">' . e($nombre) . '</strong>. '
        . 'Le guiaremos paso a paso para registrar el proceso conforme al '
        . '<span class="t-gold" style="font-weight:500;">Código Sustantivo del Trabajo</span>.'
    );

    $nextHint = new HtmlString(
        'Al hacer clic en <strong>Siguiente,</strong> comenzará el <strong>Paso 1</strong>. '
        . 'El proceso completo toma aproximadamente 10 minutos.'
    );

    $steps = [
        ['n' => '1', 'sc' => '#e11d48', 'ib' => 'rgba(225,29,72,.12)', 'ibc' => 'rgba(225,29,72,.25)', 'tag' => 'Paso 1', 'title' => 'Quién y cuándo', 'body' => 'Identifique al trabajador involucrado y confirme la fecha y hora aproximada del hecho.'],
        ['n' => '2', 'sc' => '#f97316', 'ib' => 'rgba(249,115,22,.12)', 'ibc' => 'rgba(249,115,22,.25)', 'tag' => 'Paso 2', 'title' => 'Qué pasó', 'body' => 'Describa los hechos, adjunte evidencias y registre testigos - la IA verifica que no falte alguna acción concreta.'],
        ['n' => '3', 'sc' => '#22c55e', 'ib' => 'rgba(34,197,94,.12)', 'ibc' => 'rgba(34,197,94,.25)', 'tag' => 'Paso 3', 'title' => 'Revisión y envío', 'body' => 'Revise el resumen completo, autorice con su verificación de identidad y programe la audiencia de descargos.'],
    ];
@endphp

@include('filament.components.partials.bienvenida-hero', [
    'eyebrow'   => 'Proceso Disciplinario Laboral',
    'title'     => 'Asistente de Gestión Jurídica',
    'subtitle'  => $subtitle,
    'ruleLabel' => 'El proceso completo - 3 pasos',
    'steps'     => $steps,
    'nextHint'  => $nextHint,
])
