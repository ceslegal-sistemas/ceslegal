<?php

namespace App\Services;

use App\Models\ProcesoDisciplinario;
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

class DocumentGeneratorService
{
    /**
     * Generar citación a descargos desde la plantilla
     *
     * @param ProcesoDisciplinario $proceso
     * @return string Ruta del PDF generado
     */
    public function generarCitacionDescargos(ProcesoDisciplinario $proceso): string
    {
        // Ruta de la plantilla
        $templatePath = base_path('FORMATO A CITACIÓN A DESCARGOS-GENERAL-19 DE DICIEMBRE DE 2025.docx');

        if (!file_exists($templatePath)) {
            throw new \Exception('No se encontró la plantilla de citación a descargos');
        }

        // Crear el procesador de plantillas
        $templateProcessor = new TemplateProcessor($templatePath);

        // Preparar los datos
        $trabajador = $proceso->trabajador;
        $empresa = $proceso->empresa;

        // Formatear fecha actual
        $fechaActual = Carbon::now()->locale('es');

        // Formatear fecha de descargos (campo DATE)
        $fechaDescargos = $proceso->fecha_descargos_programada
            ? Carbon::parse($proceso->fecha_descargos_programada)->locale('es')
            : null;

        // Formatear hora de descargos (campo TIME)
        $horaDescargos = null;
        if ($proceso->hora_descargos_programada) {
            try {
                // El campo es TIME (H:i:s), convertirlo a formato legible
                $horaDescargos = Carbon::createFromFormat('H:i:s', $proceso->hora_descargos_programada)
                    ->locale('es')
                    ->format('h:i A');
            } catch (\Exception $e) {
                // Si falla, intentar parsearlo como string normal
                try {
                    $horaDescargos = Carbon::parse($proceso->hora_descargos_programada)
                        ->locale('es')
                        ->format('h:i A');
                } catch (\Exception $e2) {
                    // Si todo falla, usar el valor tal cual
                    $horaDescargos = $proceso->hora_descargos_programada;
                }
            }
        }

        // Formatear fecha de ocurrencia
        $fechaOcurrencia = $proceso->fecha_ocurrencia
            ? Carbon::parse($proceso->fecha_ocurrencia)->locale('es')
            : null;

        // Separar nombres y apellidos del trabajador
        $nombreCompleto = $trabajador->nombre_completo ?? '';
        $partes = explode(' ', $nombreCompleto, 3);
        $nombres = isset($partes[0]) && isset($partes[1]) ? $partes[0] . ' ' . $partes[1] : ($partes[0] ?? '');
        $apellidos = $partes[2] ?? '';

        // COMENTADO: Artículos legales - Ahora se usan Sanciones Laborales
        // $articulosLegalesTexto = $proceso->articulos_legales_texto ?? 'No especificado';
        $sancionesLaboralesTexto = $proceso->sanciones_laborales_texto ?? 'No especificado';

        // Preparar variables de ubicación según modalidad
        $direccionTexto = '';
        $ciudadTexto = '';
        $departamentoTexto = '';

        if ($proceso->modalidad_descargos === 'presencial') {
            // Mostrar dirección completa solo para modalidad presencial
            $direccionTexto = !empty($empresa->direccion) ? 'ubicada en la dirección ' . $empresa->direccion . ',' : '';
            $ciudadTexto = !empty($empresa->ciudad) ? $empresa->ciudad . ', ' : '';
            $departamentoTexto = !empty($empresa->departamento) ? $empresa->departamento : '';
        }

        // Variables que la plantilla espera
        $variables = [
            // Variables de fecha actual
            'DIA' => $fechaActual->format('d'),
            'MES' => $fechaActual->isoFormat('MMMM'),
            'AÑO' => $fechaActual->year,
            'DIA_LETRA' => $fechaActual->isoFormat('dddd'),

            // Variables de la empresa
            'CIUDAD' => !empty($empresa->ciudad) ? $empresa->ciudad . '., ' : '',
            'CIUDAD_EMPRESA' => $ciudadTexto,
            'DEPARTAMENTO' => !empty($empresa->departamento) ? $empresa->departamento . '. ' : '',
            'DEPARTAMENTO_EMPRESA' => $departamentoTexto,
            'NIT' => $empresa->nit ?? '',
            'DIRECCION_EMPRESA' => $direccionTexto,
            'NOMBRE_EMPRESA' => $empresa->razon_social ?? '',

            // Variables del trabajador
            'NOMBRES' => $nombres,
            'APELLIDOS' => $apellidos,
            'NUMERO_DOCUMENTO' => $trabajador->numero_documento ?? '',
            'CARGO' => $trabajador->cargo ?? '',
            'CORREO' => $trabajador->email ?? '',

            // Variables de la citación a descargos
            'DIA_DESCARGOS' => $fechaDescargos ? $fechaDescargos->format('d') : '',
            'MES_DESCARGOS' => $fechaDescargos ? $fechaDescargos->isoFormat('MMMM') : '',
            'AÑO_DESCARGOS' => $fechaDescargos ? $fechaDescargos->year : '',
            'DIA_LETRA_DESCARGOS' => $fechaDescargos ? $fechaDescargos->isoFormat('dddd') : '',
            'HORA_DESCARGOS' => $horaDescargos ?? '',
            'MODALIDAD_DESCARGOS' => ucfirst($proceso->modalidad_descargos ?? 'presencial'),

            // Variables de la ocurrencia de los hechos
            'DIA_OCURRENCIA' => $fechaOcurrencia ? $fechaOcurrencia->format('d') : '',
            'MES_OCURRENCIA' => $fechaOcurrencia ? $fechaOcurrencia->isoFormat('MMMM') : '',
            'AÑO_OCURRENCIA' => $fechaOcurrencia ? $fechaOcurrencia->year : '',
            'DIA_LETRA_OCURRENCIA' => $fechaOcurrencia ? $fechaOcurrencia->isoFormat('dddd') : '',
            'HORA_OCURRENCIA' => $fechaOcurrencia ? $fechaOcurrencia->format('H:i A') : '',

            // Razón del descargo (hechos)
            'RAZON_DESCARGO' => strip_tags($proceso->hechos ?? ''),

            // COMENTADO: Artículos legales - Ahora se usan Sanciones Laborales
            // 'ARTICULOS_LEGALES' => $articulosLegalesTexto,
            'SANCIONES_LABORALES' => $sancionesLaboralesTexto,

            // Variables adicionales (compatibilidad con plantillas antiguas)
            'CODIGO_PROCESO' => $proceso->codigo ?? 'N/A',
            'EMPRESA_NOMBRE' => $empresa->razon_social ?? '',
            'EMPRESA_NIT' => $empresa->nit ?? '',
            'NOMBRE_EMPLEADOR' => $empresa->representante_legal ?? 'Representante Legal',
            'TRABAJADOR_NOMBRE' => $trabajador->nombre_completo ?? '',
            'TRABAJADOR_DOCUMENTO' => ($trabajador->tipo_documento ?? '') . ' ' . ($trabajador->numero_documento ?? ''),
            'TRABAJADOR_CARGO' => $trabajador->cargo ?? '',
            'TRABAJADOR_AREA' => $trabajador->area ?? '',
            'TRABAJADOR_EMAIL' => $trabajador->email ?? '',
            'FECHA_DESCARGOS' => ($fechaDescargos && $horaDescargos)
                ? $fechaDescargos->isoFormat('dddd, D [de] MMMM [de] YYYY') . ' a las ' . $horaDescargos
                : ($fechaDescargos ? $fechaDescargos->isoFormat('dddd, D [de] MMMM [de] YYYY') : ''),
            'MODALIDAD_DESCARGOS' => ucfirst($proceso->modalidad_descargos ?? 'presencial'),
            'HECHOS' => strip_tags($proceso->hechos ?? ''),
            'ANTECEDENTES' => strip_tags($proceso->antecedentes ?? ''),
            'NORMAS_INCUMPLIDAS' => strip_tags($proceso->normas_incumplidas ?? ''),
            'IDENTIFICACION_PERJUICIO' => strip_tags($proceso->identificacion_perjuicio ?? ''),
        ];

        // Reemplazar las variables en la plantilla
        foreach ($variables as $variable => $valor) {
            try {
                $templateProcessor->setValue($variable, $valor);
            } catch (\Exception $e) {
                // Si la variable no existe en la plantilla, continuar
                continue;
            }
        }

        // Guardar el documento procesado temporalmente
        $tempDocxPath = storage_path('app/temp/citacion_' . $proceso->codigo . '_' . time() . '.docx');

        // Crear directorio si no existe
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $templateProcessor->saveAs($tempDocxPath);

        // Convertir DOCX a PDF usando LibreOffice (si está disponible) o generar HTML
        $pdfPath = $this->convertirDocxAPdf($tempDocxPath, $proceso->codigo);

        // Eliminar el archivo temporal DOCX
        if (file_exists($tempDocxPath)) {
            unlink($tempDocxPath);
        }

        return $pdfPath;
    }

    /**
     * Convertir DOCX a PDF
     */
    private function convertirDocxAPdf(string $docxPath, string $codigo): string
    {
        $pdfPath = storage_path('app/citaciones/citacion_' . $codigo . '_' . time() . '.pdf');
        $outputDir = storage_path('app/citaciones');

        // Crear directorio si no existe
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Obtener la ruta de LibreOffice
        $libreOfficePath = $this->getLibreOfficePath();

        if ($libreOfficePath) {
            // Crear directorio temporal para perfil de LibreOffice (evita conflictos)
            $tempProfileDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lo_profile_' . uniqid();
            @mkdir($tempProfileDir, 0755, true);

            // Convertir rutas a formato Windows nativo
            $docxPathWin = realpath($docxPath) ?: str_replace('/', '\\', $docxPath);
            $outputDirWin = realpath($outputDir) ?: str_replace('/', '\\', $outputDir);

            // Comando para Windows con perfil temporal
            $command = sprintf(
                '"%s" --headless --nofirststartwizard "-env:UserInstallation=file:///%s" --convert-to pdf --outdir "%s" "%s" 2>&1',
                $libreOfficePath,
                str_replace('\\', '/', $tempProfileDir),
                $outputDirWin,
                $docxPathWin
            );

            \Illuminate\Support\Facades\Log::info('Ejecutando LibreOffice', [
                'command' => $command,
                'docx_path' => $docxPathWin,
                'output_dir' => $outputDirWin,
            ]);

            // Ejecutar el comando
            $output = [];
            $return = 0;
            exec($command, $output, $return);

            \Illuminate\Support\Facades\Log::info('Resultado LibreOffice', [
                'return_code' => $return,
                'output' => $output,
            ]);

            // Limpiar directorio de perfil temporal
            $this->eliminarDirectorioRecursivo($tempProfileDir);

            // LibreOffice genera el PDF con el mismo nombre base del archivo original
            $nombreBase = pathinfo($docxPath, PATHINFO_FILENAME);
            $generatedPdf = $outputDir . DIRECTORY_SEPARATOR . $nombreBase . '.pdf';

            \Illuminate\Support\Facades\Log::info('Buscando PDF generado', [
                'expected_path' => $generatedPdf,
                'exists' => file_exists($generatedPdf),
            ]);

            if (file_exists($generatedPdf)) {
                // Renombrar al nombre final si es diferente
                if (realpath($generatedPdf) !== realpath($pdfPath)) {
                    rename($generatedPdf, $pdfPath);
                }
                \Illuminate\Support\Facades\Log::info('PDF generado con LibreOffice exitosamente', ['path' => $pdfPath]);
                return $pdfPath;
            }

            // Si no encontró el archivo, buscar PDFs recientes en el directorio
            $archivosRecientes = glob($outputDir . DIRECTORY_SEPARATOR . '*.pdf');
            if (!empty($archivosRecientes)) {
                foreach ($archivosRecientes as $archivo) {
                    // Buscar archivos modificados en los últimos 30 segundos
                    if (filemtime($archivo) > time() - 30) {
                        \Illuminate\Support\Facades\Log::info('PDF encontrado por búsqueda temporal', ['found' => $archivo]);
                        if (realpath($archivo) !== realpath($pdfPath)) {
                            rename($archivo, $pdfPath);
                        }
                        return $pdfPath;
                    }
                }
            }

            \Illuminate\Support\Facades\Log::warning('LibreOffice ejecutó pero no generó PDF', [
                'archivos_en_dir' => $archivosRecientes,
            ]);
        }

        // Si LibreOffice no funciona, usar lectura directa del DOCX + Dompdf
        \Illuminate\Support\Facades\Log::info('Usando extracción directa de texto + Dompdf');

        try {
            // Extraer texto del DOCX directamente (sin PhpWord que falla con &nbsp;)
            $htmlContent = $this->extraerTextoDeDocx($docxPath);

            // Convertir HTML a PDF con Dompdf
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            $options->set('defaultPaperSize', 'letter');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($htmlContent);
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();

            // Guardar el PDF
            file_put_contents($pdfPath, $dompdf->output());

            \Illuminate\Support\Facades\Log::info('PDF generado con Dompdf', ['path' => $pdfPath]);

            return $pdfPath;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al convertir DOCX a PDF', [
                'error' => $e->getMessage(),
                'docx_path' => $docxPath,
            ]);

            // Si todo falla, copiar el DOCX como última alternativa
            $docxFallback = str_replace('.pdf', '.docx', $pdfPath);
            copy($docxPath, $docxFallback);
            return $docxFallback;
        }
    }

    /**
     * Extraer texto de un archivo DOCX y convertirlo a HTML
     */
    private function extraerTextoDeDocx(string $docxPath): string
    {
        $paragrafos = [];

        try {
            $zip = new \ZipArchive();
            if ($zip->open($docxPath) === true) {
                $content = $zip->getFromName('word/document.xml');
                $zip->close();

                if ($content) {
                    // Limpiar entidades problemáticas
                    $content = str_replace(['&nbsp;', '&ndash;', '&mdash;'], [' ', '-', '-'], $content);

                    // Suprimir errores de XML
                    libxml_use_internal_errors(true);

                    $dom = new \DOMDocument();
                    $dom->loadXML($content);

                    $xpath = new \DOMXPath($dom);
                    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

                    // Obtener todos los párrafos
                    $parrafosXml = $xpath->query('//w:p');

                    foreach ($parrafosXml as $parrafo) {
                        $textos = $xpath->query('.//w:t', $parrafo);
                        $textoParrafo = '';

                        foreach ($textos as $texto) {
                            $textoParrafo .= $texto->textContent;
                        }

                        if (trim($textoParrafo)) {
                            $paragrafos[] = htmlspecialchars(trim($textoParrafo));
                        }
                    }

                    libxml_clear_errors();
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Error al extraer texto de DOCX', [
                'error' => $e->getMessage(),
            ]);
        }

        // Construir HTML
        $contenidoHtml = '';
        foreach ($paragrafos as $p) {
            $contenidoHtml .= "<p>{$p}</p>\n";
        }

        if (empty($contenidoHtml)) {
            $contenidoHtml = '<p>Documento de citación a descargos</p>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Citación a Descargos</title>
    <style>
        @page { margin: 2.5cm; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
            text-align: justify;
        }
        p { margin: 10px 0; }
    </style>
</head>
<body>
{$contenidoHtml}
</body>
</html>
HTML;
    }

    /**
     * Eliminar directorio recursivamente
     */
    private function eliminarDirectorioRecursivo(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        try {
            $archivos = array_diff(scandir($dir), ['.', '..']);
            foreach ($archivos as $archivo) {
                $ruta = $dir . DIRECTORY_SEPARATOR . $archivo;
                is_dir($ruta) ? $this->eliminarDirectorioRecursivo($ruta) : @unlink($ruta);
            }
            @rmdir($dir);
        } catch (\Exception $e) {
            // Ignorar errores al limpiar
        }
    }

    /**
     * Obtener la ruta de LibreOffice según el sistema operativo
     */
    private function getLibreOfficePath(): ?string
    {
        // Rutas comunes de LibreOffice en Windows
        $windowsPaths = [
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            'C:\\LibreOffice\\program\\soffice.exe',
        ];

        foreach ($windowsPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Intentar con el comando directo (si está en PATH)
        exec('where soffice 2>nul', $output, $return);
        if ($return === 0 && !empty($output[0])) {
            return trim($output[0]);
        }

        return null;
    }

    /**
     * Mejorar el HTML generado por PhpWord para mejor conversión a PDF
     */
    private function mejorarHTMLCitacion(string $html): string
    {
        // Agregar estilos CSS para formato profesional
        $estilos = <<<CSS
<style>
    @page {
        margin: 2cm 2.5cm 2cm 2.5cm;
    }
    body {
        font-family: 'Arial', 'Helvetica', sans-serif;
        font-size: 11pt;
        line-height: 1.5;
        color: #000000;
        text-align: justify;
    }
    p {
        margin: 8px 0;
        text-align: justify;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    td, th {
        padding: 5px;
        vertical-align: top;
    }
</style>
CSS;

        // Insertar estilos antes del cierre de </head>
        if (stripos($html, '</head>') !== false) {
            $html = str_ireplace('</head>', $estilos . '</head>', $html);
        } else {
            // Si no tiene head, agregar al inicio del body
            $html = str_ireplace('<body', $estilos . '<body', $html);
        }

        return $html;
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
        ], function ($message) use ($trabajador, $proceso, $pdfPath, $nombreArchivo, $mimeType) {
            $message->to($trabajador->email, $trabajador->nombre_completo)
                ->subject('Citación a Audiencia de Descargos - Proceso ' . $proceso->codigo)
                ->attach($pdfPath, [
                    'as' => $nombreArchivo,
                    'mime' => $mimeType,
                ]);
        });
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
                    $preguntasGeneradas = $iaService->generarPreguntasCompletas($diligencia, 5);

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
                'plantilla_usada' => 'FORMATO A CITACIÓN A DESCARGOS-GENERAL-19 DE DICIEMBRE DE 2025.docx',
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

            // Construir el contexto de descargos
            $contextoDescargos = '';
            foreach ($preguntasRespuestas as $index => $pr) {
                $contextoDescargos .= ($index + 1) . ". Pregunta: {$pr['pregunta']}\n   Respuesta del trabajador: {$pr['respuesta']}\n\n";
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
                $contextoDescargos
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
            margin: 2.5cm 2.5cm 2.5cm 2.5cm;
        }
        body {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000000;
            text-align: justify;
        }
        h1 {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
            color: #000000;
        }
        h2 {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
            color: #000000;
        }
        h3 {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            font-weight: bold;
            margin: 15px 0 8px 0;
            color: #000000;
        }
        p {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            margin: 8px 0;
            text-align: justify;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        .info-section {
            margin: 15px 0;
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
        string $contextoDescargos
    ): string {
        $fechaActual = Carbon::now()->locale('es');
        // COMENTADO: Artículos legales - Ahora se usan Sanciones Laborales
        // $articulosLegales = $proceso->articulos_legales_texto ?? 'Código Sustantivo del Trabajo';
        $sancionesLaborales = $proceso->sanciones_laborales_texto ?? 'Reglamento Interno de Trabajo';
        $hechosTexto = strip_tags($proceso->hechos);

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
{$contextoDescargos}

INSTRUCCIONES DE REDACCIÓN (LENGUAJE CLARO):
- Oraciones cortas (máximo 25 palabras)
- Voz activa ("decidimos" no "fue decidido")
- Palabras simples (evita jerga legal)
- Habla directo al trabajador ("usted")
- Sin frases como "por medio de la presente"

FORMATO REQUERIDO:
- Fuente: Calibri 11pt
- Texto justificado
- Interlineado 1.5
- Estilo profesional tipo documento Word
- Solo texto en negro

ESTRUCTURA DEL DOCUMENTO:
Genera HTML con exactamente esta estructura:

<div style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; line-height: 1.5; text-align: justify; color: #000000;">

  <div style="text-align: center; margin-bottom: 25px;">
    <h1 style="font-family: Calibri, Arial, sans-serif; font-size: 14pt; font-weight: bold; margin: 10px 0; color: #000000;">{$empresa->razon_social}</h1>
    <p style="font-size: 11pt; margin: 5px 0;">NIT: {$empresa->nit}</p>
    <h2 style="font-family: Calibri, Arial, sans-serif; font-size: 12pt; font-weight: bold; margin: 15px 0; color: #000000; text-transform: uppercase;">{$nombreSancion}</h2>
    <p style="font-size: 11pt; margin: 5px 0;">{$fechaActual->isoFormat('D [de] MMMM [de] YYYY')}</p>
    <p style="font-size: 11pt; margin: 5px 0;">Proceso: {$proceso->codigo}</p>
  </div>

  <div style="margin: 20px 0;">
    <p style="margin: 5px 0;"><strong>Señor(a):</strong> {$trabajador->nombre_completo}</p>
    <p style="margin: 5px 0;"><strong>Cargo:</strong> {$trabajador->cargo}</p>
    <p style="margin: 5px 0;"><strong>Presente</strong></p>
  </div>

  <p style="margin: 15px 0;"><strong>Asunto:</strong> Notificación de {$nombreSancion}</p>

  <p style="margin: 10px 0;">Estimado(a) {$trabajador->nombre_completo}:</p>

  <p style="margin: 10px 0;">Le escribimos para informarle sobre una decisión importante relacionada con su trabajo en {$empresa->razon_social}.</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 15px 0 8px 0; color: #000000;">1. Hechos que motivaron esta decisión</h3>
  <p style="margin: 8px 0;">[Describe los hechos claramente mencionando fechas específicas y acciones concretas. Usa 2-3 oraciones.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 15px 0 8px 0; color: #000000;">2. Por qué estos hechos son importantes</h3>
  <p style="margin: 8px 0;">[Explica el impacto de los hechos y cómo afectan las obligaciones laborales. Usa lenguaje simple.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 15px 0 8px 0; color: #000000;">3. Sus descargos</h3>
  <p style="margin: 8px 0;">[Resume los descargos del trabajador reconociendo su versión. Demuestra que fueron escuchados.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 15px 0 8px 0; color: #000000;">4. Nuestra decisión</h3>
  <p style="margin: 8px 0;">Después de analizar cuidadosamente toda la información, hemos decidido aplicar un {$nombreSancion}. [Explica claramente las razones de esta decisión.]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 15px 0 8px 0; color: #000000;">5. Qué significa esto para usted</h3>
  <p style="margin: 8px 0;">[Explica las consecuencias prácticas de forma clara y específica.{$textoSuspension}]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 15px 0 8px 0; color: #000000;">6. Base legal</h3>
  <p style="margin: 8px 0;">Esta decisión se fundamenta en el Código Sustantivo del Trabajo de Colombia, el reglamento interno de trabajo de la empresa y las normas establecidas en su contrato laboral.</p>

  <p style="margin: 8px 0;"><strong>Sanciones del reglamento incumplidas:</strong></p>
  <p style="margin: 8px 0;">[Separar cada sanción por su propio párrafo, explicando en lenguaje claro qué significan.{$sancionesLaborales}]</p>

  <h3 style="font-family: Calibri, Arial, sans-serif; font-size: 11pt; font-weight: bold; margin: 15px 0 8px 0; color: #000000;">7. Sus derechos de impugnación</h3>
  <p style="margin: 8px 0;">Si no está de acuerdo con esta decisión, usted tiene derecho a presentar una impugnación. Esto significa que puede solicitar una nueva revisión de su caso. Cuenta con {$diasImpugnacion} días hábiles a partir de la fecha de esta notificación para ejercer este derecho.</p>

  <p style="margin: 15px 0;">Si tiene preguntas sobre esta comunicación, puede contactarnos.</p>

  <div style="margin-top: 50px;">
    <p style="margin: 5px 0;">Cordialmente,</p>
    <p style="margin-top: 35px; margin-bottom: 5px;"><strong>{$empresa->representante_legal}</strong></p>
    <p style="margin: 3px 0;">Representante Legal</p>
    <p style="margin: 3px 0;">{$empresa->razon_social}</p>
    <p style="margin: 3px 0;">NIT: {$empresa->nit}</p>
  </div>

</div>

IMPORTANTE:
- Completa TODAS las secciones [entre corchetes] con contenido específico basado en HECHOS y DESCARGOS
- Mantén el formato exacto (Calibri 11pt, texto justificado, negro)
- NO incluyas bloques de código markdown (```html)
- Genera SOLO el HTML mostrado, sin texto adicional
- Sé profesional pero claro y accesible
PROMPT;
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
     * Convertir HTML a PDF
     */
    private function convertirHTMLaPDF(string $htmlPath, string $codigo, string $tipoSancion): string
    {
        $pdfPath = storage_path('app/sanciones/sancion_' . $codigo . '_' . $tipoSancion . '_' . time() . '.pdf');

        try {
            // Usar Dompdf para convertir HTML a PDF
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');

            $dompdf = new Dompdf($options);
            $html = file_get_contents($htmlPath);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();

            file_put_contents($pdfPath, $dompdf->output());

            // Eliminar el archivo HTML temporal
            if (file_exists($htmlPath)) {
                unlink($htmlPath);
            }

            return $pdfPath;
        } catch (\Exception $e) {
            // Si falla la conversión a PDF, devolver el HTML
            \Illuminate\Support\Facades\Log::warning('No se pudo convertir HTML a PDF, se usará HTML', [
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
        ], function ($message) use ($trabajador, $proceso, $documentoPath, $nombreArchivo, $mimeType, $nombreSancion) {
            $message->to($trabajador->email, $trabajador->nombre_completo)
                ->subject('Notificación de ' . $nombreSancion . ' - Proceso ' . $proceso->codigo)
                ->attach($documentoPath, [
                    'as' => $nombreArchivo,
                    'mime' => $mimeType,
                ]);
        });
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
}
