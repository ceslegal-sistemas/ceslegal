<?php

namespace App\Services;

use App\Models\ProcesoDisciplinario;
use App\Models\EmailTracking;
use App\Services\TimelineService;
use App\Services\EstadoProcesoService;
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
     * Genera el HTML del documento de citación a descargos
     * Formato idéntico al DOCX original
     */
    private function generarHTMLCitacionDescargos(ProcesoDisciplinario $proceso): string
    {
        $trabajador = $proceso->trabajador;
        $empresa = $proceso->empresa;
        $fechaActual = Carbon::now()->locale('es');

        // Formatear fecha de descargos
        $fechaDescargos = $proceso->fecha_descargos_programada
            ? Carbon::parse($proceso->fecha_descargos_programada)->locale('es')
            : null;

        // Formatear hora de descargos
        $horaDescargos = null;
        if ($proceso->hora_descargos_programada) {
            try {
                $horaDescargos = Carbon::createFromFormat('H:i:s', $proceso->hora_descargos_programada)
                    ->locale('es')
                    ->format('h:i A');
            } catch (\Exception $e) {
                try {
                    $horaDescargos = Carbon::parse($proceso->hora_descargos_programada)
                        ->locale('es')
                        ->format('h:i A');
                } catch (\Exception $e2) {
                    $horaDescargos = $proceso->hora_descargos_programada;
                }
            }
        }

        // Formatear fecha de ocurrencia (principal)
        $fechaOcurrencia = $proceso->fecha_ocurrencia
            ? Carbon::parse($proceso->fecha_ocurrencia)->locale('es')
            : null;

        // Obtener todas las fechas de ocurrencia (principal + adicionales)
        $fechasOcurrenciaTexto = $proceso->fechas_ocurrencia_texto ?? 'No especificada';

        // Hechos del proceso
        $hechosTexto = html_entity_decode(strip_tags($proceso->hechos ?? ''), ENT_QUOTES, 'UTF-8');
        // Formatear variables igual que en el DOCX
        $ciudad = !empty($empresa->ciudad) ? $empresa->ciudad . ', ' : '';
        $departamento = !empty($empresa->departamento) ? $empresa->departamento . '. ' : '';
        $dia = $fechaActual->format('d');
        $mes = $fechaActual->isoFormat('MMMM');
        $anio = $fechaActual->year;

        // Separar nombres y apellidos
        $nombreCompleto = $trabajador->nombre_completo ?? '';
        $partes = explode(' ', $nombreCompleto);
        // Asumimos: primeros 2 elementos son nombres, resto son apellidos
        $nombres = '';
        $apellidos = '';
        if (count($partes) >= 4) {
            $nombres = $partes[0] . ' ' . $partes[1];
            $apellidos = implode(' ', array_slice($partes, 2));
        } elseif (count($partes) >= 2) {
            $nombres = $partes[0];
            $apellidos = implode(' ', array_slice($partes, 1));
        } else {
            $nombres = $nombreCompleto;
        }

        $numeroDocumento = $trabajador->numero_documento ?? '';
        $cargo = $trabajador->cargo ?? '';

        // Fecha de descargos
        $diaLetraDescargos = $fechaDescargos ? $fechaDescargos->isoFormat('dddd') : '';
        $diaDescargos = $fechaDescargos ? $fechaDescargos->format('d') : '';
        $mesDescargos = $fechaDescargos ? $fechaDescargos->isoFormat('MMMM') : '';
        $anioDescargos = $fechaDescargos ? $fechaDescargos->year : '';
        $horaDescargosTexto = $horaDescargos ?? '';

        $modalidad = strtolower($proceso->modalidad_descargos ?? 'presencial');

        // Dirección según modalidad
        $direccionEmpresa = '';
        $ciudadEmpresa = '';
        $departamentoEmpresa = '';
        if ($modalidad === 'presencial') {
            $direccionEmpresa = !empty($empresa->direccion) ? 'ubicada en la dirección ' . $empresa->direccion . ', ' : '';
            $ciudadEmpresa = !empty($empresa->ciudad) ? $empresa->ciudad . ', ' : '';
            $departamentoEmpresa = !empty($empresa->departamento) ? $empresa->departamento : '';
        }

        // Fecha de ocurrencia
        $diaLetraOcurrencia = $fechaOcurrencia ? $fechaOcurrencia->isoFormat('dddd') : '';
        $diaOcurrencia = $fechaOcurrencia ? $fechaOcurrencia->format('d') : '';
        $mesOcurrencia = $fechaOcurrencia ? $fechaOcurrencia->isoFormat('MMMM') : '';
        $anioOcurrencia = $fechaOcurrencia ? $fechaOcurrencia->year : '';

        // Determinar si hay múltiples fechas de ocurrencia
        $tieneMultiplesFechas = !empty($proceso->fechas_ocurrencia_adicionales) && count($proceso->fechas_ocurrencia_adicionales) > 0;

        // Texto de fecha(s) de ocurrencia para el documento
        if ($tieneMultiplesFechas) {
            $textoFechaOcurrencia = "en las fechas: <strong>{$fechasOcurrenciaTexto}</strong>";
        } else {
            $textoFechaOcurrencia = "el día {$diaLetraOcurrencia} ({$diaOcurrencia}) de {$mesOcurrencia} de {$anioOcurrencia}";
        }

        $nombreEmpresa = $empresa->razon_social ?? '';
        $nombreEmpleador = $empresa->representante_legal ?? 'Representante Legal';
        $nit = $empresa->nit ?? '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citación a Diligencia de Descargos</title>
    <style>
        @page {
            margin: 2.5cm 2.5cm 2.5cm 2.5cm;
        }
        body {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000000;
            text-align: justify;
        }
        .fecha-lugar {
            text-align: right;
            margin-bottom: 30px;
        }
        .destinatario {
            margin-bottom: 20px;
        }
        .destinatario p {
            margin: 0;
            line-height: 1.4;
        }
        .referencia {
            margin-bottom: 20px;
        }
        .referencia p {
            margin: 0;
        }
        .contenido {
            margin-bottom: 20px;
        }
        .contenido p {
            margin: 0 0 15px 0;
            text-align: justify;
        }
        .firma {
            margin-top: 50px;
        }
        .firma p {
            margin: 0;
        }
        .linea-firma {
            margin-top: 60px;
            border-top: 1px solid #000000;
            width: 250px;
            padding-top: 5px;
        }
        strong, b {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="fecha-lugar">
        <p>{$ciudad}{$departamento}{$dia} de {$mes} de {$anio}.</p>
    </div>

    <div class="destinatario">
        <p><strong>Señor(a):</strong></p>
        <p><strong>{$nombres} {$apellidos}.</strong></p>
        <p>C.C. No. {$numeroDocumento}.</p>
        <p>Cargo: {$cargo}.</p>
    </div>

    <div class="referencia">
        <p><strong>Referencia:</strong> Citación a diligencia de descargos al empleado {$nombres} {$apellidos}.</p>
    </div>

    <div class="contenido">
        <p>Respetado(a) {$nombres} {$apellidos},</p>

        <p>Por medio de la presente comunicación en calidad de la empresa <strong>{$nombreEmpresa}</strong>, le informamos que, de conformidad con lo establecido en el Reglamento de Trabajo, Usted ha sido citado a una diligencia de descargos para el día <strong>{$diaLetraDescargos} ({$diaDescargos}) de {$mesDescargos} de {$anioDescargos}</strong> a las <strong>{$horaDescargosTexto}</strong> de forma <strong>{$modalidad}</strong>, {$direccionEmpresa}{$ciudadEmpresa}{$departamentoEmpresa} a la hora señalada.</p>

        <p>Las razones por las cuales es citado a diligencia de descargos, se dan por el hecho de que usted presuntamente {$textoFechaOcurrencia}, razón de los descargos:</p>

        <p>{$hechosTexto}</p>

        <p>De esta manera incumpliendo con sus obligaciones contractuales, reglamentarias y legales, como también los procedimientos y protocolos de la compañía.</p>

        <p>Así las cosas, los anteriores hechos implican una posible violación a sus obligaciones contractuales, reglamentarias y legales, que pueden ser constitutivos de una falta disciplinaria.</p>
    </div>

    <div class="firma">
        <p>Atentamente,</p>
        <div class="linea-firma">
            <p><strong>{$nombreEmpleador}.</strong></p>
            <p>NIT. {$nit}.</p>
        </div>
    </div>
</body>
</html>
HTML;
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
            'tipo_correo' => 'citacion',
            'proceso_id' => $proceso->id,
            'trabajador_id' => $trabajador->id,
            'email_destinatario' => $trabajador->email,
            'enviado_en' => Carbon::now('America/Bogota'),
        ]);

        // Detectar la extensión real del archivo
        $extension = pathinfo($pdfPath, PATHINFO_EXTENSION);
        $mimeType = $extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $nombreArchivo = 'Citacion_Descargos_' . $proceso->codigo . '.' . $extension;

        Mail::send('emails.citacion-descargos', [
            'proceso' => $proceso,
            'trabajador' => $trabajador,
            'empresa' => $empresa,
            'linkDescargos' => $linkDescargos,
            'fechaAccesoPermitida' => $fechaAccesoPermitida,
            'trackingToken' => $tracking->token,
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

            // Generar token de acceso si no existe
            if (!$diligencia->token_acceso) {
                $diligencia->generarTokenAcceso();
            }

            // Configurar acceso temporal
            $diligencia->fecha_acceso_permitida = $proceso->fecha_descargos_programada
                ? Carbon::parse($proceso->fecha_descargos_programada)->toDateString()
                : now()->toDateString();
            $diligencia->acceso_habilitado = true;
            $diligencia->save();

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
            $apiKey = $config['api_key'];
            $model = $config['model'];
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            // Construir el prompt con principios de lenguaje claro
            $prompt = $this->construirPromptSancionLenguajeClaro(
                $proceso,
                $trabajador,
                $empresa,
                $tipoSancion,
                $contextoDescargos,
                $trabajadorNoRespondio
            );

            // Log para debugging
            \Illuminate\Support\Facades\Log::info('Generando documento de sanción con IA', [
                'proceso_id' => $proceso->id,
                'tipo_sancion' => $tipoSancion,
                'max_tokens' => $config['max_tokens'] ?? 8000,
            ]);

            // Llamar a la API de IA con mayor límite de tokens
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
                    'maxOutputTokens' => 8192, // Aumentado para documentos más largos
                    'topP' => 0.95,
                    'topK' => 40,
                ],
            ]);

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

        // Construir la tabla de sanciones (Artículo 20)
        $tablaSanciones = $this->construirTablaSancionesParaPrompt($proceso, $empresa);

        // Incluir días de suspensión en el nombre si aplica
        $diasSuspension = $proceso->dias_suspension;
        $nombreSancion = match ($tipoSancion) {
            'llamado_atencion' => 'Llamado de Atención',
            'suspension' => 'Suspensión Laboral' . ($diasSuspension ? " de {$diasSuspension} día" . ($diasSuspension > 1 ? 's' : '') : ''),
            'terminacion' => 'Terminación de Contrato',
            default => 'Sanción',
        };

        $diasImpugnacion = 3; // Días hábiles para impugnar según ley colombiana

        // Preparar texto específico para suspensiones
        $textoSuspension = '';
        if ($tipoSancion === 'suspension' && $diasSuspension) {
            $textoSuspension = "\n- Días de suspensión: {$diasSuspension} día" . ($diasSuspension > 1 ? 's' : '') . " (sin remuneración)";
        }

        // Preparar texto sobre no respuesta del trabajador
        $textoNoRespondio = '';
        if ($trabajadorNoRespondio) {
            $textoNoRespondio = "\n\nNOTA IMPORTANTE: El trabajador NO respondió al formulario de descargos. Se le envió la citación a descargos y se le dio la oportunidad de presentar su versión de los hechos, pero no ejerció su derecho de defensa dentro del plazo establecido. Esta circunstancia debe mencionarse explícitamente en la sección 3 del documento.";
        }

        return <<<PROMPT
Genera un documento oficial de {$nombreSancion} para un trabajador en Colombia usando formato profesional estilo Word.

INFORMACIÓN DEL CASO:
- Empresa: {$empresa->razon_social} (NIT: {$empresa->nit})
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

INSTRUCCIONES DE REDACCIÓN (LENGUAJE CLARO):
- Oraciones cortas (máximo 25 palabras)
- Voz activa ("decidimos" no "fue decidido")
- Palabras simples (evita jerga legal)
- Habla directo al trabajador ("usted")
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
    <h1 style="font-family: Calibri, Arial, sans-serif; font-size: 14pt; font-weight: bold; margin: 5px 0; color: #000000;">{$empresa->razon_social}</h1>
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

  <p style="margin: 6px 0;">Le escribimos para informarle sobre una decisión importante relacionada con su trabajo en {$empresa->razon_social}.</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">1. Hechos que motivaron esta decisión</h3>
  <p style="margin: 4px 0;">[Describe los hechos claramente mencionando fechas específicas y acciones concretas. Usa 2-3 oraciones.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">2. Por qué estos hechos son importantes</h3>
  <p style="margin: 4px 0;">[Explica el impacto de los hechos y cómo afectan las obligaciones laborales. Usa lenguaje simple.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">3. Sus descargos</h3>
  <p style="margin: 4px 0;">[Si el trabajador respondió: resume los descargos reconociendo su versión. Si NO respondió: indica claramente que se le envió la citación a descargos y se le brindó la oportunidad de presentar su versión de los hechos dentro del plazo legal establecido, pero el trabajador no ejerció su derecho de defensa al no responder al formulario de descargos. Aclara que, no obstante lo anterior, se garantizó plenamente su derecho al debido proceso y defensa.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">4. Nuestra decisión</h3>
  <p style="margin: 4px 0;">Después de analizar cuidadosamente toda la información, hemos decidido aplicar un {$nombreSancion}. [Explica claramente las razones de esta decisión.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 10px 0 4px 0; color: #000000;">5. Qué significa esto para usted</h3>
  <p style="margin: 4px 0;">[Explica las consecuencias prácticas de forma clara y específica.{$textoSuspension}]</p>

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
    <p style="margin: 2px 0;">{$empresa->razon_social}</p>
    <p style="margin: 2px 0;">NIT: {$empresa->nit}</p>
  </div>

</div>

IMPORTANTE:
- Completa TODAS las secciones [entre corchetes] con contenido específico basado en HECHOS y DESCARGOS
- Mantén el formato exacto (Calibri 11pt, texto justificado, negro, interlineado compacto)
- NO incluyas bloques de código markdown (```html)
- Genera SOLO el HTML mostrado, sin texto adicional
- Sé profesional pero claro y accesible
- NUNCA USES EMOJIS en ninguna parte del documento
- TABLA DE SANCIONES (Artículo 20):
  * Incluye la tabla EXACTAMENTE como se proporciona en el HTML
  * Si hay filas con "[ANALIZA...]", reemplaza ese texto con tu análisis:
    - Determina si la conducta es LEVE o GRAVE según su impacto
    - Redacta una descripción clara de la conducta
    - Determina la sanción apropiada (Llamado de Atención para leves, Suspensión o Terminación para graves)
  * NO elimines la tabla, es parte oficial del documento
PROMPT;
    }

    /**
     * Construir la tabla de sanciones (Artículo 20) para incluir en el prompt
     */
    private function construirTablaSancionesParaPrompt(ProcesoDisciplinario $proceso, $empresa): string
    {
        $sancionesLaborales = $proceso->sancionesLaborales;
        $otroMotivo = $proceso->otro_motivo_descargos;

        // Construir filas de la tabla
        $filasTabla = '';

        // Agregar sanciones laborales seleccionadas
        foreach ($sancionesLaborales as $sancion) {
            $tipoFalta = ucfirst($sancion->tipo_falta); // "Leve" o "Grave"
            $descripcion = $sancion->descripcion ?? $sancion->nombre_claro;
            $tipoSancionTexto = $sancion->tipo_sancion_texto;

            $filasTabla .= <<<HTML
    <tr>
      <td style="border: 1px solid #000; padding: 4px 6px; text-align: center; font-weight: bold;">{$tipoFalta}</td>
      <td style="border: 1px solid #000; padding: 4px 6px;">{$descripcion}</td>
      <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{$tipoSancionTexto}</td>
    </tr>
HTML;
        }

        // Si hay "Otro motivo", agregar instrucción para que la IA lo analice
        $instruccionOtro = '';
        if (!empty($otroMotivo)) {
            $instruccionOtro = <<<HTML
    <tr>
      <td style="border: 1px solid #000; padding: 4px 6px; text-align: center; font-weight: bold;">[ANALIZA Y DETERMINA: ¿Esta conducta es LEVE o GRAVE según su gravedad?]</td>
      <td style="border: 1px solid #000; padding: 4px 6px;">[ANALIZA EL SIGUIENTE MOTIVO Y REDACTA UNA DESCRIPCIÓN CLARA: {$otroMotivo}]</td>
      <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">[DETERMINA LA SANCIÓN APROPIADA SEGÚN LA GRAVEDAD]</td>
    </tr>
HTML;
            $filasTabla .= $instruccionOtro;
        }

        // Construir la tabla completa
        return <<<HTML
  <table style="width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 10pt;">
    <tr>
      <td colspan="3" style="border: 1px solid #000; padding: 6px; text-align: center; background-color: #f5f5f5;">
        <strong>TABLA DE SANCIONES LABORALES</strong><br>
        <span style="font-size: 9pt;">(Todas las sanciones contenidas en esta tabla solo se aplicarán previa garantía del debido proceso establecido en este Reglamento, conforme a la Ley 2466 de 2025.)</span>
      </td>
    </tr>
    <tr>
      <td colspan="3" style="border: 1px solid #000; padding: 4px 6px; text-align: center;">
        <strong>{$empresa->razon_social}</strong><br>
        NIT: {$empresa->nit}
      </td>
    </tr>
    <tr style="background-color: #e0e0e0;">
      <th style="border: 1px solid #000; padding: 4px 6px; text-align: center; width: 20%;">Tipo de Falta</th>
      <th style="border: 1px solid #000; padding: 4px 6px; text-align: center; width: 55%;">Descripción de la conducta</th>
      <th style="border: 1px solid #000; padding: 4px 6px; text-align: center; width: 25%;">Sanción</th>
    </tr>
    {$filasTabla}
  </table>
HTML;
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
            'tipo_correo' => 'sancion',
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
     * Enviar notificación de cambio de estado de descargos al trabajador
     */
    public function enviarNotificacionEstadoDescargos(ProcesoDisciplinario $proceso, string $estado): void
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
            'tipo_correo' => 'estado_descargos',
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
        ], function ($message) use ($trabajador, $asunto) {
            $message->to($trabajador->email, $trabajador->nombre_completo)
                ->subject($asunto);
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
                    'tipo_correo' => 'estado_descargos_cliente',
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
        <h1>{$empresa->razon_social}</h1>
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
        <p style="margin: 2px 0;">{$empresa->razon_social}</p>
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
            'tipo_correo' => 'resolucion_impugnacion',
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
