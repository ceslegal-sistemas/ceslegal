<?php

namespace App\Services;

use App\Models\ProcesoDisciplinario;
use App\Models\EmailTracking;
use App\Services\TimelineService;
use App\Services\EstadoProcesoService;
use App\Services\ReglamentoInternoService;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DocumentGeneratorService
{
    /**
     * Generar citación a descargos desde la plantilla
     *
     * @param ProcesoDisciplinario $proceso
     * @return string Ruta del PDF generado
     */

    private string $libreOfficePath;

    public function __construct()
    {
        $this->libreOfficePath = $this->detectLibreOfficePath();
    }

    private function detectLibreOfficePath(): string
    {
        // Linux
        if (PHP_OS_FAMILY === 'Linux') {
            foreach (['/usr/bin/soffice', '/usr/local/bin/soffice', '/snap/bin/soffice'] as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
            return 'soffice';
        }

        // Windows
        foreach ([
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return 'C:\\Program Files\\LibreOffice\\program\\soffice.exe';
    }

    public function generarCitacionDescargos(ProcesoDisciplinario $proceso): string
    {
        // Generar el HTML del documento
        $html = $this->generarHTMLCitacionDescargos($proceso);

        // Convertir HTML a PDF con Dompdf
        $pdfPath = $this->convertirCitacionHTMLaPDF($html, $proceso->codigo);

        return $pdfPath;
    }

    /**
     * Genera el HTML del documento de citación a descargos.
     * Estructura: Comunicación Formal de Apertura de Investigación Disciplinaria
     * conforme al Artículo 7 de la Ley 2466 de 2025.
     */
    private function generarHTMLCitacionDescargos(ProcesoDisciplinario $proceso): string
    {
        $trabajador  = $proceso->trabajador;
        $empresa     = $proceso->empresa;
        $fechaActual = Carbon::now()->locale('es');

        // ── Fecha de elaboración ─────────────────────────────────────────────
        $dia = $fechaActual->format('d');
        $mes = $fechaActual->isoFormat('MMMM');
        $anio = $fechaActual->year;

        // ── Datos de la diligencia ───────────────────────────────────────────
        $fechaDescargos = $proceso->fecha_descargos_programada
            ? Carbon::parse($proceso->fecha_descargos_programada)->locale('es')
            : null;

        $horaDescargos = null;
        if ($proceso->hora_descargos_programada) {
            try {
                $horaDescargos = Carbon::createFromFormat('H:i:s', $proceso->hora_descargos_programada)->format('h:i A');
            } catch (\Exception $e) {
                try {
                    $horaDescargos = Carbon::parse($proceso->hora_descargos_programada)->format('h:i A');
                } catch (\Exception $e2) {
                    $horaDescargos = $proceso->hora_descargos_programada;
                }
            }
        }

        $diaDescargos  = $fechaDescargos ? $fechaDescargos->format('d') : '___';
        $mesDescargos  = $fechaDescargos ? $fechaDescargos->isoFormat('MMMM') : '_______________';
        $anioDescargos = $fechaDescargos ? $fechaDescargos->year : '20__';
        $horaTexto     = $horaDescargos ?? '___:___';

        $modalidad = strtolower($proceso->modalidad_descargos ?? 'presencial');

        // ── Lugar de la diligencia ───────────────────────────────────────────
        if ($modalidad === 'presencial') {
            $lugarDiligencia = trim(implode(', ', array_filter([
                $empresa->direccion ?? null,
                $empresa->ciudad ?? null,
                $empresa->departamento ?? null,
            ])));
            $lugarDiligencia = $lugarDiligencia ?: 'instalaciones de la empresa';
        } else {
            $lugarDiligencia = 'diligencia virtual - se remitirá el enlace de acceso al correo registrado';
        }

        // ── Datos del trabajador ─────────────────────────────────────────────
        $nombreTrabajador = e($trabajador->nombre_completo ?? '');
        $numDocTrabajador = e($trabajador->numero_documento ?? '');
        $tipoDoc          = e($trabajador->tipo_documento ?? 'C.C.');
        $cargoTrabajador  = e($trabajador->cargo ?? 'cargo en la empresa');
        $dirTrabajador    = e($trabajador->direccion ?? '');
        $ciudadTrabajador = e(trim(implode(', ', array_filter([
            $empresa->ciudad ?? null,
            $empresa->departamento ?? null,
        ]))));

        // ── Datos de la empresa ──────────────────────────────────────────────
        $nombreEmpresa     = e($empresa->nombre_completo ?? '');
        $nit               = e($empresa->nit ?? '');
        $representante     = e($empresa->representante_legal ?? 'Representante Legal');
        $emailContacto     = e($empresa->email_contacto ?? $empresa->email ?? '');
        $telefonoEmpresa   = e($empresa->telefono ?? '');

        // Bug real reportado: una empresa SIN RIT subido (ej. CES LEGAL S.A.S.,
        // proceso PD-2026-0119) recibía una citación que citaba textualmente
        // "el Reglamento Interno de Trabajo de {empresa}" con una conducta y una
        // tabla de sanciones - ambas en realidad venían del catálogo genérico de
        // respaldo del CST (ReglamentoInternoService::conductasCstBase()), nunca
        // de un reglamento real de esa empresa. Se usa este flag para no
        // fabricar una cita a un documento que no existe.
        $tieneRIT = $empresa->reglamentoInterno !== null;

        // ── Hechos ───────────────────────────────────────────────────────────
        $hechosTexto = html_entity_decode(strip_tags($proceso->hechos ?? ''), ENT_QUOTES, 'UTF-8');
        // Los hechos se presentan como una narración en prosa (un <p> por párrafo
        // real, separado por saltos de línea), no como una lista numerada. El
        // texto real que escribe el abogado/RRHH al crear el proceso YA suele
        // venir como varios párrafos de un relato continuo (ver caso real
        // PD-2026-0008: 3 párrafos con fechas, nombres de cargos y hechos
        // concretos) - convertirlo en <ol><li> fragmentaba esa narración en
        // "puntos" sueltos, dando la impresión de un relato pobre aunque el
        // contenido ya fuera detallado. El contenido sigue siendo LITERAL
        // (nunca se reescribe, resume ni completa con IA): solo cambia cómo
        // se presenta, igual que en la citación de referencia del bufete.
        $parrafosHechos = array_values(array_filter(
            array_map('trim', explode("\n", $hechosTexto))
        ));
        if (empty($parrafosHechos)) {
            $conductasHTML = '<p><em>No se registraron hechos para este proceso.</em></p>';
        } else {
            $conductasHTML = implode('', array_map(fn($p) => '<p>' . e($p) . '</p>', $parrafosHechos));
        }

        // ── Normas incumplidas ───────────────────────────────────────────────
        // Fuente 1 (manual): normas_incumplidas del proceso, si fue diligenciado a mano.
        // Fuente 2 (preferente si no hay lo anterior): la MISMA conducta del RIT ya
        // clasificada para este incidente concreto -
        // motivosDescargosNormalizados() (selección manual en "Motivo de los
        // descargos") o, en su defecto, motivosDescargosDesdeClasificacionIA()
        // (clasificación de IA hecha al crear el proceso, ver
        // ProcesoDisciplinarioResource) - el mismo par de métodos que ya
        // alimenta la tabla de sanciones del documento (filasTablaSancionesConFallback
        // más abajo). Antes la citación SIEMPRE mostraba el genérico "Art. 58
        // CST" aunque el proceso ya tuviera una conducta real identificada
        // (caso confirmado: PD-2026-0008, con sanciones_laborales_ids y
        // clasificacion_incidente_ia ya poblados, la citación seguía sin
        // citarlos). No se agrega ninguna llamada nueva a IA: solo se reutiliza
        // información que el proceso ya tiene calculada.
        // Fuente 3 (respaldo): referencia genérica sin inventar artículos.
        $normasTexto = trim($proceso->normas_incumplidas ?? '');
        $normasHTML = null;
        if ($normasTexto) {
            $normasLineas = array_values(array_filter(array_map('trim', explode("\n", $normasTexto))));
            $normasItems  = array_map(fn($l) => '<li>' . e($l) . '</li>', $normasLineas);
            $normasHTML   = '<ul>' . implode('', $normasItems) . '</ul>';
        } else {
            // La citación se genera al ABRIR el proceso, mucho antes de que
            // alguien pase por el análisis de la sanción - a diferencia de
            // generarDocumentoSancion() (que ya llama a este mismo método,
            // línea ~972), este punto nunca disparaba la clasificación
            // automática, así que un proceso recién creado sin selección
            // manual SIEMPRE caía al genérico, sin importar que la IA
            // pudiera identificar la conducta real. Método idempotente/
            // fail-open: no hace nada si ya hay una conducta, y no revienta
            // si la IA falla (ver docblock de asegurarClasificacionIncidente()).
            $proceso->asegurarClasificacionIncidente();

            $motivosIncidente = $proceso->motivosDescargosNormalizados();
            if (empty($motivosIncidente)) {
                $motivosIncidente = $proceso->motivosDescargosDesdeClasificacionIA();
            }

            if (!empty($motivosIncidente)) {
                $etiquetasGravedad = ['leve' => 'falta leve', 'grave' => 'falta grave', 'muy_grave' => 'falta muy grave'];
                $itemsNormas = [];
                foreach ($motivosIncidente as $motivo) {
                    $nombreConducta = trim((string) ($motivo['nombre'] ?? ''));
                    if ($nombreConducta === '') {
                        continue;
                    }
                    $etiqueta = $etiquetasGravedad[$motivo['gravedad'] ?? ''] ?? 'falta disciplinaria';
                    $baseLegal = trim((string) ($motivo['base_legal'] ?? ''));

                    // Si la empresa no tiene RIT, esta conducta viene SIEMPRE del
                    // catálogo genérico de respaldo del CST
                    // (ReglamentoInternoService::conductasCstBase()), nunca de un
                    // reglamento real - citarla como "Reglamento Interno de
                    // Trabajo de {empresa}" sería fabricar un documento que no
                    // existe (bug real: PD-2026-0119, CES LEGAL S.A.S. sin RIT).
                    if ($tieneRIT) {
                        // El número de artículo (ej. "Artículo 76 RIT") se extrae
                        // de forma determinística al procesar el RIT - ver
                        // ReglamentoInternoService::articuloQuePrecedeEnTexto().
                        // Solo se cita cuando es un número real detectado en el
                        // propio texto del RIT, nunca inventado (fuentes sin
                        // número real, como el constructor de RIT, devuelven un
                        // base_legal genérico sin dígitos).
                        $articulo = $this->formatearCitaArticulo($baseLegal, 'RIT');
                        $itemsNormas[] = $articulo
                            ? '<li><strong>' . e($articulo) . '</strong> del Reglamento Interno de Trabajo de <strong>' . $nombreEmpresa . '</strong>: &laquo;' . e($nombreConducta) . '&raquo; (calificada como ' . $etiqueta . ').</li>'
                            : '<li>Reglamento Interno de Trabajo de <strong>' . $nombreEmpresa . '</strong>: &laquo;' . e($nombreConducta) . '&raquo; (calificada como ' . $etiqueta . ').</li>';
                    } else {
                        $articulo = $this->formatearCitaArticulo($baseLegal, 'CST');
                        $itemsNormas[] = $articulo
                            ? '<li><strong>' . e($articulo) . '</strong> del Código Sustantivo del Trabajo: &laquo;' . e($nombreConducta) . '&raquo; (calificada como ' . $etiqueta . ').</li>'
                            : '<li>el Código Sustantivo del Trabajo: &laquo;' . e($nombreConducta) . '&raquo; (calificada como ' . $etiqueta . ').</li>';
                    }
                }

                if (!empty($itemsNormas)) {
                    $itemsNormas[] = '<li>Artículo 58 del Código Sustantivo del Trabajo, que establece las obligaciones especiales del trabajador frente al empleador.</li>';
                    $itemsNormas[] = '<li>Las cláusulas del contrato de trabajo suscrito entre las partes.</li>';
                    $normasHTML = '<ul>' . implode('', $itemsNormas) . '</ul>';
                }
            }

            if ($normasHTML === null) {
                // Referencia genérica sin inventar artículos ni un RIT que no
                // existe: RIT real de la empresa si lo tiene, o el CST solo.
                $referenciaRIT = $tieneRIT
                    ? 'el Reglamento Interno de Trabajo de <strong>' . $nombreEmpresa . '</strong> (depositado ante el Ministerio del Trabajo), en sus disposiciones sobre obligaciones, conducta y disciplina del trabajador, y '
                    : '';
                $normasHTML = '<ul>
                    <li>' . $referenciaRIT . 'el Artículo 58 del Código Sustantivo del Trabajo, que establece las obligaciones especiales del trabajador frente al empleador.</li>
                    <li>Las cláusulas del contrato de trabajo suscrito entre las partes.</li>
                </ul>';
            }
        }

        // ── Consecuencias según tipo de sanción considerada ──────────────────
        $tipoSancion = $proceso->tipo_sancion ?? '';
        $diasSuspension = $proceso->dias_suspension ?? 8;
        if ($tipoSancion === 'llamado_atencion') {
            $consecuenciasHTML = '<ul>
                <li>Llamado de atención verbal y/o escrito, con anotación en la hoja de vida laboral.</li>
                <li>En caso de reincidencia, podrá derivar en sanciones de mayor gravedad.</li>
            </ul>';
        } elseif ($tipoSancion === 'suspension') {
            $consecuenciasHTML = '<ul>
                <li>Suspensión laboral sin derecho a salario por un período de hasta <strong>' . $diasSuspension . ' días</strong>.</li>
                <li>En caso de reincidencia o calificación más grave de la falta, terminación del contrato de trabajo con justa causa.</li>
            </ul>';
        } elseif ($tipoSancion === 'terminacion') {
            $consecuenciasHTML = '<ul>
                <li>Terminación del contrato de trabajo con justa causa, de conformidad con el artículo 62 del Código Sustantivo del Trabajo.</li>
                <li>Las conductas probadas pueden dar lugar a acciones civiles o penales adicionales según la normativa vigente.</li>
            </ul>';
        } else {
            $consecuenciasHTML = '<ul>
                <li>Suspensión laboral sin derecho a salario.</li>
                <li>Terminación del contrato de trabajo con justa causa, en caso de que las faltas sean calificadas como graves tras el análisis de las pruebas y su defensa.</li>
            </ul>';
        }

        // ── Tabla de sanciones - la(s) conducta(s) del incidente concreto si
        //    están disponibles; si no, el catálogo completo del RIT como
        //    respaldo (ver filasTablaSancionesConFallback) para no dejar la
        //    tabla vacía.
        $tablaSancionesHTML = '';
        try {
            $filas = $this->filasTablaSancionesConFallback($proceso, $empresa);

            if (!empty($filas)) {
                $colorGrav = ['Leve' => '#15803d', 'Grave' => '#b91c1c', 'Muy grave' => '#7f1d1d'];

                $filasHTML = '';
                foreach ($filas as $fila) {
                    $color = $colorGrav[$fila['gravedad']] ?? '#374151';
                    $items = implode('', array_map(fn($f) => '<li>' . e($f) . '</li>', $fila['conductas']));
                    $filasHTML .= '<tr>
                        <td class="tabla-rit-tipo" style="color:' . $color . ';">' . e(mb_strtoupper($fila['gravedad'])) . '</td>
                        <td class="tabla-rit-conductas"><ul>' . $items . '</ul></td>
                        <td class="tabla-rit-sancion">' . e($fila['sancion']) . '</td>
                    </tr>';
                }

                // El título y el nombre de la empresa van FUERA de la tabla (solo se
                // muestran una vez); solo la fila de encabezados de columna va dentro de
                // <thead> para que DomPDF la repita en cada página nueva si la tabla se
                // parte. Antes las 3 filas (título, empresa, encabezados) iban dentro del
                // <table> sin thead/tbody - eso hacía que DomPDF calculara mal el corte de
                // página en tablas largas y el borde de la fila partida terminaba
                // saliéndose del margen inferior en vez de respetarlo como el encabezado.
                //
                // Si la empresa no tiene RIT, estas filas vienen del catálogo
                // genérico de respaldo del CST, no de un reglamento real - el
                // texto de la tabla no puede decir "Reglamento Interno" en ese
                // caso (bug real: PD-2026-0119, CES LEGAL S.A.S. sin RIT).
                $subtituloTabla = $tieneRIT
                    ? 'Todas las sanciones contenidas en esta tabla solo se aplicarán previa garantía del debido proceso establecido en el Reglamento Interno, conforme a la Ley 2466 de 2025.'
                    : 'Esta empresa no tiene un Reglamento Interno de Trabajo registrado en el sistema. Las sanciones contenidas en esta tabla corresponden al régimen general del Código Sustantivo del Trabajo y solo se aplicarán previa garantía del debido proceso, conforme a la Ley 2466 de 2025.';
                $columnaConductas = $tieneRIT ? 'Conductas reguladas por el Reglamento Interno' : 'Conductas reguladas por el Código Sustantivo del Trabajo';
                $piePagina = $tieneRIT
                    ? 'Tabla conforme al Reglamento Interno de Trabajo de ' . e($empresa->nombre_completo) . ', de conformidad con la Ley 2466 de 2025. Toda sanción se aplicará previa garantía del debido proceso.'
                    : 'Tabla conforme al Código Sustantivo del Trabajo, de conformidad con la Ley 2466 de 2025. Toda sanción se aplicará previa garantía del debido proceso.';

                $tablaSancionesHTML = '<div class="tabla-rit-header-empresa">
                    <strong>TABLA DE SANCIONES LABORALES</strong><br>
                    <span style="font-size:8.5pt;">(' . $subtituloTabla . ')</span>
                </div>
                <p class="tabla-rit-empresa">' . e($empresa->nombre_completo) . ' &nbsp;|&nbsp; NIT: ' . e($empresa->nit) . '</p>
                <table class="tabla-rit">
                    <thead>
                    <tr class="tabla-rit-thead">
                        <th style="width:15%;">Tipo de Falta</th>
                        <th style="width:57%;">' . $columnaConductas . '</th>
                        <th style="width:28%;">Sanción aplicable</th>
                    </tr>
                    </thead>
                    <tbody>
                    ' . $filasHTML . '
                    </tbody>
                </table>
                <p class="tabla-rit-pie">' . $piePagina . '</p>';
            }
        } catch (\Throwable $e) {
            // Si falla la construcción de la tabla, el documento se genera sin ella
        }

        // ── Fecha disponibilidad pruebas (día hábil siguiente, según la jornada) ──
        $diasHabiles = $empresa?->diasHabilesSet() ?? \App\Support\DiasHabiles::DEFECTO;
        $fechaDisponibilidadPruebas = Carbon::now()->copy();
        do {
            $fechaDisponibilidadPruebas->addDay();
        } while (! in_array($fechaDisponibilidadPruebas->dayOfWeekIso, $diasHabiles, true));
        $fechaDisponibilidadPruebas->locale('es');
        $fechaDispTexto = $fechaDisponibilidadPruebas->isoFormat('D [de] MMMM [de] YYYY');

        // ── Fragmentos HTML condicionales (no se pueden usar ternarios en heredoc) ─
        $htmlDirTrabajador   = $dirTrabajador   ? "<p>{$dirTrabajador}</p>" : '';
        $htmlTelefonoEmpresa = $telefonoEmpresa ? "<p>Tel: {$telefonoEmpresa}</p>" : '';
        $htmlEmailFirma      = $emailContacto   ? "<p>{$emailContacto}</p>" : '';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comunicación Formal de Apertura de Investigación Disciplinaria</title>
    <style>
        /* Top/bottom ampliados 1.4cm/1.2cm sobre el original (2.5cm) para
           reservar una franja de encabezado/pie donde vive el membrete (ver
           membrete-empresa.blade.php) - dompdf posiciona los elementos
           `position: fixed` dentro del área de contenido, no en el margen
           físico, así que sin esta franja el logo y el pie chocan con el
           texto cuando el contenido llega hasta el borde. */
        @page { margin: 3.9cm 2.5cm 3.7cm 3cm; }
        body {
            /* Tahoma 10pt, sin subrayados - mismo tipo de letra, tamaño y
               estilo (títulos/artículos en negrilla simple, no subrayados
               ni con tamaño distinto) que usa el bufete en sus citaciones
               reales - ver docx de referencia "DELGADILLO RUBIO". */
            font-family: 'Tahoma', 'Verdana', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.35;
            color: #000;
            text-align: justify;
        }
        .destinatario { margin-bottom: 18px; }
        .destinatario p { margin: 0; line-height: 1.35; }
        .asunto { margin-bottom: 18px; }
        .asunto p { margin: 0; }
        p { margin: 0 0 12px 0; }
        h3 {
            font-size: 10pt;
            font-weight: bold;
            margin: 18px 0 6px 0;
        }
        ul, ol { margin: 4px 0 12px 0; padding-left: 24px; }
        li { margin-bottom: 6px; }
        .diligencia-datos {
            margin: 8px 0 12px 16px;
        }
        .diligencia-datos p { margin: 0; line-height: 1.6; }
        .firma-bloque { margin-top: 48px; }
        .firma-bloque p { margin: 0; line-height: 1.4; }
        .linea-firma {
            margin-top: 56px;
            border-top: 1px solid #000;
            width: 260px;
            padding-top: 5px;
        }
        .constancia {
            margin-top: 36px;
            border-top: 2px solid #000;
            padding-top: 14px;
        }
        .constancia h3 { text-decoration: none; }
        .firma-trabajador {
            margin-top: 50px;
            border-top: 1px solid #000;
            width: 260px;
            padding-top: 5px;
        }
        strong { font-weight: bold; }
        .italic { font-style: italic; }
        .tabla-rit {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin: 0 0 4px 0;
        }
        .tabla-rit td, .tabla-rit th {
            border: 1px solid #374151;
            padding: 5px 7px;
            vertical-align: top;
        }
        /* Cada fila de datos se mantiene entera en una sola página - evita que
           DomPDF corte una fila a la mitad y su borde termine saliéndose del
           margen inferior en vez de respetarlo. */
        .tabla-rit tbody tr {
            page-break-inside: avoid;
        }
        .tabla-rit-header-empresa {
            text-align: center;
            background-color: #f3f4f6;
            border: 1px solid #374151;
            border-bottom: none;
            padding: 5px 7px;
            margin: 8px 0 0 0;
        }
        .tabla-rit-empresa {
            text-align: center;
            font-size: 9pt;
            border: 1px solid #374151;
            border-top: none;
            padding: 5px 7px;
            margin: 0;
        }
        .tabla-rit-thead th {
            background-color: #e5e7eb;
            text-align: center;
            font-weight: bold;
        }
        .tabla-rit-tipo {
            text-align: center;
            font-weight: bold;
        }
        .tabla-rit-conductas ul {
            margin: 0;
            padding-left: 14px;
        }
        .tabla-rit-conductas li { margin-bottom: 2px; }
        .tabla-rit-sancion { text-align: center; }
        .tabla-rit-pie {
            font-size: 8pt;
            color: #6b7280;
            margin: 2px 0 0 0;
        }
    </style>
</head>
<body>

    <!-- DESTINATARIO -->
    <div class="destinatario">
        <p>Señora/Señor</p>
        <p><strong>{$nombreTrabajador}</strong></p>
        {$htmlDirTrabajador}
        <p>{$ciudadTrabajador}</p>
        <p><strong>Fecha:</strong> {$dia} de {$mes} de {$anio}</p>
    </div>

    <!-- ASUNTO -->
    <div class="asunto">
        <p><strong>Asunto:</strong> Citación a Diligencia Administrativa por apertura de Proceso Disciplinario Laboral</p>
    </div>

    <!-- APERTURA -->
    <p>De conformidad con lo establecido en el Artículo 7 de la Ley 2466 de 2025, la empresa <strong>{$nombreEmpresa}</strong>, en su calidad de empleador, le notifica formalmente la apertura de un proceso disciplinario laboral en su contra, derivado de las conductas que se describen a continuación.</p>

    <!-- SECCIÓN 1: CONDUCTAS IMPUTADAS -->
    <h3>Detalles de las Conductas Imputadas</h3>
    <p>Las razones por las cuales se le cita a diligencia de descargos se fundamentan, presuntamente, en los siguientes hechos, puestos en su conocimiento para que pueda ejercer su derecho de defensa:</p>
    {$conductasHTML}

    <!-- SECCIÓN 2: INCUMPLIMIENTOS -->
    <h3>Incumplimientos Contractuales o Legales</h3>
    <p>Los anteriores hechos implican una posible violación a sus obligaciones contractuales, reglamentarias y legales, que pueden ser constitutivas de una falta disciplinaria de acuerdo con las siguientes normas:</p>
    {$normasHTML}

    <!-- SECCIÓN 3: CONSECUENCIAS -->
    <h3>Consecuencias de las Faltas</h3>
    <p>Las conductas imputadas podrían dar lugar a las siguientes sanciones, de acuerdo con el Reglamento Interno de Trabajo y la normativa aplicable:</p>
    {$tablaSancionesHTML}

    <!-- SECCIÓN 4: CITACIÓN -->
    <h3>Citación a Diligencia de Descargos</h3>
    <p>Con el fin de que pueda ejercer su derecho de defensa, se le cita a rendir descargos en la siguiente diligencia:</p>
    <div class="diligencia-datos">
        <p><strong>Fecha:</strong> {$diaDescargos} de {$mesDescargos} de {$anioDescargos}</p>
        <p><strong>Hora:</strong> {$horaTexto}</p>
        <p><strong>Lugar:</strong> {$lugarDiligencia}</p>
    </div>
    <p>Durante esta diligencia, usted tendrá la oportunidad de presentar sus explicaciones, controvertir las pruebas que le están siendo trasladadas y aportar las pruebas que considere necesarias para sustentar su defensa. Las pruebas que fundamentan los cargos formulados serán puestas a su disposición en el área de Gestión Humana a partir del {$fechaDispTexto}, en el horario de atención de la empresa.</p>
    <p>Esta citación tiene como propósito garantizar su derecho al debido proceso, permitiéndole rendir explicaciones y ejercer su defensa frente a los cargos formulados.</p>

    <!-- SECCIÓN 5: TRASLADO DE PRUEBAS -->
    <h3>Traslado de Pruebas</h3>
    <p>Se le informa que la empresa ha puesto a su disposición todas las pruebas que sustentan los cargos imputados. Estas pruebas le serán trasladadas para su revisión y análisis por un término de <strong>cinco (5) días hábiles</strong>, contados a partir de la fecha de la notificación de este documento. Usted podrá acceder a ellas en el área de Gestión Humana, en el horario de atención, con el fin de que pueda preparar su defensa de manera adecuada y oportuna.</p>

    <!-- SECCIÓN 6: ADVERTENCIA -->
    <h3>Advertencia</h3>
    <p>De no presentarse a la diligencia sin justa causa, la empresa procederá a continuar el proceso disciplinario con base en las pruebas disponibles.</p>

    <!-- SECCIÓN 7: SOLICITUDES PREVIAS -->
    <h3>Solicitudes Previas</h3>
    <p>Las solicitudes sobre acompañantes, representación sindical y ajuste razonable por discapacidad deberán ser respondidas a través del <strong>formulario digital de descargos</strong> que recibirá por correo electrónico junto con esta comunicación. Dichas solicitudes incluyen:</p>
    <ul>
        <li>Si asistirá acompañado(a) y en qué calidad (testigo, representante sindical, apoderado u otro).</li>
        <li>Si desea ser asistido(a) por uno o dos representantes del sindicato al que pertenezca.</li>
        <li>Si requiere algún ajuste razonable para la comunicación o comprensión de la diligencia debido a una condición de discapacidad.</li>
    </ul>
    <p>Para comunicaciones previas urgentes, puede contactar a la empresa al correo <strong>{$emailContacto}</strong>.</p>

    <!-- FIRMA -->
    <div class="firma-bloque">
        <p>Atentamente,</p>
        <div class="linea-firma">
            <p><strong>{$representante}</strong></p>
            <p>Representante Legal</p>
            <p><strong>{$nombreEmpresa}</strong></p>
            {$htmlTelefonoEmpresa}
            {$htmlEmailFirma}
        </div>
    </div>

    <!-- CONSTANCIA DE RECIBO -->
    <div class="constancia">
        <h3>CONSTANCIA DE RECIBO / NOTIFICACIÓN</h3>
        <p><strong>Notificación electrónica (equivalencia funcional):</strong> De conformidad con los artículos 6 y 18 de la Ley 527 de 1999 y el principio de equivalencia funcional del mensaje de datos, si la presente comunicación fue entregada por correo electrónico al trabajador, el registro de envío, primera apertura, dirección IP y marca de tiempo constituyen <em>constancia idónea de notificación</em>, sin necesidad de firma física. La apertura del mensaje equivale al acuse de recibo (Art. 18 Ley 527/1999).</p>

        <p><strong>Entrega en medio físico (cuando aplique):</strong> Si la comunicación fue entregada personalmente, el trabajador firma a continuación:</p>

        <p>Yo, <strong>{$nombreTrabajador}</strong>, identificado(a) con {$tipoDoc} No. <strong>{$numDocTrabajador}</strong>, declaro que recibí copia de la presente comunicación el día ___ de _______________ de _______, en la cual se me notifica la apertura del proceso de investigación disciplinario.</p>
        <div class="firma-trabajador">
            <p><strong>Firma del Trabajador:</strong></p>
            <p>Nombre: {$nombreTrabajador}</p>
            <p>{$tipoDoc}: {$numDocTrabajador}</p>
        </div>
    </div>

</body>
</html>
HTML;

        // Membrete de empresa (logo + franja + marca de agua + pie de
        // página) - mismo mecanismo que MARCA_AGUA_BORRADOR en
        // SolicitudContratoIAService, capa puramente visual sin tocar el
        // texto legal. No renderiza nada si la empresa no tiene logo (ver
        // membrete-empresa.blade.php).
        $membrete = view('pdfs.components.membrete-empresa', ['empresa' => $empresa])->render();
        $html = str_replace('</body>', $membrete . '</body>', $html);

        return $html;
    }

    /**
     * Convierte un `base_legal` crudo (ej. "Artículo 76 RIT", "Art. 60 CST",
     * "Art. 58 y 60 CST") en una cita de artículo lista para mostrar en negrilla
     * (ej. "Artículo 76", "Artículo 60", "Artículos 58 y 60"). Devuelve null
     * cuando el valor no trae un número real que citar (ej. "RIT de la empresa
     * (constructor)" o "RIT de la empresa" - fuentes sin artículo detectado de
     * forma determinística) para nunca inventar un número de artículo.
     */
    private function formatearCitaArticulo(?string $baseLegal, string $sufijo): ?string
    {
        $baseLegal = trim((string) $baseLegal);
        if ($baseLegal === '' || !preg_match('/\d/', $baseLegal)) {
            return null;
        }

        $texto = trim((string) preg_replace('/\s*' . preg_quote($sufijo, '/') . '\s*$/u', '', $baseLegal));
        $texto = preg_replace('/^Art\.\s*/u', 'Artículo ', $texto);
        if (preg_match('/\sy\s|,/u', $texto)) {
            $texto = preg_replace('/^Artículo\s/u', 'Artículos ', $texto);
        }

        return $texto;
    }

    /**
     * Convierte el HTML de citación a PDF usando Dompdf
     */
    private function convertirCitacionHTMLaPDF(string $html, string $codigo): string
    {
        $outputDir = storage_path('app/citaciones');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $timestamp = time();
        $pdfPath = $outputDir . DIRECTORY_SEPARATOR . 'citacion_' . $codigo . '_' . $timestamp . '.pdf';

        try {
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            $options->set('isFontSubsettingEnabled', true);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();

            file_put_contents($pdfPath, $dompdf->output());

            Log::info('PDF de citación generado exitosamente', [
                'path' => $pdfPath,
                'codigo' => $codigo
            ]);

            return $pdfPath;
        } catch (\Exception $e) {
            Log::error('Error al generar PDF de citación', [
                'error' => $e->getMessage(),
                'codigo' => $codigo
            ]);

            // Guardar como HTML si falla el PDF
            $htmlPath = $outputDir . DIRECTORY_SEPARATOR . 'citacion_' . $codigo . '_' . $timestamp . '.html';
            file_put_contents($htmlPath, $html);

            return $htmlPath;
        }
    }

    /**
     * Convertir DOCX a PDF
     */
    private function convertirDocxAPdf(string $docxPath, string $codigo): string
    {
        $outputDir = storage_path('app/citaciones');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $baseName = pathinfo($docxPath, PATHINFO_FILENAME);

        // PDF real que genera LibreOffice
        $librePdf = $outputDir . DIRECTORY_SEPARATOR . $baseName . '.pdf';

        // PDF final que tú quieres
        $finalPdf = $outputDir . DIRECTORY_SEPARATOR . 'citacion_' . $codigo . '.pdf';

        if ($this->isLibreOfficeAvailable()) {

            $command = sprintf(
                '"%s" --headless --nofirststartwizard --nodefault --nolockcheck --nologo --norestore --convert-to pdf --outdir %s %s 2>&1',
                $this->libreOfficePath,
                escapeshellarg($outputDir),
                escapeshellarg($docxPath)
            );



            Log::info('Ejecutando LibreOffice', ['command' => $command]);

            \exec($command, $output, $return);

            Log::info('Resultado LibreOffice', [
                'return_code' => $return,
                'output' => $output,
            ]);

            // ⬅️ AQUÍ es donde debe validarse
            if ($return === 0 && file_exists($librePdf)) {

                // Renombrar al nombre final
                rename($librePdf, $finalPdf);

                return $finalPdf;
            }
        }

        // Fallback (solo si LibreOffice falla)
        $fallback = $outputDir . DIRECTORY_SEPARATOR . 'citacion_' . $codigo . '.docx';
        copy($docxPath, $fallback);

        return $fallback;
    }


    // private function convertirDocxAPdf(string $docxPath, string $codigo): string
    // {
    //     $pdfPath = storage_path('app/citaciones/citacion_' . $codigo . '_' . time() . '.pdf');

    //     // Crear directorio si no existe
    //     if (!file_exists(storage_path('app/citaciones'))) {
    //         mkdir(storage_path('app/citaciones'), 0755, true);
    //     }

    //     // Intentar convertir con LibreOffice si está disponible
    //     if ($this->isLibreOfficeAvailable()) {
    //         $command = sprintf(
    //             'soffice --headless --convert-to pdf --outdir %s %s',
    //             escapeshellarg(dirname($pdfPath)),
    //             escapeshellarg($docxPath)
    //         );

    //         exec($command, $output, $return);

    //         if ($return === 0) {
    //             // LibreOffice genera el PDF con el mismo nombre base
    //             $generatedPdf = dirname($pdfPath) . '/' . pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf';
    //             if (file_exists($generatedPdf)) {
    //                 rename($generatedPdf, $pdfPath);
    //                 return $pdfPath;
    //             }
    //         }
    //     }

    //     // Si LibreOffice no está disponible, usar conversión alternativa
    //     // Por ahora, copiar el DOCX como alternativa
    //     // En producción, considera usar servicios como CloudConvert o similar
    //     copy($docxPath, str_replace('.pdf', '.docx', $pdfPath));

    //     return str_replace('.pdf', '.docx', $pdfPath);
    // }

    /**
     * Verificar si LibreOffice está disponible
     */
    // private function isLibreOfficeAvailable(): bool
    // {
    //     exec('soffice --version 2>&1', $output, $return);
    //     return $return === 0;
    // }

    private function isLibreOfficeAvailable(): bool
    {
        if (!function_exists('exec')) {
            Log::warning('La función exec() no está disponible en este servidor');
            return false;
        }

        if (PHP_OS_FAMILY === 'Linux') {
            \exec('which soffice 2>/dev/null', $output, $return);
            return $return === 0;
        }

        return file_exists($this->libreOfficePath) && is_executable($this->libreOfficePath);
    }


    /**
     * Enviar citación por correo electrónico
     */
    public function enviarCitacionPorEmail(ProcesoDisciplinario $proceso, string $pdfPath, ?string $linkDescargos = null, ?\Carbon\Carbon $fechaAccesoPermitida = null): void
    {
        $trabajador = $proceso->trabajador;
        $empresa = $proceso->empresa;

        if (empty($trabajador->email)) {
            throw new \Exception('El trabajador no tiene correo electrónico registrado');
        }

        // Crear registro de tracking para el correo (hora de Colombia)
        $tracking = EmailTracking::create([
            'token' => EmailTracking::generarToken(),
            'tipo_documento' => 'citacion',
            'proceso_id' => $proceso->id,
            'trabajador_id' => $trabajador->id,
            'email_destinatario' => $trabajador->email,
            'enviado_en' => Carbon::now('America/Bogota'),
        ]);

        // Detectar la extensión real del archivo
        $extension = pathinfo($pdfPath, PATHINFO_EXTENSION);
        $mimeType = $extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $nombreArchivo = 'Citacion_Descargos_' . $proceso->codigo . '.' . $extension;

        // Nombres de las pruebas aportadas por la empresa. Solo se enumeran en
        // el correo (no se adjuntan): hasta 5 archivos de 10 MB harían rebotar
        // el envío en la mayoría de servidores y dejarían material probatorio
        // en un correo reenviable. El trabajador las abre desde el enlace, que
        // ya exige código de verificación.
        $evidenciasEmpleador = collect($proceso->evidencias_empleador ?? [])
            ->filter()
            ->map(fn (string $ruta) => basename($ruta))
            ->values()
            ->all();

        Mail::send('emails.citacion-descargos', [
            'proceso' => $proceso,
            'trabajador' => $trabajador,
            'empresa' => $empresa,
            'linkDescargos' => $linkDescargos,
            'fechaAccesoPermitida' => $fechaAccesoPermitida,
            'trackingToken' => $tracking->token,
            'evidenciasEmpleador' => $evidenciasEmpleador,
        ], function ($message) use ($trabajador, $proceso, $pdfPath, $nombreArchivo, $mimeType) {
            $message->to($trabajador->email, $trabajador->nombre_completo)
                ->subject('Citación a Audiencia de Descargos - Proceso ' . $proceso->codigo)
                ->attach($pdfPath, [
                    'as' => $nombreArchivo,
                    'mime' => $mimeType,
                ]);
        });

        Log::info('Citación enviada con tracking', [
            'proceso_id' => $proceso->id,
            'trabajador_email' => $trabajador->email,
            'tracking_token' => substr($tracking->token, 0, 10) . '...',
        ]);
    }

    /**
     * Generar y enviar citación (proceso completo)
     */
    public function generarYEnviarCitacion(ProcesoDisciplinario $proceso): array
    {
        try {
            // Generar el PDF
            $pdfPath = $this->generarCitacionDescargos($proceso);

            // Crear o actualizar diligencia de descargo
            $diligencia = \App\Models\DiligenciaDescargo::firstOrCreate(
                ['proceso_id' => $proceso->id],
                [
                    'fecha_diligencia' => $proceso->fecha_descargos_programada,
                    'lugar_diligencia' => $proceso->modalidad_descargos === 'presencial'
                        ? ($proceso->empresa->direccion ?? 'Oficinas de la empresa')
                        : 'virtual',
                ]
            );

            // Configurar fecha de acceso ANTES de generar el token
            // (generarTokenAcceso usa fecha_acceso_permitida para calcular la expiración)
            $diligencia->fecha_acceso_permitida = $proceso->fecha_descargos_programada
                ? Carbon::parse($proceso->fecha_descargos_programada)->toDateString()
                : now()->toDateString();
            $diligencia->acceso_habilitado = true;

            // Generar token de acceso si no existe
            if (!$diligencia->token_acceso) {
                $diligencia->generarTokenAcceso(); // guarda internamente
            } else {
                $diligencia->save();
            }

            // Intentar generar preguntas completas (estándar + IA + cierre) si no existen
            $preguntasGeneradasConIA = false;
            if ($diligencia->preguntas()->count() === 0) {
                try {
                    $iaService = new IADescargoService();
                    $preguntasGeneradas = $iaService->generarPreguntasCompletas($diligencia, 2);

                    // Verificar si se generaron preguntas con IA
                    $preguntasConIA = collect($preguntasGeneradas)->filter(function ($pregunta) {
                        return $pregunta->es_generada_por_ia ?? false;
                    })->count();

                    if ($preguntasConIA > 0) {
                        $preguntasGeneradasConIA = true;
                    } else {
                        // Si no se generaron preguntas con IA, solo registrar warning
                        \Illuminate\Support\Facades\Log::warning('No se generaron preguntas con IA', [
                            'proceso_id' => $proceso->id,
                            'diligencia_id' => $diligencia->id,
                            'preguntas_totales' => count($preguntasGeneradas),
                        ]);
                    }
                } catch (\Exception $e) {
                    // Registrar el error pero NO detener el envío del correo
                    \Illuminate\Support\Facades\Log::warning('Error al generar preguntas con IA (se continuará con el envío)', [
                        'proceso_id' => $proceso->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Solo generar link de acceso si la modalidad es virtual
            $linkDescargos = null;
            $fechaAccesoPermitida = null;

            if ($proceso->modalidad_descargos === 'virtual') {
                $linkDescargos = route('descargos.acceso', ['token' => $diligencia->token_acceso]);
                $fechaAccesoPermitida = Carbon::parse($diligencia->fecha_acceso_permitida);
            }

            // Guardar documento en la base de datos
            $extension = pathinfo($pdfPath, PATHINFO_EXTENSION);
            $documento = \App\Models\Documento::create([
                'documentable_type' => ProcesoDisciplinario::class,
                'documentable_id' => $proceso->id,
                'tipo_documento' => 'citacion_descargos',
                'nombre_archivo' => 'Citacion_Descargos_' . $proceso->codigo . '.' . $extension,
                'ruta_archivo' => $pdfPath,
                'formato' => $extension,
                'generado_por' => auth()->id() ?? 1,
                'version' => 1,
                'plantilla_usada' => 'Generación directa PDF con Dompdf',
                'variables_usadas' => null,
                'fecha_generacion' => now(),
            ]);

            // Enviar por email (con o sin link según la modalidad)
            $this->enviarCitacionPorEmail($proceso, $pdfPath, $linkDescargos, $fechaAccesoPermitida);

            // Cambiar estado automáticamente a "descargos_pendientes"
            // IMPORTANTE: Hacer esto ANTES de refresh() para que el Observer lo detecte correctamente
            $proceso->estado = 'descargos_pendientes';
            $proceso->save();

            // Refrescar el proceso desde la BD para asegurar que tiene el estado correcto
            $proceso->refresh();

            // Registrar en el timeline
            $timelineService = app(TimelineService::class);

            // Registrar documento generado
            $timelineService->registrarDocumentoGenerado(
                procesoTipo: 'proceso_disciplinario',
                procesoId: $proceso->id,
                tipoDocumento: 'Citación a descargos',
                nombreArchivo: basename($pdfPath)
            );

            // Registrar notificación enviada
            $timelineService->registrarNotificacion(
                procesoTipo: 'proceso_disciplinario',
                procesoId: $proceso->id,
                tipoNotificacion: 'Citación a descargos',
                destinatario: $proceso->trabajador->email
            );

            // Preparar mensaje de éxito
            $extension = pathinfo($pdfPath, PATHINFO_EXTENSION);
            $mensaje = 'Citación generada y enviada exitosamente. Diligencia de descargos creada con acceso web.';

            // Advertir si se envió DOCX en lugar de PDF
            if ($extension === 'docx') {
                $mensaje .= ' ADVERTENCIA: LibreOffice no está instalado, el documento fue enviado en formato DOCX en lugar de PDF.';
            }

            if (!$preguntasGeneradasConIA) {
                $mensaje .= ' NOTA: No se pudieron generar preguntas con IA. Deberá generarlas manualmente desde el módulo de Descargos.';
            }

            return [
                'success' => true,
                'message' => $mensaje,
                'pdf_path' => $pdfPath,
                'diligencia_id' => $diligencia->id,
                'link_descargos' => $linkDescargos,
                'preguntas_ia_generadas' => $preguntasGeneradasConIA,
                'formato_documento' => $extension, // Agregar formato del documento
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generar documento de sanción con IA usando lenguaje claro
     */
    public function generarDocumentoSancion(ProcesoDisciplinario $proceso, string $tipoSancion): array
    {
        try {
            // Obtener la información del trabajador y empresa
            $trabajador = $proceso->trabajador;
            $empresa = $proceso->empresa;
            $diligencia = $proceso->diligenciaDescargo;

            if (!$diligencia) {
                throw new \Exception('No se encontró la diligencia de descargos para este proceso');
            }

            // Obtener las preguntas y respuestas de los descargos
            $preguntasRespuestas = $diligencia->preguntas()
                ->with('respuesta')
                ->ordenadas()
                ->get()
                ->map(function ($pregunta) {
                    return [
                        'pregunta' => $pregunta->pregunta,
                        'respuesta' => $pregunta->respuesta?->respuesta ?? 'Sin respuesta'
                    ];
                })
                ->toArray();

            // Detectar si el trabajador NO respondió al formulario de descargos
            $totalPreguntas = count($preguntasRespuestas);
            $preguntasRespondidas = collect($preguntasRespuestas)->filter(fn($pr) => $pr['respuesta'] !== 'Sin respuesta')->count();
            $trabajadorNoRespondio = $preguntasRespondidas === 0;

            // Construir el contexto de descargos
            $contextoDescargos = '';
            if ($trabajadorNoRespondio) {
                $contextoDescargos = "EL TRABAJADOR NO RESPONDIÓ AL FORMULARIO DE DESCARGOS.\n";
                $contextoDescargos .= "Se le envió la citación a descargos con fecha programada: {$proceso->fecha_descargos_programada}.\n";
                $contextoDescargos .= "El trabajador no presentó sus descargos dentro del plazo establecido, por lo cual se procede a emitir la sanción sin su versión de los hechos.\n";
                $contextoDescargos .= "Se garantizó el derecho a la defensa al enviar la citación y dar la oportunidad de responder.\n\n";
            } else {
                foreach ($preguntasRespuestas as $index => $pr) {
                    $contextoDescargos .= ($index + 1) . ". Pregunta: {$pr['pregunta']}\n   Respuesta del trabajador: {$pr['respuesta']}\n\n";
                }
            }

            // Configuración de la API de IA
            $provider = config('services.ia.provider', 'openai');
            $config = config("services.ia.{$provider}", []);
            $apiKey  = $config['api_key'];
            $modelos = array_unique(array_filter([
                $config['model'] ?? 'gemini-2.5-flash',
                'gemini-2.5-flash',
                'gemini-2.5-flash-lite',
            ]));

            // Construir el prompt con principios de lenguaje claro.
            // Para 'no_sancion' se usa un prompt dedicado (constancia de cierre
            // sin sanción), porque el prompt de sanción está redactado para
            // imponer una medida y ofrecer derechos de impugnación, lo cual es
            // incoherente cuando la decisión favorece al trabajador.
            if ($tipoSancion === 'no_sancion') {
                $prompt = $this->construirPromptConstanciaNoSancion(
                    $proceso,
                    $trabajador,
                    $empresa,
                    $contextoDescargos,
                    $trabajadorNoRespondio
                );
            } else {
                // Último intento de tener una conducta concreta del RIT que citar
                // antes de resignarse a "No especificado" - ver el docblock del
                // método para el caso real que lo motivó.
                $proceso->asegurarClasificacionIncidente();

                $prompt = $this->construirPromptSancionLenguajeClaro(
                    $proceso,
                    $trabajador,
                    $empresa,
                    $tipoSancion,
                    $contextoDescargos,
                    $trabajadorNoRespondio
                );
            }

            // Log para debugging
            \Illuminate\Support\Facades\Log::info('Generando documento de sanción con IA', [
                'proceso_id' => $proceso->id,
                'tipo_sancion' => $tipoSancion,
                'max_tokens' => $config['max_tokens'] ?? 8000,
            ]);

            // Llamar a la API de IA con fallback entre modelos
            $response = null;
            foreach ($modelos as $modeloActual) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modeloActual}:generateContent?key={$apiKey}";
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(120)->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 8192,
                        'topP' => 0.95,
                    ],
                ]);

                if (in_array($response->status(), [503, 404])) {
                    continue;
                }
                break;
            }

            if (!$response->successful()) {
                $errorBody = $response->body();
                \Illuminate\Support\Facades\Log::error('Error en API de IA', [
                    'proceso_id' => $proceso->id,
                    'status' => $response->status(),
                    'error' => $errorBody,
                ]);
                throw new \Exception("Error en API de IA: " . $errorBody);
            }

            $responseData = $response->json();

            if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                \Illuminate\Support\Facades\Log::error('Respuesta de IA sin contenido', [
                    'proceso_id' => $proceso->id,
                    'response' => $responseData,
                ]);
                throw new \Exception("Respuesta de IA sin contenido válido");
            }

            $documentoSancion = $responseData['candidates'][0]['content']['parts'][0]['text'];

            // Verificar si la respuesta está completa
            $finishReason = $responseData['candidates'][0]['finishReason'] ?? 'UNKNOWN';

            \Illuminate\Support\Facades\Log::info('Documento generado por IA', [
                'proceso_id' => $proceso->id,
                'finish_reason' => $finishReason,
                'contenido_length' => strlen($documentoSancion),
                'contenido_preview' => substr($documentoSancion, 0, 200),
            ]);

            if ($finishReason === 'MAX_TOKENS') {
                \Illuminate\Support\Facades\Log::warning('Respuesta de IA truncada por límite de tokens', [
                    'proceso_id' => $proceso->id,
                    'finish_reason' => $finishReason,
                ]);
            }

            // Limpiar el contenido (remover bloques de código markdown si existen)
            $documentoSancion = $this->limpiarContenidoHTML($documentoSancion);

            // Red de seguridad: el prompt usa corchetes como marcador de "completa
            // esto" y prohíbe explícitamente dejarlos en el texto final, pero un
            // modelo de lenguaje puede incumplirlo (caso real: "[Día] de [Mes]"
            // llegó sin resolver a un documento entregado a un trabajador). Nunca
            // debe salir un corchete sin resolver en un documento legal real.
            $documentoSancion = $this->limpiarPlaceholdersSinResolver($documentoSancion, $proceso);

            // Insertar la TABLA DE SANCIONES de forma determinística (verbatim del
            // RIT y, para "otro motivo", del CST citado textual). La IA nunca la toca.
            if ($tipoSancion !== 'no_sancion') {
                $documentoSancion = $this->inyectarTablaSanciones($documentoSancion, $proceso, $empresa);
            }

            // Membrete de empresa (logo + franja + marca de agua + pie de página) -
            // se inyecta al final, sobre el HTML ya resuelto (después de
            // limpiarContenidoHTML()/envolverEnHTMLCompleto() y de la tabla de
            // sanciones), nunca antes: la IA a veces devuelve su propio
            // <!DOCTYPE>/<html> completo con su propio </body>, no controlado por
            // código - mismo motivo por el que inyectarTablaSanciones() de arriba
            // tampoco confía ciegamente en un </body>.
            $documentoSancion = $this->inyectarMembreteSancion($documentoSancion, $empresa);

            // Guardar el documento generado como HTML temporal
            $htmlPath = $this->guardarDocumentoSancionHTML($documentoSancion, $proceso->codigo, $tipoSancion);

            // Convertir a PDF si es posible
            $pdfPath = $this->convertirHTMLaPDF($htmlPath, $proceso->codigo, $tipoSancion);

            return [
                'success' => true,
                'documento_path' => $pdfPath,
                'documento_contenido' => $documentoSancion,
                'tipo_sancion' => $tipoSancion,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al generar documento de sanción con IA', [
                'proceso_id' => $proceso->id,
                'tipo_sancion' => $tipoSancion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al generar documento: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Limpiar contenido HTML removiendo bloques de código markdown
     */
    private function limpiarContenidoHTML(string $contenido): string
    {
        // Remover bloques de código markdown (```html ... ```)
        $contenido = preg_replace('/```html\s*/', '', $contenido);
        $contenido = preg_replace('/```\s*$/', '', $contenido);
        $contenido = preg_replace('/```/', '', $contenido);

        // Asegurar que tenga estructura HTML básica si no la tiene
        if (stripos($contenido, '<!DOCTYPE') === false && stripos($contenido, '<html') === false) {
            $contenido = $this->envolverEnHTMLCompleto($contenido);
        }

        return trim($contenido);
    }

    /**
     * Elimina cualquier marcador entre corchetes que la IA haya dejado sin
     * resolver (ej. "[Día]", "[Mes]") - el prompt los usa como instrucción
     * interna de "completa esto" y prohíbe expresamente dejarlos en el texto
     * final, pero un modelo de lenguaje puede incumplirlo. Se registra en el
     * log para poder mejorar el prompt si se repite, y se elimina el
     * marcador (nunca se manda un corchete sin resolver a un documento legal
     * real entregado a un trabajador).
     */
    private function limpiarPlaceholdersSinResolver(string $contenido, ProcesoDisciplinario $proceso): string
    {
        if (!preg_match_all('/\[[^\[\]]{1,80}\]/u', $contenido, $matches)) {
            return $contenido;
        }

        \Illuminate\Support\Facades\Log::warning('Documento de sanción con placeholders sin resolver', [
            'proceso_id'   => $proceso->id,
            'placeholders' => $matches[0],
        ]);

        return preg_replace('/\[[^\[\]]{1,80}\]/u', '', $contenido);
    }

    /**
     * Envolver contenido en estructura HTML completa
     */
    private function envolverEnHTMLCompleto(string $contenido): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento de Sanción</title>
    <style>
        @page {
            /* Top/bottom ampliados 1.4cm/1.2cm sobre el original (2cm) para
               reservar una franja de encabezado/pie donde vive el membrete
               (ver membrete-empresa.blade.php, inyectado por
               inyectarMembreteSancion()) - dompdf posiciona los elementos
               `position: fixed` dentro del área de contenido, no en el
               margen físico, así que sin esta franja el logo y el pie
               chocan con el texto cuando el contenido llega hasta el borde. */
            margin: 3.4cm 2cm 3.2cm 2cm;
        }
        body {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.2;
            color: #000000;
            text-align: justify;
        }
        h1 {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin: 5px 0;
            color: #000000;
        }
        h2 {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            margin: 5px 0;
            color: #000000;
        }
        h3 {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            font-weight: bold;
            margin: 10px 0 4px 0;
            color: #000000;
        }
        p {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            margin: 4px 0;
            text-align: justify;
            line-height: 1.2;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .info-section {
            margin: 8px 0;
        }
        strong {
            font-weight: bold;
        }
    </style>
</head>
<body>
    {$contenido}
</body>
</html>
HTML;
    }

    /**
     * Construir prompt con principios de lenguaje claro
     */
    private function construirPromptSancionLenguajeClaro(
        ProcesoDisciplinario $proceso,
        $trabajador,
        $empresa,
        string $tipoSancion,
        string $contextoDescargos,
        bool $trabajadorNoRespondio = false
    ): string {
        $fechaActual = Carbon::now()->locale('es');
        // COMENTADO: Artículos legales - Ahora se usan Sanciones Laborales
        // $articulosLegales = $proceso->articulos_legales_texto ?? 'Código Sustantivo del Trabajo';
        $sancionesLaboralesRaw = $proceso->sanciones_laborales_texto ?? 'Reglamento Interno de Trabajo';
        // Limpiar emojis del texto de sanciones laborales
        $sancionesLaborales = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $sancionesLaboralesRaw);
        $sancionesLaborales = trim(preg_replace('/\s+/', ' ', $sancionesLaborales));
        $hechosTexto = strip_tags($proceso->hechos);

        // Justificación que registró la empresa al apartarse de la recomendación
        // de la IA. Antes solo llegaba al documento de "no sanción": si la empresa
        // elegía una sanción DISTINTA a la recomendada, su razón nunca entraba al
        // prompt. Comprobado empíricamente que eso hacía a la IA inventar una
        // proporcionalidad de relleno ("no tiene antecedentes disciplinarios")
        // que contradecía la razón real de la empresa ("ya fue advertido dos
        // veces") - un hecho falso y favorable en un documento sancionatorio.
        $razonDivergencia = trim((string) ($proceso->razon_divergencia ?? ''));
        $bloqueDivergencia = $razonDivergencia !== ''
            ? "\nDECISIÓN DISTINTA A LA RECOMENDADA POR EL ANÁLISIS - RAZÓN DE LA EMPRESA:\n{$razonDivergencia}\n"
            : '';

        // La tabla de sanciones NO la genera la IA (riesgo de parafraseo/invención).
        // Se deja un marcador y se inserta verbatim (RIT/CST) después de la respuesta.
        $tablaSanciones = '<!--TABLA_SANCIONES-->';

        // Incluir días de suspensión en el nombre si aplica
        $diasSuspension = $proceso->dias_suspension;
        $nombreSancion = match ($tipoSancion) {
            'llamado_atencion' => 'Llamado de Atención',
            'suspension' => 'Suspensión Laboral' . ($diasSuspension ? " de {$diasSuspension} día" . ($diasSuspension > 1 ? 's' : '') : ''),
            'terminacion' => 'Terminación de Contrato',
            'no_sancion' => 'Sin Sanción',
            default => 'Sanción',
        };

        $diasImpugnacion = 3; // Días hábiles para impugnar según ley colombiana

        // Preparar texto específico para suspensiones. Fechas calculadas por el
        // servidor, NUNCA pedidas al cliente ni dejadas a que la IA las invente:
        // inicia el día SIGUIENTE a la notificación (evita cualquier duda de
        // que la sanción tenga efecto retroactivo o de que el día de la propia
        // notificación cuente como día completo de suspensión) y dura los días
        // calendario acordados, ambos extremos incluidos. Pedido explícito del
        // usuario tras ver un documento real con "[Día] de [Mes]" sin resolver:
        // la IA no tenía ninguna fecha real con la cual completar esa sección.
        $textoSuspension = '';
        if ($tipoSancion === 'suspension' && $diasSuspension) {
            $fechaInicioSuspension = $fechaActual->copy()->addDay();
            $fechaFinSuspension    = $fechaInicioSuspension->copy()->addDays($diasSuspension - 1);

            $textoSuspension = "\n- Días de suspensión: {$diasSuspension} día" . ($diasSuspension > 1 ? 's' : '') . " (sin remuneración)"
                . "\n- Fecha de inicio de la suspensión: " . $fechaInicioSuspension->isoFormat('D [de] MMMM [de] YYYY')
                . "\n- Fecha de fin de la suspensión: " . $fechaFinSuspension->isoFormat('D [de] MMMM [de] YYYY') . " (ambos días incluidos)";
        }

        // Preparar texto sobre no respuesta del trabajador
        $textoNoRespondio = '';
        if ($trabajadorNoRespondio) {
            $textoNoRespondio = "\n\nNOTA IMPORTANTE: El trabajador NO respondió al formulario de descargos. Se le envió la citación a descargos y se le dio la oportunidad de presentar su versión de los hechos, pero no ejerció su derecho de defensa dentro del plazo establecido. Esta circunstancia debe mencionarse explícitamente en la sección 3 del documento.";
        }

        return <<<PROMPT
Genera un documento oficial de {$nombreSancion} para un trabajador en Colombia usando formato profesional estilo Word.

INFORMACIÓN DEL CASO:
- Empresa: {$empresa->nombre_completo} (NIT: {$empresa->nit})
- Representante: {$empresa->representante_legal}
- Trabajador: {$trabajador->nombre_completo} ({$trabajador->tipo_documento} {$trabajador->numero_documento})
- Cargo: {$trabajador->cargo}
- Fecha: {$fechaActual->isoFormat('D [de] MMMM [de] YYYY')}
- Proceso: {$proceso->codigo}

HECHOS:
{$hechosTexto}

SANCIONES DEL REGLAMENTO INTERNO INCUMPLIDAS:
{$sancionesLaborales}

DESCARGOS DEL TRABAJADOR:
{$contextoDescargos}{$textoNoRespondio}
{$bloqueDivergencia}
INSTRUCCIONES DE REDACCIÓN (LENGUAJE CLARO):
- Oraciones cortas (máximo 25 palabras)
- Voz activa ("decidimos" no "fue decidido")
- Palabras simples (evita jerga legal)
- Habla directo al trabajador ("usted")
- Sin frases como "por medio de la presente"

CÓMO MOTIVAR LA DECISIÓN (OBLIGATORIO):
El documento debe estar motivado con el mismo rigor con que un juez sustenta
un fallo, pero escrito para que lo entienda alguien que no sabe de derecho.
Eso significa recorrer, de forma explícita y en este orden:
 a) QUÉ REGLA DEBÍA CUMPLIR: enuncia la conducta del reglamento que aplica al
    caso, tomada de "SANCIONES DEL REGLAMENTO INTERNO INCUMPLIDAS". Explica con
    tus palabras qué exige esa regla en la práctica.
 b) QUÉ HIZO EXACTAMENTE: describe la conducta concreta del trabajador con los
    datos verificables del caso (qué pasó, cuándo, con qué consecuencia).
 c) POR QUÉ LO QUE HIZO INCUMPLE ESA REGLA: conecta de forma expresa el hecho
    con la regla. Esta conexión es el corazón de la motivación y NO puede
    faltar ni quedar implícita. No basta decir "incumplió el reglamento".
 d) QUÉ DIJO EN SU DEFENSA Y POR QUÉ NO CAMBIA (O SÍ MATIZA) LA DECISIÓN:
    responde a los argumentos concretos que dio en sus descargos. Si no
    respondió, dilo y deja constancia de que se le dio la oportunidad.
 e) POR QUÉ ESTA MEDIDA Y NO OTRA MÁS FUERTE O MÁS SUAVE: explica la
    proporcionalidad (gravedad de la falta, si hubo reincidencia, antecedentes,
    perjuicio causado). Si arriba aparece "DECISIÓN DISTINTA A LA RECOMENDADA
    POR EL ANÁLISIS - RAZÓN DE LA EMPRESA", esa razón es el fundamento REAL de
    la medida: úsala como eje de este punto. Está PROHIBIDO afirmar lo
    contrario de lo que dice esa razón (por ejemplo, sostener que no hay
    antecedentes cuando la empresa afirma que sí los hubo) e igualmente
    prohibido inventar atenuantes o agravantes de relleno para justificar la
    medida.

PROHIBICIÓN ABSOLUTA: no cites números de artículo, leyes ni cláusulas que no
aparezcan textualmente en los datos entregados arriba. Si no tienes el número
exacto, describe la obligación incumplida con palabras, sin inventar la cita.
No afirmes hechos que no estén en HECHOS o en DESCARGOS.

FORMATO REQUERIDO:
- Fuente: Calibri 11pt
- Texto justificado
- Interlineado 1.2 (compacto)
- Estilo profesional tipo documento Word
- Solo texto en negro
- NO USAR EMOJIS EN NINGUNA PARTE DEL DOCUMENTO

ESTRUCTURA DEL DOCUMENTO:
Genera HTML con exactamente esta estructura:

<div style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; line-height: 1.2; text-align: justify; color: #000000;">

  <div style="text-align: center; margin-bottom: 15px;">
    <h1 style="font-family: Calibri, Arial, sans-serif; font-size: 14pt; font-weight: bold; margin: 5px 0; color: #000000;">{$empresa->nombre_completo}</h1>
    <p style="font-size: 11pt; margin: 2px 0;">NIT: {$empresa->nit}</p>
    <h2 style="font-family: Calibri, Arial, sans-serif; font-size: 12pt; font-weight: bold; margin: 8px 0; color: #000000; text-transform: uppercase;">{$nombreSancion}</h2>
    <p style="font-size: 11pt; margin: 2px 0;">{$fechaActual->isoFormat('D [de] MMMM [de] YYYY')}</p>
    <p style="font-size: 11pt; margin: 2px 0;">Proceso: {$proceso->codigo}</p>
  </div>

  <div style="margin: 10px 0;">
    <p style="margin: 2px 0;"><strong>Señor(a):</strong> {$trabajador->nombre_completo}</p>
    <p style="margin: 2px 0;"><strong>Cargo:</strong> {$trabajador->cargo}</p>
    <p style="margin: 2px 0;"><strong>Presente</strong></p>
  </div>

  <p style="margin: 8px 0;"><strong>Asunto:</strong> Notificación de {$nombreSancion}</p>

  <p style="margin: 6px 0;">Estimado(a) {$trabajador->nombre_completo}:</p>

  <p style="margin: 6px 0;">Le escribimos para informarle sobre una decisión importante relacionada con su trabajo en {$empresa->nombre_completo}.</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">1. Hechos que motivaron esta decisión</h3>
  <p style="margin: 4px 0;">[Describe los hechos claramente mencionando fechas específicas y acciones concretas. Usa 2-3 oraciones.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">2. Qué regla debía cumplir y por qué lo que ocurrió la incumple</h3>
  <p style="margin: 4px 0;">[Punto (a) de la motivación: enuncia la regla del reglamento que aplica, tomada de las sanciones listadas, y explica con palabras sencillas qué exige en la práctica.]</p>
  <p style="margin: 4px 0;">[Punto (c) de la motivación - EL MÁS IMPORTANTE: conecta de forma expresa el hecho concreto con esa regla. Di literalmente qué parte de la conducta del trabajador incumple qué parte de la obligación, y por qué. No basta afirmar que "incumplió el reglamento": hay que demostrarlo paso a paso, como lo haría un juez, pero en lenguaje llano.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">3. Sus descargos y cómo los valoramos</h3>
  <p style="margin: 4px 0;">[Si el trabajador respondió: resume sus argumentos concretos. Si NO respondió: indica claramente que se le envió la citación a descargos y se le brindó la oportunidad de presentar su versión dentro del plazo legal, pero no ejerció su derecho de defensa. Aclara que, no obstante, se garantizó plenamente su derecho al debido proceso.]</p>
  <p style="margin: 4px 0;">[Punto (d) de la motivación: responde uno por uno a los argumentos que dio. Di cuáles se aceptan y cuáles no, y POR QUÉ. Si sus explicaciones no desvirtúan los hechos, explica la razón; si atenúan la falta, dilo expresamente y refléjalo en la medida. Si no respondió, indica que la decisión se toma con la prueba disponible.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">4. Nuestra decisión y por qué esta medida</h3>
  <p style="margin: 4px 0;">Después de analizar cuidadosamente toda la información, hemos decidido aplicar un {$nombreSancion}.</p>
  <p style="margin: 4px 0;">[Punto (e) de la motivación: explica por qué se eligió ESTA medida y no una más fuerte ni más suave. Menciona lo que se tuvo en cuenta: gravedad de la falta, si hubo reincidencia, antecedentes del trabajador y el perjuicio causado. El trabajador debe entender que la medida es proporcional y no arbitraria.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">5. Qué significa esto para usted</h3>
  <p style="margin: 4px 0;">[Explica las consecuencias prácticas de forma clara y específica.{$textoSuspension}
  Si arriba aparecen fechas de inicio y fin de la suspensión, ÚSALAS EXACTAMENTE como están escritas - son las fechas reales, no un ejemplo. NUNCA escribas "[Día]", "[Mes]" ni ningún otro corchete: si no tienes una fecha exacta para algo, redacta esa frase en términos generales sin inventar el dato.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">6. Base legal</h3>
  <p style="margin: 4px 0;">Esta decisión se fundamenta en el Código Sustantivo del Trabajo de Colombia, el reglamento interno de trabajo de la empresa y las normas establecidas en su contrato laboral.</p>

  <p style="margin: 4px 0;"><strong>Sanciones del reglamento incumplidas:</strong></p>
  <p style="margin: 4px 0;">[Separar cada sanción por su propio párrafo, explicando en lenguaje claro qué significan.{$sancionesLaborales}]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">7. Sus derechos de impugnación</h3>
  <p style="margin: 4px 0;">Si no está de acuerdo con esta decisión, usted tiene derecho a presentar una impugnación. Esto significa que puede solicitar una nueva revisión de su caso. Cuenta con {$diasImpugnacion} días hábiles a partir de la fecha de esta notificación para ejercer este derecho.</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 12px 0 4px 0; color: #000000;">Artículo 20. Tabla de sanciones</h3>
  {$tablaSanciones}

  <p style="margin: 8px 0;">Si tiene preguntas sobre esta comunicación, puede contactarnos.</p>

  <div style="margin-top: 30px;">
    <p style="margin: 2px 0;">Cordialmente,</p>
    <p style="margin-top: 25px; margin-bottom: 2px;"><strong>{$empresa->representante_legal}</strong></p>
    <p style="margin: 2px 0;">Representante Legal</p>
    <p style="margin: 2px 0;">{$empresa->nombre_completo}</p>
    <p style="margin: 2px 0;">NIT: {$empresa->nit}</p>
  </div>

</div>

IMPORTANTE:
- Completa TODAS las secciones [entre corchetes] con contenido específico basado en HECHOS y DESCARGOS
- La sección 2 debe dejar explícito QUÉ incumplió y POR QUÉ ese hecho incumple esa
  regla concreta. Un documento que solo diga "incumplió el reglamento", sin
  demostrar la conexión, está mal redactado y no sirve como motivación.
- Nunca uses corchetes ni marcadores de relleno en el texto final: el documento se
  entrega tal cual al trabajador.
- Mantén el formato exacto (Calibri 11pt, texto justificado, negro, interlineado compacto)
- NO incluyas bloques de código markdown (```html)
- Genera SOLO el HTML mostrado, sin texto adicional
- Sé profesional pero claro y accesible
- NUNCA USES EMOJIS en ninguna parte del documento
- TABLA DE SANCIONES (Artículo 20):
  * En el lugar de la tabla verás el comentario <!--TABLA_SANCIONES-->.
  * DÉJALO EXACTAMENTE ASÍ, en su misma posición, SIN modificarlo, traducirlo ni eliminarlo.
  * NO generes ninguna tabla de sanciones por tu cuenta: el sistema insertará la tabla oficial (textual del RIT/CST) en ese punto.
PROMPT;
    }

    /**
     * Construir prompt para la CONSTANCIA DE CIERRE SIN SANCIÓN.
     *
     * A diferencia del documento de sanción, este escrito documenta la decisión
     * de NO sancionar al trabajador. No impone medidas, no ofrece derechos de
     * impugnación (la decisión favorece al trabajador) y deja constancia del
     * debido proceso garantizado.
     */
    private function construirPromptConstanciaNoSancion(
        ProcesoDisciplinario $proceso,
        $trabajador,
        $empresa,
        string $contextoDescargos,
        bool $trabajadorNoRespondio = false
    ): string {
        $fechaActual = Carbon::now()->locale('es');
        $hechosTexto = strip_tags($proceso->hechos);

        // Justificación registrada por la empresa al apartarse de la
        // recomendación de la IA (si la hubo).
        $razonDivergencia = trim((string) ($proceso->razon_divergencia ?? ''));
        $textoMotivacion = $razonDivergencia !== ''
            ? "MOTIVACIÓN DE LA EMPRESA PARA NO SANCIONAR:\n{$razonDivergencia}\n"
            : "MOTIVACIÓN DE LA EMPRESA PARA NO SANCIONAR:\nLa empresa, tras valorar los hechos y los descargos, concluyó que no existe mérito para imponer una sanción.\n";

        // Nota sobre la no respuesta del trabajador (se garantizó el debido proceso igual).
        $textoNoRespondio = '';
        if ($trabajadorNoRespondio) {
            $textoNoRespondio = "\n\nNOTA: El trabajador no respondió al formulario de descargos. Aun así, se le garantizó plenamente el derecho de defensa al citarlo a descargos y darle la oportunidad de presentar su versión dentro del plazo. Menciónalo en la sección 3.";
        }

        return <<<PROMPT
Genera un documento oficial de CONSTANCIA DE CIERRE DE PROCESO DISCIPLINARIO SIN SANCIÓN para un trabajador en Colombia, usando formato profesional estilo Word.

CONTEXTO IMPORTANTE: La empresa decidió NO imponer ninguna sanción al trabajador. Este documento NO sanciona, NO amonesta y NO impone consecuencias. Es una comunicación que cierra el proceso a favor del trabajador y deja constancia de que se respetó el debido proceso.

INFORMACIÓN DEL CASO:
- Empresa: {$empresa->nombre_completo} (NIT: {$empresa->nit})
- Representante: {$empresa->representante_legal}
- Trabajador: {$trabajador->nombre_completo} ({$trabajador->tipo_documento} {$trabajador->numero_documento})
- Cargo: {$trabajador->cargo}
- Fecha: {$fechaActual->isoFormat('D [de] MMMM [de] YYYY')}
- Proceso: {$proceso->codigo}

HECHOS QUE SE ANALIZARON:
{$hechosTexto}

DESCARGOS DEL TRABAJADOR:
{$contextoDescargos}{$textoNoRespondio}

{$textoMotivacion}

INSTRUCCIONES DE REDACCIÓN (LENGUAJE CLARO):
- Oraciones cortas (máximo 25 palabras)
- Voz activa ("decidimos" no "fue decidido")
- Palabras simples (evita jerga legal)
- Habla directo al trabajador ("usted")
- Tono respetuoso y neutral; NO uses lenguaje acusatorio ni punitivo
- Sin frases como "por medio de la presente"

FORMATO REQUERIDO:
- Fuente: Calibri 11pt
- Texto justificado
- Interlineado 1.2 (compacto)
- Estilo profesional tipo documento Word
- Solo texto en negro
- NO USAR EMOJIS EN NINGUNA PARTE DEL DOCUMENTO

ESTRUCTURA DEL DOCUMENTO:
Genera HTML con exactamente esta estructura:

<div style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; line-height: 1.2; text-align: justify; color: #000000;">

  <div style="text-align: center; margin-bottom: 15px;">
    <h1 style="font-family: Calibri, Arial, sans-serif; font-size: 14pt; font-weight: bold; margin: 5px 0; color: #000000;">{$empresa->nombre_completo}</h1>
    <p style="font-size: 11pt; margin: 2px 0;">NIT: {$empresa->nit}</p>
    <h2 style="font-family: Calibri, Arial, sans-serif; font-size: 12pt; font-weight: bold; margin: 8px 0; color: #000000; text-transform: uppercase;">Constancia de Cierre de Proceso Disciplinario Sin Sanción</h2>
    <p style="font-size: 11pt; margin: 2px 0;">{$fechaActual->isoFormat('D [de] MMMM [de] YYYY')}</p>
    <p style="font-size: 11pt; margin: 2px 0;">Proceso: {$proceso->codigo}</p>
  </div>

  <div style="margin: 10px 0;">
    <p style="margin: 2px 0;"><strong>Señor(a):</strong> {$trabajador->nombre_completo}</p>
    <p style="margin: 2px 0;"><strong>Cargo:</strong> {$trabajador->cargo}</p>
    <p style="margin: 2px 0;"><strong>Presente</strong></p>
  </div>

  <p style="margin: 8px 0;"><strong>Asunto:</strong> Cierre del proceso disciplinario sin sanción</p>

  <p style="margin: 6px 0;">Estimado(a) {$trabajador->nombre_completo}:</p>

  <p style="margin: 6px 0;">Le escribimos para informarle el resultado del proceso disciplinario {$proceso->codigo}. La empresa decidió no aplicarle ninguna sanción.</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">1. Hechos que se analizaron</h3>
  <p style="margin: 4px 0;">[Describe brevemente los hechos que dieron origen al proceso, de forma neutral, sin atribuir culpa. Usa 2-3 oraciones.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">2. Sus descargos</h3>
  <p style="margin: 4px 0;">[Si el trabajador respondió: resume sus descargos reconociendo su versión. Si NO respondió: indica que se le citó a descargos y se le dio la oportunidad de presentar su versión dentro del plazo, garantizando su derecho de defensa.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">3. Nuestra decisión</h3>
  <p style="margin: 4px 0;">[Explica de forma clara que, tras analizar los hechos y los descargos, la empresa decidió NO imponer ninguna sanción. Desarrolla las razones a partir de la MOTIVACIÓN DE LA EMPRESA indicada arriba. Mantén un tono respetuoso.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">4. Qué significa esto para usted</h3>
  <p style="margin: 4px 0;">Este proceso queda cerrado. No se registra ninguna sanción en su contra y su situación laboral continúa normalmente. Esta decisión no afecta su hoja de vida laboral.</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">5. Debido proceso</h3>
  <p style="margin: 4px 0;">Dejamos constancia de que este proceso se adelantó respetando su derecho de defensa y el debido proceso, conforme al Código Sustantivo del Trabajo y al reglamento interno de trabajo.</p>

  <p style="margin: 8px 0;">Si tiene preguntas sobre esta comunicación, puede contactarnos.</p>

  <div style="margin-top: 30px;">
    <p style="margin: 2px 0;">Cordialmente,</p>
    <p style="margin-top: 25px; margin-bottom: 2px;"><strong>{$empresa->representante_legal}</strong></p>
    <p style="margin: 2px 0;">Representante Legal</p>
    <p style="margin: 2px 0;">{$empresa->nombre_completo}</p>
    <p style="margin: 2px 0;">NIT: {$empresa->nit}</p>
  </div>

</div>

IMPORTANTE:
- Completa TODAS las secciones [entre corchetes] con contenido específico basado en HECHOS, DESCARGOS y la MOTIVACIÓN DE LA EMPRESA
- NO incluyas ninguna sección de "derechos de impugnación", "sanción", "consecuencias" ni "tabla de sanciones": este documento NO sanciona
- Mantén el formato exacto (Calibri 11pt, texto justificado, negro, interlineado compacto)
- NO incluyas bloques de código markdown (```html)
- Genera SOLO el HTML mostrado, sin texto adicional
- Sé profesional, claro y respetuoso
- NUNCA USES EMOJIS en ninguna parte del documento
PROMPT;
    }

    /**
     * Construir la tabla de sanciones (Artículo 20) para incluir en el prompt
     */
    /**
     * Inserta la tabla de sanciones determinística donde quedó el marcador,
     * o tras el encabezado "Artículo 20. Tabla de sanciones" si la IA lo quitó.
     */
    private function inyectarTablaSanciones(string $html, ProcesoDisciplinario $proceso, $empresa): string
    {
        $tabla = $this->construirTablaSancionesDeterministica($proceso, $empresa);

        if (str_contains($html, '<!--TABLA_SANCIONES-->')) {
            return str_replace('<!--TABLA_SANCIONES-->', $tabla, $html);
        }
        if (preg_match('/Art[íi]culo\s*20\.?\s*Tabla de sanciones.*?<\/h3>/is', $html, $m)) {
            return str_replace($m[0], $m[0] . $tabla, $html);
        }
        return $html . $tabla; // último recurso: anexar al final
    }

    /**
     * Inyecta el membrete de empresa en el documento de sanción. A
     * diferencia del contrato (vistas Blade fijas) y la citación (HTML
     * armado a mano en PHP), acá el `</body>` puede venir del propio texto
     * de la IA, no siempre de envolverEnHTMLCompleto() - mismo motivo por
     * el que inyectarTablaSanciones() de arriba tampoco confía ciegamente
     * en un `</body>`. Si no se encuentra ninguno, se anexa al final.
     */
    private function inyectarMembreteSancion(string $html, $empresa): string
    {
        $membrete = view('pdfs.components.membrete-empresa', ['empresa' => $empresa])->render();

        if (trim($membrete) === '') {
            return $html;
        }

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $membrete . '</body>', $html);
        }

        return $html . $membrete;
    }

    /**
     * Filas de la tabla de sanciones SOLO para las conductas del incidente
     * concreto de este proceso - NO el catálogo completo de faltas
     * leves/graves/muy graves del RIT. Antes tanto la citación como el
     * documento de sanción mostraban el RIT entero vía
     * ReglamentoInternoService::filasTablaSanciones($rit); el usuario aclaró
     * que la tabla debe corresponder al incidente real del trabajador, no al
     * catálogo. Se toma de ProcesoDisciplinario::motivosDescargosNormalizados()
     * (las conductas que YA se seleccionaron para este proceso - misma fuente
     * que sanciones_laborales_texto en el resto del sistema).
     *
     * @return array<int, array{gravedad:string, conductas:array<string>, sancion:string}>
     */
    private function filasTablaSancionesDelIncidente(ProcesoDisciplinario $proceso): array
    {
        $motivos = $proceso->motivosDescargosNormalizados();
        if (empty($motivos)) {
            return [];
        }

        return $this->agruparMotivosPorGravedad($motivos);
    }

    /**
     * Agrupa un arreglo de motivos (forma de motivosDescargosNormalizados()/
     * motivosDescargosDesdeClasificacionIA()) en filas de tabla por gravedad.
     */
    private function agruparMotivosPorGravedad(array $motivos): array
    {
        $etiquetaGravedad = ['leve' => 'Leve', 'grave' => 'Grave', 'muy_grave' => 'Muy grave'];
        $grupos = ['leve' => ['c' => [], 's' => []], 'grave' => ['c' => [], 's' => []], 'muy_grave' => ['c' => [], 's' => []]];

        foreach ($motivos as $motivo) {
            $g = $motivo['gravedad'] ?? 'grave';
            if (!isset($grupos[$g])) continue;
            if (!empty($motivo['nombre'])) $grupos[$g]['c'][] = $motivo['nombre'];
            if (!empty($motivo['medida'])) $grupos[$g]['s'][] = $motivo['medida'];
        }

        $filas = [];
        foreach (['leve', 'grave', 'muy_grave'] as $g) {
            if (empty($grupos[$g]['c'])) continue;
            $filas[] = [
                'gravedad'  => $etiquetaGravedad[$g],
                'conductas' => array_values(array_unique($grupos[$g]['c'])),
                'sancion'   => implode(' / ', array_values(array_unique(array_filter($grupos[$g]['s'])))),
            ];
        }

        return $filas;
    }

    /**
     * Filas de la tabla de sanciones con RESPALDO: intenta primero SOLO la(s)
     * conducta(s) del incidente concreto (filasTablaSancionesDelIncidente); si
     * viene vacío - el proceso no tiene sanciones_laborales_ids en el formato
     * esperado, o no coincide con conductasSancionablesDeEmpresa() - cae al
     * catálogo completo del RIT (ReglamentoInternoService::filasTablaSanciones).
     * Un caso real (empresa con RIT subido) mostró la tabla completamente
     * vacía tras filtrar solo por incidente - mejor mostrar el catálogo
     * completo que no mostrar nada en un documento legal.
     */
    private function filasTablaSancionesConFallback(ProcesoDisciplinario $proceso, $empresa): array
    {
        $filas = $this->filasTablaSancionesDelIncidente($proceso);
        if (!empty($filas)) {
            return $filas;
        }

        // Respaldo intermedio (bug real reportado: la tabla mostraba TODAS las
        // faltas leves/graves/muy graves del RIT sin relación con el incidente,
        // porque sanciones_laborales_ids no venía poblado - ej. proceso editado
        // sin pasar por "Motivo de los descargos"). Antes de rendirse al
        // catálogo completo, se usa la clasificación de la IA
        // (clasificacion_incidente_ia.conducta_rit_aplicable), que sí analizó
        // los hechos reales de este proceso + el RIT completo.
        $filasIA = $this->agruparMotivosPorGravedad($proceso->motivosDescargosDesdeClasificacionIA());
        if (!empty($filasIA)) {
            return $filasIA;
        }

        $rit = $empresa->reglamentoInterno;
        if (!$rit) {
            return [];
        }

        try {
            return app(\App\Services\ReglamentoInternoService::class)->filasTablaSanciones($rit);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Construye la TABLA DE SANCIONES de forma DETERMINÍSTICA (verbatim), sin IA.
     * Las faltas salen de las conductas del incidente concreto de este proceso
     * si están disponibles, o del catálogo completo del RIT como respaldo (ver
     * filasTablaSancionesConFallback) para no dejar la tabla vacía. Un "otro
     * motivo" no tipificado se ancla a un artículo del CST citado textualmente
     * (nunca se inventa la sanción).
     */
    private function construirTablaSancionesDeterministica(ProcesoDisciplinario $proceso, $empresa): string
    {
        $otroMotivo = trim((string) $proceso->otro_motivo_descargos);
        $filasTabla = '';

        // Igual que en generarHTMLCitacionDescargos(): si la empresa no tiene
        // RIT, las filas de esta tabla vienen del catálogo genérico de
        // respaldo del CST, nunca de un reglamento real - el texto no puede
        // decir "este Reglamento" en ese caso.
        $tieneRIT = $empresa->reglamentoInterno !== null;

        // ── Conductas del incidente por gravedad EXACTA (leve/grave/muy grave) ───
        try {
            $filas = $this->filasTablaSancionesConFallback($proceso, $empresa);
        } catch (\Throwable $e) {
            $filas = [];
        }
        foreach ($filas as $fila) {
            $grav    = e($fila['gravedad']);
            $items   = implode('', array_map(fn($f) => '<li>' . e($f) . '</li>', $fila['conductas']));
            $sancion = e($fila['sancion']);
            $filasTabla .= <<<HTML
    <tr style="page-break-inside: avoid;">
      <td style="border: 1px solid #000; padding: 4px 6px; text-align: center; font-weight: bold;">{$grav}</td>
      <td style="border: 1px solid #000; padding: 4px 6px;"><ul style="margin:0;padding-left:16px;">{$items}</ul></td>
      <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{$sancion}</td>
    </tr>
HTML;
        }

        // "Otro motivo" no tipificado en el RIT -> se ancla a un artículo del CST,
        // citado textualmente. NUNCA se inventa la sanción.
        if ($otroMotivo !== '') {
            $articulo = $this->buscarArticuloCstParaTexto($otroMotivo, $empresa->id ?? null);
            $conducta = e($otroMotivo);
            if ($articulo) {
                $ref = e(trim(($articulo->codigo ? $articulo->codigo . ' - ' : '') . ($articulo->titulo ?? '')));
                $extracto = e(\Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', (string) ($articulo->texto_completo ?? $articulo->descripcion ?? ''))), 320));
                $fundamentoCol = "Fundamento legal: <strong>{$ref}</strong>. <em>{$extracto}</em>";
                $tipoCol = 'Conforme al CST';
            } else {
                $fundamentoCol = 'Requiere calificación conforme al RIT/CST. No se identificó un artículo aplicable en la base legal cargada.';
                $tipoCol = 'Por calificar';
            }

            $filasTabla .= <<<HTML
    <tr style="page-break-inside: avoid;">
      <td style="border: 1px solid #000; padding: 4px 6px; text-align: center; font-weight: bold;">{$tipoCol}</td>
      <td style="border: 1px solid #000; padding: 4px 6px;">{$conducta}</td>
      <td style="border: 1px solid #000; padding: 4px 6px;">{$fundamentoCol}</td>
    </tr>
HTML;
        }

        if ($filasTabla === '') {
            $filasTabla = $tieneRIT
                ? '<tr><td colspan="3" style="border: 1px solid #000; padding: 6px; text-align: center;">No se encontró el cuadro de faltas en el Reglamento Interno de Trabajo de esta empresa.</td></tr>'
                : '<tr><td colspan="3" style="border: 1px solid #000; padding: 6px; text-align: center;">Esta empresa no tiene un Reglamento Interno de Trabajo registrado en el sistema.</td></tr>';
        }

        // Construir la tabla completa. Título/empresa van FUERA del <table> (se
        // muestran una sola vez); solo la fila de encabezados va en <thead> para
        // que DomPDF la repita si la tabla se parte entre páginas, y las filas de
        // datos van en <tbody> con page-break-inside:avoid - mismo fix aplicado a
        // generarHTMLCitacionDescargos() para que el borde de una fila partida no
        // se salga del margen inferior de la página.
        $subtituloTabla = $tieneRIT
            ? 'Todas las sanciones contenidas en esta tabla solo se aplicarán previa garantía del debido proceso establecido en este Reglamento, conforme a la Ley 2466 de 2025.'
            : 'Esta empresa no tiene un Reglamento Interno de Trabajo registrado en el sistema. Las sanciones contenidas en esta tabla corresponden al régimen general del Código Sustantivo del Trabajo y solo se aplicarán previa garantía del debido proceso, conforme a la Ley 2466 de 2025.';

        return <<<HTML
  <div style="border: 1px solid #000; border-bottom: none; padding: 6px; text-align: center; background-color: #f5f5f5; margin: 8px 0 0 0;">
    <strong>TABLA DE SANCIONES LABORALES</strong><br>
    <span style="font-size: 9pt;">({$subtituloTabla})</span>
  </div>
  <p style="border: 1px solid #000; border-top: none; padding: 4px 6px; text-align: center; margin: 0;">
    <strong>{$empresa->nombre_completo}</strong><br>
    NIT: {$empresa->nit}
  </p>
  <table style="width: 100%; border-collapse: collapse; margin: 0 0 4px 0; font-size: 10pt;">
    <thead>
    <tr style="background-color: #e0e0e0;">
      <th style="border: 1px solid #000; padding: 4px 6px; text-align: center; width: 20%;">Tipo de Falta</th>
      <th style="border: 1px solid #000; padding: 4px 6px; text-align: center; width: 55%;">Descripción de la conducta</th>
      <th style="border: 1px solid #000; padding: 4px 6px; text-align: center; width: 25%;">Sanción</th>
    </tr>
    </thead>
    <tbody style="page-break-inside: auto;">
    {$filasTabla}
    </tbody>
  </table>
HTML;
    }

    /**
     * Busca el artículo del CST más relevante para un texto (p. ej. un "otro motivo"
     * no tipificado), por similitud coseno sobre los embeddings de ArticuloLegal.
     * Devuelve el artículo para citarlo TEXTUAL, sin que la IA invente la sanción.
     */
    private function buscarArticuloCstParaTexto(string $texto, ?int $empresaId): ?\App\Models\ArticuloLegal
    {
        try {
            $emb = app(\App\Services\BibliotecaLegalService::class)->embedConsulta($texto);
            if (empty($emb) || !is_array($emb)) {
                return null;
            }

            $articulos = \App\Models\ArticuloLegal::query()
                ->paraEmpresa($empresaId)
                ->activos()
                ->whereNotNull('embedding')
                ->get();

            $mejor = null;
            $mejorScore = 0.40; // umbral mínimo de relevancia
            foreach ($articulos as $a) {
                $e = $a->embedding;
                if (!is_array($e) || count($e) !== count($emb)) {
                    continue;
                }
                $score = $this->cosenoSimilitud($emb, $e);
                if ($score > $mejorScore) {
                    $mejorScore = $score;
                    $mejor = $a;
                }
            }

            return $mejor;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo anclar "otro motivo" al CST', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /** Similitud coseno entre dos vectores de la misma dimensión. */
    private function cosenoSimilitud(array $a, array $b): float
    {
        $dot = 0.0; $na = 0.0; $nb = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] * $a[$i];
            $nb  += $b[$i] * $b[$i];
        }
        if ($na == 0.0 || $nb == 0.0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * Guardar documento de sanción como HTML
     */
    private function guardarDocumentoSancionHTML(string $contenido, string $codigo, string $tipoSancion): string
    {
        $htmlPath = storage_path('app/sanciones/sancion_' . $codigo . '_' . $tipoSancion . '_' . time() . '.html');

        if (!file_exists(storage_path('app/sanciones'))) {
            mkdir(storage_path('app/sanciones'), 0755, true);
        }

        file_put_contents($htmlPath, $contenido);

        return $htmlPath;
    }

    /**
     * Convertir HTML a PDF usando LibreOffice
     */
    private function convertirHTMLaPDF(string $htmlPath, string $codigo, string $tipoSancion): string
    {
        $outputDir = storage_path('app/sanciones');
        $timestamp = time();
        $finalPdfName = 'sancion_' . $codigo . '_' . $tipoSancion . '_' . $timestamp . '.pdf';
        $finalPdfPath = $outputDir . DIRECTORY_SEPARATOR . $finalPdfName;

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Intentar con LibreOffice primero
        if ($this->isLibreOfficeAvailable()) {
            $baseName = pathinfo($htmlPath, PATHINFO_FILENAME);
            $expectedPdf = $outputDir . DIRECTORY_SEPARATOR . $baseName . '.pdf';

            $command = sprintf(
                '"%s" --headless --nofirststartwizard --nodefault --nolockcheck --nologo --norestore --convert-to pdf --outdir %s %s 2>&1',
                $this->libreOfficePath,
                escapeshellarg($outputDir),
                escapeshellarg($htmlPath)
            );

            Log::info('Ejecutando LibreOffice para HTML a PDF', [
                'command' => $command,
                'input' => $htmlPath,
                'outputDir' => $outputDir
            ]);

            \exec($command, $output, $return);

            Log::info('Resultado LibreOffice HTML a PDF', [
                'return_code' => $return,
                'output' => $output,
                'expected_pdf' => $expectedPdf
            ]);

            if ($return === 0 && file_exists($expectedPdf)) {
                // Renombrar al nombre final deseado
                if (rename($expectedPdf, $finalPdfPath)) {
                    // Eliminar el archivo HTML temporal
                    if (file_exists($htmlPath)) {
                        unlink($htmlPath);
                    }
                    Log::info('PDF generado exitosamente desde HTML', ['path' => $finalPdfPath]);
                    return $finalPdfPath;
                }
                // Si no se pudo renombrar, usar el nombre que generó LibreOffice
                if (file_exists($htmlPath)) {
                    unlink($htmlPath);
                }
                return $expectedPdf;
            }

            Log::warning('LibreOffice no pudo convertir HTML a PDF', [
                'return_code' => $return,
                'output' => $output
            ]);
        }

        // Si LibreOffice falla, usar Dompdf como fallback
        Log::info('Usando Dompdf como fallback para conversión HTML a PDF');
        try {
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');

            $dompdf = new Dompdf($options);
            $html = file_get_contents($htmlPath);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();

            file_put_contents($finalPdfPath, $dompdf->output());

            // Eliminar el archivo HTML temporal
            if (file_exists($htmlPath)) {
                unlink($htmlPath);
            }

            Log::info('PDF generado con Dompdf (fallback)', ['path' => $finalPdfPath]);
            return $finalPdfPath;
        } catch (\Exception $e) {
            // Si todo falla, devolver el HTML
            Log::error('Error al convertir HTML a PDF con Dompdf', [
                'error' => $e->getMessage(),
            ]);
            return $htmlPath;
        }
    }

    /**
     * Enviar documento de sanción por correo
     */
    public function enviarSancionPorEmail(ProcesoDisciplinario $proceso, string $documentoPath, string $tipoSancion): void
    {
        $trabajador = $proceso->trabajador;
        $empresa = $proceso->empresa;

        if (empty($trabajador->email)) {
            throw new \Exception('El trabajador no tiene correo electrónico registrado');
        }

        // Crear registro de tracking para el correo (hora de Colombia)
        $tracking = EmailTracking::create([
            'token' => EmailTracking::generarToken(),
            'tipo_documento' => 'sancion',
            'proceso_id' => $proceso->id,
            'trabajador_id' => $trabajador->id,
            'email_destinatario' => $trabajador->email,
            'enviado_en' => Carbon::now('America/Bogota'),
        ]);

        $extension = pathinfo($documentoPath, PATHINFO_EXTENSION);
        $mimeType = $extension === 'pdf' ? 'application/pdf' : 'text/html';
        $nombreSancion = match ($tipoSancion) {
            'llamado_atencion' => 'Llamado de Atención',
            'suspension' => 'Suspensión',
            'terminacion' => 'Terminación de Contrato',
            'no_sancion' => 'Sin Sanción',
            default => 'Sanción',
        };
        $nombreArchivo = 'Sancion_' . $nombreSancion . '_' . $proceso->codigo . '.' . $extension;

        Mail::send('emails.sancion-notificacion', [
            'proceso' => $proceso,
            'trabajador' => $trabajador,
            'empresa' => $empresa,
            'tipoSancion' => $nombreSancion,
            'trackingToken' => $tracking->token,
        ], function ($message) use ($trabajador, $proceso, $documentoPath, $nombreArchivo, $mimeType, $nombreSancion) {
            $message->to($trabajador->email, $trabajador->nombre_completo)
                ->subject('Notificación de ' . $nombreSancion . ' - Proceso ' . $proceso->codigo)
                ->attach($documentoPath, [
                    'as' => $nombreArchivo,
                    'mime' => $mimeType,
                ]);
        });

        Log::info('Sanción enviada con tracking', [
            'proceso_id' => $proceso->id,
            'tipo_sancion' => $tipoSancion,
            'trabajador_email' => $trabajador->email,
            'tracking_token' => substr($tracking->token, 0, 10) . '...',
        ]);
    }

    /**
     * Enviar la constancia de cierre SIN SANCIÓN por correo al trabajador.
     * Usa una plantilla neutral (no punitiva) distinta a la de sanciones.
     */
    public function enviarConstanciaNoSancionPorEmail(ProcesoDisciplinario $proceso, string $documentoPath): void
    {
        $trabajador = $proceso->trabajador;
        $empresa = $proceso->empresa;

        if (empty($trabajador->email)) {
            throw new \Exception('El trabajador no tiene correo electrónico registrado');
        }

        // Registro de tracking del correo (hora de Colombia)
        $tracking = EmailTracking::create([
            'token' => EmailTracking::generarToken(),
            'tipo_documento' => 'sancion',
            'proceso_id' => $proceso->id,
            'trabajador_id' => $trabajador->id,
            'email_destinatario' => $trabajador->email,
            'enviado_en' => Carbon::now('America/Bogota'),
        ]);

        $extension = pathinfo($documentoPath, PATHINFO_EXTENSION);
        $mimeType = $extension === 'pdf' ? 'application/pdf' : 'text/html';
        $nombreArchivo = 'Constancia_No_Sancion_' . $proceso->codigo . '.' . $extension;

        Mail::send('emails.constancia-no-sancion', [
            'proceso' => $proceso,
            'trabajador' => $trabajador,
            'empresa' => $empresa,
            'trackingToken' => $tracking->token,
        ], function ($message) use ($trabajador, $proceso, $documentoPath, $nombreArchivo, $mimeType) {
            $message->to($trabajador->email, $trabajador->nombre_completo)
                ->subject('Cierre de proceso disciplinario sin sanción - Proceso ' . $proceso->codigo)
                ->attach($documentoPath, [
                    'as' => $nombreArchivo,
                    'mime' => $mimeType,
                ]);
        });

        Log::info('Constancia de no sanción enviada con tracking', [
            'proceso_id' => $proceso->id,
            'trabajador_email' => $trabajador->email,
            'tracking_token' => substr($tracking->token, 0, 10) . '...',
        ]);
    }

    /**
     * Generar y enviar sanción (proceso completo)
     */
    public function generarYEnviarSancion(ProcesoDisciplinario $proceso, string $tipoSancion): array
    {
        // Usar transacción para garantizar atomicidad
        return \Illuminate\Support\Facades\DB::transaction(function () use ($proceso, $tipoSancion) {
            try {
                // Generar el documento con IA
                $resultado = $this->generarDocumentoSancion($proceso, $tipoSancion);

                if (!$resultado['success']) {
                    throw new \Exception($resultado['message'] ?? 'Error al generar documento de sanción');
                }

                $documentoPath = $resultado['documento_path'];

                // Guardar documento en la tabla de documentos
                $extension = pathinfo($documentoPath, PATHINFO_EXTENSION);
                $documento = \App\Models\Documento::create([
                    'documentable_type' => ProcesoDisciplinario::class,
                    'documentable_id' => $proceso->id,
                    'tipo_documento' => 'sancion',
                    'nombre_archivo' => 'Sancion_' . $tipoSancion . '_' . $proceso->codigo . '.' . $extension,
                    'ruta_archivo' => $documentoPath,
                    'formato' => $extension,
                    'generado_por' => auth()->id() ?? 1,
                    'version' => 1,
                    'fecha_generacion' => now(),
                ]);

                // Crear o actualizar la sanción en la base de datos
                $sancion = \App\Models\Sancion::updateOrCreate(
                    ['proceso_id' => $proceso->id],
                    [
                        'tipo_sancion' => $tipoSancion,
                        'motivo_sancion' => strip_tags($proceso->hechos),
                        // COMENTADO: Artículos legales - Ahora se usan Sanciones Laborales
                        // 'fundamento_legal' => $proceso->articulos_legales_texto,
                        'fundamento_legal' => $proceso->sanciones_laborales_texto,
                        'documento_generado' => true,
                        'ruta_documento' => $documentoPath,
                        'fecha_notificacion_trabajador' => now(),
                        'notificado_por' => auth()->id(),
                    ]
                );

                // Enviar por email (si falla, se hace rollback de todo)
                $this->enviarSancionPorEmail($proceso, $documentoPath, $tipoSancion);

                // SOLO AQUÍ actualizamos el estado del proceso (después de que todo lo anterior fue exitoso)
                $proceso->tipo_sancion = $tipoSancion;
                $proceso->decision_sancion = true;
                $proceso->fecha_notificacion = now();
                $proceso->estado = 'sancion_emitida';
                $proceso->save();

                // Registrar en el timeline
                $timelineService = app(TimelineService::class);

                $timelineService->registrarDocumentoGenerado(
                    procesoTipo: 'proceso_disciplinario',
                    procesoId: $proceso->id,
                    tipoDocumento: 'Sanción',
                    nombreArchivo: basename($documentoPath)
                );

                $timelineService->registrarNotificacion(
                    procesoTipo: 'proceso_disciplinario',
                    procesoId: $proceso->id,
                    tipoNotificacion: 'Sanción emitida',
                    destinatario: $proceso->trabajador->email
                );

                return [
                    'success' => true,
                    'message' => 'Sanción generada y enviada exitosamente',
                    'documento_path' => $documentoPath,
                    'sancion_id' => $sancion->id,
                ];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error al generar y enviar sanción', [
                    'proceso_id' => $proceso->id,
                    'tipo_sancion' => $tipoSancion,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // La transacción hará rollback automáticamente al lanzar la excepción
                throw $e;
            }
        });
    }

    /**
     * Generar la constancia de cierre SIN SANCIÓN, enviarla al trabajador y
     * cerrar el proceso. Todo ocurre dentro de una transacción: si algo falla
     * (generación, guardado o envío), se hace rollback y el proceso conserva su
     * estado original, evitando que quede "cerrado" sin documento ni correo.
     */
    public function generarYEnviarConstanciaNoSancion(ProcesoDisciplinario $proceso): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($proceso) {
            try {
                // Generar el documento (constancia) con IA
                $resultado = $this->generarDocumentoSancion($proceso, 'no_sancion');

                if (!$resultado['success']) {
                    throw new \Exception($resultado['message'] ?? 'Error al generar la constancia de no sanción');
                }

                $documentoPath = $resultado['documento_path'];
                $extension = pathinfo($documentoPath, PATHINFO_EXTENSION);

                // Guardar el documento. Se usa tipo 'sancion' para que la acción
                // "Ver Sanción / Ver Constancia" lo encuentre sin nuevas migraciones.
                \App\Models\Documento::create([
                    'documentable_type' => ProcesoDisciplinario::class,
                    'documentable_id' => $proceso->id,
                    'tipo_documento' => 'sancion',
                    'nombre_archivo' => 'Constancia_No_Sancion_' . $proceso->codigo . '.' . $extension,
                    'ruta_archivo' => $documentoPath,
                    'formato' => $extension,
                    'generado_por' => auth()->id() ?? 1,
                    'version' => 1,
                    'fecha_generacion' => now(),
                ]);

                // NO se crea registro en `sanciones`: no hubo sanción (y su columna
                // tipo_sancion es un ENUM que no admite 'no_sancion').

                // Enviar la constancia por email (si falla, rollback de todo)
                $this->enviarConstanciaNoSancionPorEmail($proceso, $documentoPath);

                // Cerrar el proceso solo después de que todo lo anterior fue exitoso
                $proceso->tipo_sancion = 'no_sancion';
                $proceso->decision_sancion = false;
                $proceso->fecha_notificacion = now();
                $proceso->estado = 'cerrado';
                $proceso->save();

                // Registrar en el timeline
                $timelineService = app(TimelineService::class);

                $timelineService->registrarDocumentoGenerado(
                    procesoTipo: 'proceso_disciplinario',
                    procesoId: $proceso->id,
                    tipoDocumento: 'Constancia de no sanción',
                    nombreArchivo: basename($documentoPath)
                );

                $timelineService->registrarNotificacion(
                    procesoTipo: 'proceso_disciplinario',
                    procesoId: $proceso->id,
                    tipoNotificacion: 'Cierre sin sanción',
                    destinatario: $proceso->trabajador->email
                );

                return [
                    'success' => true,
                    'message' => 'Constancia de no sanción generada y enviada exitosamente',
                    'documento_path' => $documentoPath,
                ];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error al generar y enviar constancia de no sanción', [
                    'proceso_id' => $proceso->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // La transacción hará rollback automáticamente al lanzar la excepción
                throw $e;
            }
        });
    }

    /**
     * Enviar notificación de cambio de estado de descargos al trabajador
     */
    public function enviarNotificacionEstadoDescargos(ProcesoDisciplinario $proceso, string $estado, ?string $actaPath = null): void
    {
        $trabajador = $proceso->trabajador;
        $empresa = $proceso->empresa;

        if (empty($trabajador->email)) {
            Log::warning('No se puede enviar notificación de estado: trabajador sin email', [
                'proceso_id' => $proceso->id,
                'estado' => $estado,
            ]);
            return;
        }

        // Crear registro de tracking para el correo
        $tracking = EmailTracking::create([
            'token' => EmailTracking::generarToken(),
            'tipo_documento' => 'estado_descargos',
            'proceso_id' => $proceso->id,
            'trabajador_id' => $trabajador->id,
            'email_destinatario' => $trabajador->email,
            'enviado_en' => Carbon::now('America/Bogota'),
        ]);

        // Determinar texto del estado
        $estadoTexto = match ($estado) {
            'descargos_realizados' => 'Descargos Completados',
            'descargos_no_realizados' => 'Descargos No Realizados',
            default => ucfirst(str_replace('_', ' ', $estado)),
        };

        // Determinar asunto del correo
        $asunto = match ($estado) {
            'descargos_realizados' => 'Confirmación de Recepción de Descargos - Proceso ' . $proceso->codigo,
            'descargos_no_realizados' => 'Notificación de Descargos No Presentados - Proceso ' . $proceso->codigo,
            default => 'Actualización del Proceso Disciplinario ' . $proceso->codigo,
        };

        Mail::send('emails.descargos-estado', [
            'proceso' => $proceso,
            'trabajador' => $trabajador,
            'empresa' => $empresa,
            'estado' => $estado,
            'estadoTexto' => $estadoTexto,
            'trackingToken' => $tracking->token,
        ], function ($message) use ($trabajador, $asunto, $actaPath) {
            $message->to($trabajador->email, $trabajador->nombre_completo)
                ->subject($asunto);

            if ($actaPath && file_exists($actaPath)) {
                $message->attach($actaPath, [
                    'as'   => 'Acta_de_Descargos.pdf',
                    'mime' => 'application/pdf',
                ]);
            }
        });

        Log::info('Notificación de estado de descargos enviada', [
            'proceso_id' => $proceso->id,
            'estado' => $estado,
            'trabajador_email' => $trabajador->email,
            'tracking_token' => substr($tracking->token, 0, 10) . '...',
        ]);
    }

    /**
     * Enviar notificación de estado de descargos al cliente (usuario de la empresa)
     */
    public function enviarNotificacionDescargosAlCliente(ProcesoDisciplinario $proceso, string $estado): void
    {
        $trabajador = $proceso->trabajador;
        $empresa = $proceso->empresa;

        // Obtener usuarios cliente activos de la empresa
        $usuariosCliente = \App\Models\User::where('role', 'cliente')
            ->where('empresa_id', $proceso->empresa_id)
            ->where('active', true)
            ->whereNotNull('email')
            ->get();

        if ($usuariosCliente->isEmpty()) {
            Log::warning('No hay usuarios cliente para notificar sobre descargos', [
                'proceso_id' => $proceso->id,
                'empresa_id' => $proceso->empresa_id,
                'estado' => $estado,
            ]);
            return;
        }

        // Determinar asunto del correo
        $asunto = match ($estado) {
            'descargos_realizados' => 'Trabajador Completó Descargos - Proceso ' . $proceso->codigo,
            'descargos_no_realizados' => 'Trabajador No Presentó Descargos - Proceso ' . $proceso->codigo,
            default => 'Actualización del Proceso Disciplinario ' . $proceso->codigo,
        };

        foreach ($usuariosCliente as $cliente) {
            try {
                // Crear registro de tracking para el correo
                $tracking = EmailTracking::create([
                    'token' => EmailTracking::generarToken(),
                    'tipo_documento' => 'estado_descargos_cliente',
                    'proceso_id' => $proceso->id,
                    'trabajador_id' => $trabajador->id,
                    'email_destinatario' => $cliente->email,
                    'enviado_en' => Carbon::now('America/Bogota'),
                ]);

                Mail::send('emails.descargos-estado-cliente', [
                    'proceso' => $proceso,
                    'trabajador' => $trabajador,
                    'empresa' => $empresa,
                    'cliente' => $cliente,
                    'estado' => $estado,
                    'trackingToken' => $tracking->token,
                ], function ($message) use ($cliente, $asunto) {
                    $message->to($cliente->email, $cliente->name)
                        ->subject($asunto);
                });

                Log::info('Notificación de estado de descargos enviada al cliente', [
                    'proceso_id' => $proceso->id,
                    'estado' => $estado,
                    'cliente_email' => $cliente->email,
                    'cliente_id' => $cliente->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Error al enviar notificación de descargos al cliente', [
                    'proceso_id' => $proceso->id,
                    'cliente_id' => $cliente->id,
                    'cliente_email' => $cliente->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Generar documento de resolución de impugnación
     */
    public function generarDocumentoResolucionImpugnacion(ProcesoDisciplinario $proceso, \App\Models\Impugnacion $impugnacion, ?int $nuevosDiasSuspension = null): string
    {
        $trabajador = $proceso->trabajador;
        $empresa = $proceso->empresa;
        $fechaActual = Carbon::now()->locale('es');

        // Determinar texto de la decisión
        $decisionTexto = match ($impugnacion->decision_final) {
            'confirma_sancion' => 'CONFIRMA LA SANCIÓN',
            'revoca_sancion' => 'REVOCA LA SANCIÓN',
            'modifica_sancion' => 'MODIFICA LA SANCIÓN',
            default => 'RESUELVE',
        };

        // Texto de nueva sanción si aplica
        $nuevaSancionTexto = '';
        if ($impugnacion->decision_final === 'modifica_sancion' && $impugnacion->nueva_sancion_tipo) {
            $nuevaSancionTexto = match ($impugnacion->nueva_sancion_tipo) {
                'llamado_atencion' => 'Llamado de Atención',
                'suspension' => 'Suspensión Laboral' . ($nuevosDiasSuspension ? " de {$nuevosDiasSuspension} día(s)" : ''),
                'terminacion' => 'Terminación de Contrato',
                default => ucfirst(str_replace('_', ' ', $impugnacion->nueva_sancion_tipo)),
            };
        }

        // Sanción original
        $sancionOriginalTexto = match ($proceso->tipo_sancion) {
            'llamado_atencion' => 'Llamado de Atención',
            'suspension' => 'Suspensión Laboral' . ($proceso->dias_suspension ? " de {$proceso->dias_suspension} día(s)" : ''),
            'terminacion' => 'Terminación de Contrato',
            'no_sancion' => 'Sin Sanción',
            default => ucfirst(str_replace('_', ' ', $proceso->tipo_sancion ?? 'N/A')),
        };

        // Generar HTML del documento
        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resolución de Impugnación</title>
    <style>
        @page {
            margin: 2cm 2cm 2cm 2cm;
        }
        body {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.2;
            color: #000000;
            text-align: justify;
        }
        h1 {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin: 5px 0;
            color: #000000;
        }
        h2 {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            margin: 5px 0;
            color: #000000;
        }
        h3 {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            font-weight: bold;
            margin: 10px 0 4px 0;
            color: #000000;
        }
        p {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            margin: 4px 0;
            text-align: justify;
            line-height: 1.2;
        }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 15px;">
        <h1>{$empresa->nombre_completo}</h1>
        <p style="margin: 2px 0;">NIT: {$empresa->nit}</p>
        <h2>RESOLUCIÓN DE IMPUGNACIÓN</h2>
        <p style="margin: 2px 0;">{$fechaActual->isoFormat('D [de] MMMM [de] YYYY')}</p>
        <p style="margin: 2px 0;">Proceso: {$proceso->codigo}</p>
    </div>

    <div style="margin: 8px 0;">
        <p style="margin: 2px 0;"><strong>Señor(a):</strong> {$trabajador->nombre_completo}</p>
        <p style="margin: 2px 0;"><strong>{$trabajador->tipo_documento}:</strong> {$trabajador->numero_documento}</p>
        <p style="margin: 2px 0;"><strong>Cargo:</strong> {$trabajador->cargo}</p>
    </div>

    <p style="margin: 8px 0;"><strong>Asunto:</strong> Resolución de impugnación presentada contra sanción disciplinaria</p>

    <h3>1. ANTECEDENTES</h3>
    <p>Mediante comunicación de fecha {$proceso->fecha_notificacion?->locale('es')->isoFormat('D [de] MMMM [de] YYYY')}, se le notificó la sanción disciplinaria consistente en <strong>{$sancionOriginalTexto}</strong>, como resultado del proceso disciplinario {$proceso->codigo}.</p>
    <p>En fecha {$impugnacion->fecha_impugnacion?->locale('es')->isoFormat('D [de] MMMM [de] YYYY')}, usted presentó impugnación contra dicha sanción, exponiendo los siguientes motivos:</p>
    <p style="margin-left: 20px; font-style: italic;">{$impugnacion->motivos_impugnacion}</p>

    <h3>2. ANÁLISIS</h3>
    <p>Después de revisar cuidadosamente los argumentos presentados en su impugnación, las pruebas aportadas y el expediente completo del proceso disciplinario, se procede a emitir la siguiente decisión:</p>

    <h3>3. DECISIÓN</h3>
    <p style="text-align: center; margin: 8px 0;"><strong>{$decisionTexto}</strong></p>
HTML;

        if ($impugnacion->decision_final === 'confirma_sancion') {
            $html .= '<p>Se CONFIRMA en todas sus partes la sanción disciplinaria de <strong>' . $sancionOriginalTexto . '</strong> impuesta mediante el proceso ' . $proceso->codigo . '.</p>';
        } elseif ($impugnacion->decision_final === 'revoca_sancion') {
            $html .= '<p>Se REVOCA la sanción disciplinaria de <strong>' . $sancionOriginalTexto . '</strong> impuesta mediante el proceso ' . $proceso->codigo . ', dejándola sin efecto alguno.</p>';
        } elseif ($impugnacion->decision_final === 'modifica_sancion') {
            $html .= '<p>Se MODIFICA la sanción disciplinaria, cambiando de <strong>' . $sancionOriginalTexto . '</strong> a <strong>' . $nuevaSancionTexto . '</strong>.</p>';
        }

        $html .= <<<HTML

    <h3>4. FUNDAMENTO DE LA DECISIÓN</h3>
    <p>{$impugnacion->fundamento_decision}</p>

    <h3>5. EFECTOS</h3>
HTML;

        if ($impugnacion->decision_final === 'confirma_sancion') {
            $html .= '<p>La sanción originalmente impuesta mantiene plena vigencia y debe cumplirse en los términos inicialmente establecidos.</p>';
        } elseif ($impugnacion->decision_final === 'revoca_sancion') {
            $html .= '<p>Al revocar la sanción, el proceso disciplinario queda cerrado sin efectos negativos en su expediente laboral respecto a este caso particular.</p>';
        } elseif ($impugnacion->decision_final === 'modifica_sancion') {
            $html .= '<p>La nueva sanción de <strong>' . $nuevaSancionTexto . '</strong> será aplicable a partir de la fecha de esta notificación, en los términos establecidos por el reglamento interno de trabajo.</p>';
        }

        $html .= <<<HTML

    <p>Esta decisión es definitiva y pone fin al proceso disciplinario {$proceso->codigo}.</p>

    <div style="margin-top: 30px;">
        <p style="margin: 2px 0;">Cordialmente,</p>
        <p style="margin-top: 25px; margin-bottom: 2px;"><strong>{$empresa->representante_legal}</strong></p>
        <p style="margin: 2px 0;">Representante Legal</p>
        <p style="margin: 2px 0;">{$empresa->nombre_completo}</p>
        <p style="margin: 2px 0;">NIT: {$empresa->nit}</p>
    </div>
</body>
</html>
HTML;

        // Guardar y convertir a PDF
        $htmlPath = $this->guardarDocumentoSancionHTML($html, $proceso->codigo, 'resolucion_impugnacion');
        $pdfPath = $this->convertirHTMLaPDF($htmlPath, $proceso->codigo, 'resolucion_impugnacion');

        return $pdfPath;
    }

    /**
     * Enviar resolución de impugnación por correo electrónico
     */
    public function enviarResolucionImpugnacionPorEmail(ProcesoDisciplinario $proceso, string $documentoPath, string $decision): void
    {
        $trabajador = $proceso->trabajador;
        $empresa = $proceso->empresa;
        $impugnacion = $proceso->impugnacion;

        if (empty($trabajador->email)) {
            throw new \Exception('El trabajador no tiene correo electrónico registrado');
        }

        // Crear registro de tracking
        $tracking = EmailTracking::create([
            'token' => EmailTracking::generarToken(),
            'tipo_documento' => 'resolucion_impugnacion',
            'proceso_id' => $proceso->id,
            'trabajador_id' => $trabajador->id,
            'email_destinatario' => $trabajador->email,
            'enviado_en' => Carbon::now('America/Bogota'),
        ]);

        // Determinar texto de decisión para el email
        $decisionTexto = match ($decision) {
            'confirma_sancion' => 'Sanción Confirmada',
            'revoca_sancion' => 'Sanción Revocada',
            'modifica_sancion' => 'Sanción Modificada',
            default => 'Resolución Emitida',
        };

        // Determinar nueva sanción si aplica
        $nuevaSancion = null;
        if ($decision === 'modifica_sancion' && $impugnacion->nueva_sancion_tipo) {
            $nuevaSancion = match ($impugnacion->nueva_sancion_tipo) {
                'llamado_atencion' => 'Llamado de Atención',
                'suspension' => 'Suspensión Laboral',
                'terminacion' => 'Terminación de Contrato',
                'no_sancion' => 'Sin Sanción',
                default => ucfirst(str_replace('_', ' ', $impugnacion->nueva_sancion_tipo)),
            };
        }

        $extension = pathinfo($documentoPath, PATHINFO_EXTENSION);
        $mimeType = $extension === 'pdf' ? 'application/pdf' : 'text/html';
        $nombreArchivo = 'Resolucion_Impugnacion_' . $proceso->codigo . '.' . $extension;

        Mail::send('emails.resolucion-impugnacion', [
            'proceso' => $proceso,
            'trabajador' => $trabajador,
            'empresa' => $empresa,
            'impugnacion' => $impugnacion,
            'decision' => $decision,
            'fundamento' => $impugnacion->fundamento_decision,
            'nuevaSancion' => $nuevaSancion,
            'trackingToken' => $tracking->token,
        ], function ($message) use ($trabajador, $proceso, $documentoPath, $nombreArchivo, $mimeType) {
            $message->to($trabajador->email, $trabajador->nombre_completo)
                ->subject('Resolución de Impugnación - Proceso ' . $proceso->codigo)
                ->attach($documentoPath, [
                    'as' => $nombreArchivo,
                    'mime' => $mimeType,
                ]);
        });

        Log::info('Resolución de impugnación enviada', [
            'proceso_id' => $proceso->id,
            'decision' => $decision,
            'trabajador_email' => $trabajador->email,
            'tracking_token' => substr($tracking->token, 0, 10) . '...',
        ]);
    }

    /**
     * Envía recordatorio al trabajador un día antes de la diligencia de descargos
     */
    public function enviarRecordatorioDescargos(ProcesoDisciplinario $proceso): array
    {
        $trabajador = $proceso->trabajador;
        $empresa = $proceso->empresa;

        if (!$trabajador || !$trabajador->email) {
            Log::warning('No se pudo enviar recordatorio: trabajador sin email', [
                'proceso_id' => $proceso->id,
            ]);
            return [
                'success' => false,
                'error' => 'El trabajador no tiene correo electrónico registrado',
            ];
        }

        try {
            // Obtener el link de descargos si existe
            $diligencia = $proceso->diligenciaDescargo;
            $linkDescargos = null;

            if ($diligencia && $diligencia->token_acceso) {
                $linkDescargos = route('descargos.formulario', ['token' => $diligencia->token_acceso]);
            }

            // Crear tracking para el correo
            $tracking = EmailTracking::create([
                'proceso_id' => $proceso->id,
                'tipo_documento' => 'recordatorio_descargos',
                'trabajador_id' => $trabajador->id,
                'email_destinatario' => $trabajador->email,
                'enviado_en' => Carbon::now('America/Bogota'),
            ]);

            Mail::send('emails.recordatorio-descargos', [
                'proceso' => $proceso,
                'trabajador' => $trabajador,
                'empresa' => $empresa,
                'linkDescargos' => $linkDescargos,
                'trackingToken' => $tracking->token,
            ], function ($message) use ($trabajador, $proceso) {
                $message->to($trabajador->email, $trabajador->nombre_completo)
                    ->subject('RECORDATORIO: Su diligencia de descargos es mañana - Proceso ' . $proceso->codigo);
            });

            Log::info('Recordatorio de descargos enviado al trabajador', [
                'proceso_id' => $proceso->id,
                'codigo' => $proceso->codigo,
                'trabajador_email' => $trabajador->email,
                'fecha_descargos' => $proceso->fecha_descargos_programada,
            ]);

            return [
                'success' => true,
                'mensaje' => 'Recordatorio enviado exitosamente',
            ];

        } catch (\Exception $e) {
            Log::error('Error al enviar recordatorio de descargos', [
                'proceso_id' => $proceso->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Envía notificación al empleador cuando el trabajador no se presenta a los descargos
     */
    public function notificarEmpleadorDescargosNoRealizados(ProcesoDisciplinario $proceso): array
    {
        $trabajador = $proceso->trabajador;
        $empresa = $proceso->empresa;

        // Obtener usuarios cliente de la empresa (empleador/RRHH)
        $clientes = \App\Models\User::where('role', 'cliente')
            ->where('empresa_id', $proceso->empresa_id)
            ->where('active', true)
            ->get();

        if ($clientes->isEmpty()) {
            Log::warning('No se encontraron usuarios cliente para notificar descargos no realizados', [
                'proceso_id' => $proceso->id,
                'empresa_id' => $proceso->empresa_id,
            ]);
            return [
                'success' => false,
                'error' => 'No se encontraron usuarios de la empresa para notificar',
            ];
        }

        $enviados = 0;
        $errores = [];

        foreach ($clientes as $cliente) {
            if (!$cliente->email) {
                continue;
            }

            try {
                // Crear tracking para cada correo
                $tracking = EmailTracking::create([
                    'proceso_id' => $proceso->id,
                    'tipo_documento' => 'descargos_no_realizados_empleador',
                    'trabajador_id' => $trabajador->id,
                    'email_destinatario' => $cliente->email,
                    'enviado_en' => Carbon::now('America/Bogota'),
                ]);

                Mail::send('emails.descargos-no-realizados-empleador', [
                    'proceso' => $proceso,
                    'trabajador' => $trabajador,
                    'empresa' => $empresa,
                    'cliente' => $cliente,
                    'trackingToken' => $tracking->token,
                ], function ($message) use ($cliente, $proceso, $trabajador) {
                    $message->to($cliente->email, $cliente->name)
                        ->subject('Aviso: ' . $trabajador->nombre_completo . ' no se presentó a los descargos - Proceso ' . $proceso->codigo);
                });

                $enviados++;

                Log::info('Notificación de descargos no realizados enviada al empleador', [
                    'proceso_id' => $proceso->id,
                    'cliente_email' => $cliente->email,
                    'trabajador' => $trabajador->nombre_completo,
                ]);

            } catch (\Exception $e) {
                Log::error('Error al enviar notificación de descargos no realizados', [
                    'proceso_id' => $proceso->id,
                    'cliente_email' => $cliente->email,
                    'error' => $e->getMessage(),
                ]);
                $errores[] = $cliente->email . ': ' . $e->getMessage();
            }
        }

        if ($enviados > 0) {
            return [
                'success' => true,
                'mensaje' => "Notificación enviada a {$enviados} destinatario(s)",
                'enviados' => $enviados,
            ];
        }

        return [
            'success' => false,
            'error' => 'No se pudo enviar ninguna notificación',
            'errores' => $errores,
        ];
    }
}
