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
        ['n' => '1', 'sc' => '#60a5fa', 'ib' => 'rgba(96,165,250,.12)', 'ibc' => 'rgba(96,165,250,.25)', 'tag' => 'Paso 1', 'title' => 'Datos del trabajador', 'body' => 'Identifique al trabajador y su cargo. El empleado presentará sus descargos en línea durante la audiencia y sus respuestas quedarán registradas.'],
        ['n' => '2', 'sc' => '#34d399', 'ib' => 'rgba(52,211,153,.12)', 'ibc' => 'rgba(52,211,153,.25)', 'tag' => 'Paso 2', 'title' => 'Cuándo y dónde', 'body' => 'Confirme la fecha, hora aproximada, lugar del hecho y si ocurrió dentro del horario laboral.'],
        ['n' => '3', 'sc' => '#c9a84c', 'ib' => 'rgba(201,168,76,.12)', 'ibc' => 'rgba(201,168,76,.25)', 'tag' => 'Paso 3', 'title' => 'Hechos reportados', 'body' => '¿Quién reporta el incidente? Describa lo ocurrido — la IA verifica que no falte alguna acción concreta.'],
        ['n' => '4', 'sc' => '#a78bfa', 'ib' => 'rgba(167,139,250,.12)', 'ibc' => 'rgba(167,139,250,.25)', 'tag' => 'Paso 4', 'title' => 'Evidencias', 'body' => '¿Existe evidencia? Correos, registros de asistencia, cámaras, documentos, testigos... suba los archivos disponibles.'],
        ['n' => '5', 'sc' => '#fb923c', 'ib' => 'rgba(251,146,60,.12)', 'ibc' => 'rgba(251,146,60,.25)', 'tag' => 'Paso 5', 'title' => 'Testigos', 'body' => '¿Hubo personas que presenciaron el hecho? Registre el nombre y cargo de cada testigo.'],
        ['n' => '6', 'sc' => '#f472b6', 'ib' => 'rgba(244,114,182,.12)', 'ibc' => 'rgba(244,114,182,.25)', 'tag' => 'Paso 6', 'title' => 'Revisión y envío', 'body' => 'Revise el resumen completo, previsualice la citación y confirme el envío de los descargos.'],
    ];
@endphp

@include('filament.components.partials.bienvenida-hero', [
    'eyebrow'   => 'Proceso Disciplinario Laboral',
    'title'     => 'Asistente de Gestión Jurídica',
    'subtitle'  => $subtitle,
    'ruleLabel' => 'El proceso completo — 6 pasos',
    'steps'     => $steps,
    'nextHint'  => $nextHint,
])
