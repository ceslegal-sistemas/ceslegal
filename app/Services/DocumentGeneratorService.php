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

        // Obtener artículos legales seleccionados
        $articulosLegalesTexto = $proceso->articulos_legales_texto ?? 'No especificado';

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

            // Artículos legales
            'ARTICULOS_LEGALES' => $articulosLegalesTexto,

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

        // Crear directorio si no existe
        if (!file_exists(storage_path('app/citaciones'))) {
            mkdir(storage_path('app/citaciones'), 0755, true);
        }

        // Intentar convertir con LibreOffice si está disponible
        if ($this->isLibreOfficeAvailable()) {
            $command = sprintf(
                'soffice --headless --convert-to pdf --outdir %s %s',
                escapeshellarg(dirname($pdfPath)),
                escapeshellarg($docxPath)
            );

            exec($command, $output, $return);

            if ($return === 0) {
                // LibreOffice genera el PDF con el mismo nombre base
                $generatedPdf = dirname($pdfPath) . '/' . pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf';
                if (file_exists($generatedPdf)) {
                    rename($generatedPdf, $pdfPath);
                    return $pdfPath;
                }
            }
        }

        // Si LibreOffice no está disponible, usar conversión alternativa
        // Por ahora, copiar el DOCX como alternativa
        // En producción, considera usar servicios como CloudConvert o similar
        copy($docxPath, str_replace('.pdf', '.docx', $pdfPath));

        return str_replace('.pdf', '.docx', $pdfPath);
    }

    /**
     * Verificar si LibreOffice está disponible
     */
    private function isLibreOfficeAvailable(): bool
    {
        exec('soffice --version 2>&1', $output, $return);
        return $return === 0;
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

        Mail::send('emails.citacion-descargos', [
            'proceso' => $proceso,
            'trabajador' => $trabajador,
            'empresa' => $empresa,
            'linkDescargos' => $linkDescargos,
            'fechaAccesoPermitida' => $fechaAccesoPermitida,
        ], function ($message) use ($trabajador, $proceso, $pdfPath) {
            $message->to($trabajador->email, $trabajador->nombre_completo)
                ->subject('Citación a Audiencia de Descargos - Proceso ' . $proceso->codigo)
                ->attach($pdfPath, [
                    'as' => 'Citacion_Descargos_' . $proceso->codigo . '.pdf',
                    'mime' => 'application/pdf',
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
                        : 'Virtual',
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

            // Enviar por email (con o sin link según la modalidad)
            $this->enviarCitacionPorEmail($proceso, $pdfPath, $linkDescargos, $fechaAccesoPermitida);

            // Refrescar el proceso desde la BD para asegurar que tiene el estado correcto
            $proceso->refresh();

            // Cambiar estado automáticamente a "descargos_pendientes"
            $estadoService = app(EstadoProcesoService::class);
            $estadoService->alEnviarCitacion($proceso);

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
            $mensaje = 'Citación generada y enviada exitosamente. Diligencia de descargos creada con acceso web.';
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
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }
}
