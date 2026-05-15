<?php

namespace App\Services;

use App\Models\DiligenciaDescargo;
use App\Models\ProcesoDisciplinario;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ActaDescargosService
{
    protected PhpWord $phpWord;

    public function __construct()
    {
        Settings::setOutputEscapingEnabled(true);
        $this->phpWord = new PhpWord();
        $this->libreOfficePath = $this->detectLibreOfficePath();
    }

    private function detectLibreOfficePath(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            foreach (['/usr/bin/soffice', '/usr/local/bin/soffice', '/snap/bin/soffice'] as $path) {
                if (file_exists($path)) return $path;
            }
            return 'soffice';
        }

        foreach ([
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ] as $path) {
            if (file_exists($path)) return $path;
        }

        return 'C:\\Program Files\\LibreOffice\\program\\soffice.exe';
    }

    /**
     * Genera la Diligencia Administrativa de Apertura de Investigación Disciplinaria
     * (Antes denominada: Acta de Descargos)
     */
    public function generarActaDescargos(DiligenciaDescargo $diligencia): array
    {
        try {
            $proceso    = $diligencia->proceso;
            $trabajador = $proceso->trabajador;
            $empresa    = $proceso->empresa;

            $section = $this->phpWord->addSection([
                'marginLeft'   => 1440,
                'marginRight'  => 1440,
                'marginTop'    => 1440,
                'marginBottom' => 1440,
            ]);

            $this->agregarTitulo($section, $proceso);
            $this->agregarEncabezado($section, $diligencia, $proceso, $trabajador, $empresa);
            $this->agregarConstanciaAutenticacion($section, $diligencia);
            $this->agregarHechos($section, $proceso);
            $this->agregarPreguntasRespuestas($section, $diligencia);
            $this->agregarInformacionAdicional($section, $diligencia);
            $this->agregarCierre($section, $diligencia, $trabajador);
            $this->agregarFirmas($section, $trabajador, $diligencia);

            $filename = $this->guardarDocumento($proceso);

            return [
                'success'  => true,
                'filename' => $filename,
                'path'     => storage_path('app/actas_descargos/' . $filename),
            ];
        } catch (\Exception $e) {
            Log::error('Error generando diligencia administrativa de descargos', [
                'diligencia_id' => $diligencia->id,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Agrega el encabezado institucional y título del documento
     */
    protected function agregarTitulo($section, $proceso): void
    {
        // Título principal
        $section->addText(
            'DILIGENCIA ADMINISTRATIVA DE APERTURA DE INVESTIGACIÓN DISCIPLINARIA',
            ['bold' => true, 'size' => 13, 'name' => 'Arial'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 80]
        );

        // Subtítulo con denominación anterior
        $section->addText(
            '(Antes denominada: Acta de Descargos)',
            ['italic' => true, 'size' => 10, 'name' => 'Arial'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 80]
        );

        // Número de proceso
        $section->addText(
            'Proceso Disciplinario N.° ' . $this->limpiarTextoParaWord($proceso->codigo ?? ''),
            ['bold' => true, 'size' => 11, 'name' => 'Arial'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 240]
        );
    }

    /**
     * Agrega el párrafo de apertura adaptado al contexto de la plataforma virtual CES Legal
     */
    protected function agregarEncabezado($section, $diligencia, $proceso, $trabajador, $empresa): void
    {
        $municipio    = $this->limpiarTextoParaWord($empresa->ciudad        ?? 'Colombia');
        $departamento = $this->limpiarTextoParaWord($empresa->departamento  ?? '');
        $razonSocial  = $this->limpiarTextoParaWord($empresa->razon_social  ?? '');
        $nit          = $this->limpiarTextoParaWord($empresa->nit           ?? '');
        $nombreTrab   = $this->limpiarTextoParaWord($trabajador->nombre_completo ?? '');
        $tipoDoc      = $this->limpiarTextoParaWord($trabajador->tipo_documento  ?? 'C.C.');
        $numDoc       = $this->limpiarTextoParaWord($trabajador->numero_documento ?? '');
        $cargo        = $this->limpiarTextoParaWord($trabajador->cargo ?? '');
        $esFemenino   = ($trabajador->genero === 'femenino');
        $art          = $esFemenino ? 'la' : 'el';
        $trabGen      = $esFemenino ? 'trabajadora' : 'trabajador';

        // Hora de inicio
        $horaInicio = $diligencia->primer_acceso_en
            ? \Carbon\Carbon::parse($diligencia->primer_acceso_en)->timezone('America/Bogota')->format('h:i A')
            : now()->timezone('America/Bogota')->format('h:i A');

        // Fecha
        $fechaBase = $diligencia->fecha_diligencia
            ? \Carbon\Carbon::parse($diligencia->fecha_diligencia)->timezone('America/Bogota')
            : now()->timezone('America/Bogota');
        $fechaTexto = $this->convertirFechaATexto($fechaBase);

        // Párrafo de apertura — contexto virtual/web
        $ubicacion = trim($municipio . ($departamento ? ', ' . $departamento : ''));

        $apertura =
            "En {$ubicacion}, el {$fechaTexto}, siendo las {$horaInicio}, ".
            "a través de la plataforma virtual de gestión disciplinaria CES Legal (www.ceslegal.com.co), ".
            "se dio inicio a la presente Diligencia Administrativa de Apertura de Investigación Disciplinaria ".
            "dentro del proceso disciplinario N.° {$proceso->codigo}, adelantado por {$razonSocial} con NIT {$nit}. ".
            "En representación del empleador interviene la empresa a través de la plataforma CES Legal ".
            "y, por la otra parte, {$art} {$trabGen} {$nombreTrab}, ".
            "identificad" . ($esFemenino ? 'a' : 'o') . " con {$tipoDoc} N.° {$numDoc}, ".
            "con cargo de {$cargo}, ".
            "quien accedió a la plataforma digital, verificó su identidad de forma electrónica y ".
            "rindió sus descargos y explicaciones en relación con los siguientes hechos:";

        $section->addText(
            $apertura,
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
        );
    }

    /**
     * Agrega la constancia de autenticación digital del trabajador
     * (cadena de custodia de la diligencia virtual)
     */
    protected function agregarConstanciaAutenticacion($section, $diligencia): void
    {
        $section->addText(
            'I. CONSTANCIA DE AUTENTICACIÓN DIGITAL',
            ['bold' => true, 'size' => 11, 'name' => 'Arial'],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'spaceBefore' => 120]
        );

        $section->addText(
            'La identidad del/la trabajador/a fue verificada mediante los siguientes mecanismos de seguridad '.
            'de la plataforma CES Legal, conforme a lo previsto en la Ley 527 de 1999 '.
            '(Ley de Comercio Electrónico) y los principios de equivalencia funcional del mensaje de datos:',
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 100]
        );

        // ── OTP ──────────────────────────────────────────────────────────────
        $otpCanal   = $this->limpiarTextoParaWord($diligencia->otp_canal   ?? 'correo electrónico');
        $otpEnviadoA = $this->limpiarTextoParaWord($diligencia->otp_enviado_a ?? '');

        $otpVerificado = $diligencia->otp_verificado_en
            ? \Carbon\Carbon::parse($diligencia->otp_verificado_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrado';

        $textoOtp = "1. Código de verificación OTP (One-Time Password): enviado por {$otpCanal}" .
            ($otpEnviadoA ? " al destinatario {$otpEnviadoA}" : '') .
            " y verificado satisfactoriamente el {$otpVerificado}.";

        $section->addText(
            $textoOtp,
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 80]
        );

        // ── Disclaimer ───────────────────────────────────────────────────────
        $disclaimerEn = $diligencia->disclaimer_aceptado_en
            ? \Carbon\Carbon::parse($diligencia->disclaimer_aceptado_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrado';

        $section->addText(
            "2. Declaración de participación voluntaria: el/la trabajador/a aceptó la declaración de " .
            "responsabilidad y participación voluntaria en la plataforma el {$disclaimerEn}.",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 80]
        );

        // ── Foto inicio ──────────────────────────────────────────────────────
        $fotoInicioEn = $diligencia->foto_inicio_en
            ? \Carbon\Carbon::parse($diligencia->foto_inicio_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrada';

        $section->addText(
            "3. Verificación facial al inicio de la diligencia: fotografía capturada el {$fotoInicioEn} ".
            "mediante reconocimiento facial con inteligencia artificial, registrada en el sistema con fines de trazabilidad.",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 80]
        );

        // ── Foto fin ─────────────────────────────────────────────────────────
        $fotoFinEn = $diligencia->foto_fin_en
            ? \Carbon\Carbon::parse($diligencia->foto_fin_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrada';

        $section->addText(
            "4. Verificación facial al cierre de la diligencia: fotografía capturada el {$fotoFinEn} ".
            "mediante reconocimiento facial con inteligencia artificial, registrada en el sistema con fines de trazabilidad.",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 80]
        );

        // ── IP ───────────────────────────────────────────────────────────────
        $ip = $this->limpiarTextoParaWord($diligencia->ip_acceso ?? 'No registrada');

        $section->addText(
            "5. Dirección IP de acceso del trabajador: {$ip}.",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 80]
        );

        // ── Primer acceso ────────────────────────────────────────────────────
        $primerAcceso = $diligencia->primer_acceso_en
            ? \Carbon\Carbon::parse($diligencia->primer_acceso_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrado';

        $section->addText(
            "6. Fecha y hora de ingreso a la plataforma: {$primerAcceso}.",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
        );
    }

    /**
     * Agrega los hechos del proceso con encabezado de sección
     */
    protected function agregarHechos($section, $proceso): void
    {
        $section->addText(
            'II. HECHOS OBJETO DE LA INVESTIGACIÓN',
            ['bold' => true, 'size' => 11, 'name' => 'Arial'],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'spaceBefore' => 80]
        );

        $section->addText(
            'Se le informó al/la trabajador/a sobre los hechos que dieron origen al presente proceso disciplinario:',
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 80]
        );

        $hechos = $this->limpiarTextoParaWord($proceso->hechos);

        $section->addText(
            $hechos,
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
        );
    }

    /**
     * Agrega las preguntas y respuestas con encabezado de sección
     */
    protected function agregarPreguntasRespuestas($section, $diligencia): void
    {
        $section->addText(
            'III. DESARROLLO DE LA DILIGENCIA',
            ['bold' => true, 'size' => 11, 'name' => 'Arial'],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'spaceBefore' => 80]
        );

        $section->addText(
            'A continuación se transcriben las preguntas formuladas al/la trabajador/a y las '.
            'respuestas que este/a suministró de manera escrita a través de la plataforma CES Legal:',
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 120]
        );

        $preguntas = $diligencia->preguntas()
            ->with('respuesta')
            ->ordenadas()
            ->get();

        if ($preguntas->isEmpty()) {
            $section->addText(
                'El/la trabajador/a no respondió ninguna pregunta durante la diligencia.',
                ['name' => 'Arial', 'size' => 11, 'italic' => true],
                ['alignment' => Jc::BOTH, 'spaceAfter' => 120]
            );
            return;
        }

        foreach ($preguntas as $pregunta) {
            $section->addText(
                'PREGUNTA: ' . $this->limpiarTextoParaWord($pregunta->pregunta),
                ['bold' => true, 'name' => 'Arial', 'size' => 11],
                ['alignment' => Jc::BOTH, 'spaceAfter' => 60]
            );

            if ($pregunta->respuesta) {
                $section->addText(
                    'RESPUESTA: ' . $this->limpiarTextoParaWord($pregunta->respuesta->respuesta),
                    ['name' => 'Arial', 'size' => 11],
                    ['alignment' => Jc::BOTH, 'spaceAfter' => 120]
                );

                if ($pregunta->respuesta->archivos_adjuntos && count($pregunta->respuesta->archivos_adjuntos) > 0) {
                    $section->addText(
                        'Archivos adjuntos a esta respuesta:',
                        ['italic' => true, 'name' => 'Arial', 'size' => 10],
                        ['alignment' => Jc::BOTH, 'spaceAfter' => 30]
                    );

                    foreach ($pregunta->respuesta->archivos_adjuntos as $archivo) {
                        $section->addText(
                            '  • ' . $this->limpiarTextoParaWord($archivo['nombre'] ?? 'Archivo adjunto'),
                            ['italic' => true, 'name' => 'Arial', 'size' => 10],
                            ['alignment' => Jc::BOTH, 'spaceAfter' => 30]
                        );
                    }
                }
            } else {
                $section->addText(
                    'RESPUESTA: [Sin respuesta registrada]',
                    ['name' => 'Arial', 'size' => 11, 'italic' => true],
                    ['alignment' => Jc::BOTH, 'spaceAfter' => 120]
                );
            }
        }
    }

    /**
     * Agrega información adicional de la diligencia (acompañante y pruebas)
     */
    protected function agregarInformacionAdicional($section, $diligencia): void
    {
        $section->addText(
            'IV. INFORMACIÓN ADICIONAL',
            ['bold' => true, 'size' => 11, 'name' => 'Arial'],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'spaceBefore' => 80]
        );

        // Acompañante
        $acompananteInfo = $this->obtenerInfoAcompanante($diligencia);

        if ($acompananteInfo['tiene_acompanante']) {
            $section->addText(
                'ACOMPAÑANTE DEL/LA TRABAJADOR/A:',
                ['bold' => true, 'name' => 'Arial', 'size' => 11],
                ['alignment' => Jc::BOTH, 'spaceAfter' => 60]
            );

            $section->addText(
                'Nombre: ' . $this->limpiarTextoParaWord($acompananteInfo['nombre']),
                ['name' => 'Arial', 'size' => 11],
                ['alignment' => Jc::BOTH, 'spaceAfter' => 60]
            );

            if (!empty($acompananteInfo['cargo'])) {
                $section->addText(
                    'Cargo / Relación: ' . $this->limpiarTextoParaWord($acompananteInfo['cargo']),
                    ['name' => 'Arial', 'size' => 11],
                    ['alignment' => Jc::BOTH, 'spaceAfter' => 160]
                );
            }
        } else {
            $section->addText(
                'ACOMPAÑANTE DEL/LA TRABAJADOR/A: El/la trabajador/a no se hizo acompañar en esta diligencia.',
                ['name' => 'Arial', 'size' => 11],
                ['alignment' => Jc::BOTH, 'spaceAfter' => 160]
            );
        }

        // Pruebas
        $section->addText(
            'PRUEBAS APORTADAS:',
            ['bold' => true, 'name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 60]
        );

        if ($diligencia->pruebas_aportadas) {
            $textoPruebas = !empty($diligencia->descripcion_pruebas)
                ? $this->limpiarTextoParaWord($diligencia->descripcion_pruebas)
                : 'El/la trabajador/a aportó pruebas durante la diligencia a través de la plataforma.';

            $section->addText(
                $textoPruebas,
                ['name' => 'Arial', 'size' => 11],
                ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
            );
        } else {
            $section->addText(
                'El/la trabajador/a no aportó pruebas adicionales durante esta diligencia.',
                ['name' => 'Arial', 'size' => 11, 'italic' => true],
                ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
            );
        }
    }

    /**
     * Agrega el cierre de la diligencia
     */
    protected function agregarCierre($section, $diligencia, $trabajador): void
    {
        $section->addText(
            'V. CIERRE DE LA DILIGENCIA',
            ['bold' => true, 'size' => 11, 'name' => 'Arial'],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'spaceBefore' => 80]
        );

        $fechaCierre = $diligencia->tiempo_limite
            ? \Carbon\Carbon::parse($diligencia->tiempo_limite)->timezone('America/Bogota')
            : now()->timezone('America/Bogota');

        $horaFin    = $fechaCierre->format('h:i A');
        $fechaTexto = $this->convertirFechaATexto($fechaCierre);

        $nombreTrab = $this->limpiarTextoParaWord($trabajador->nombre_completo ?? '');
        $esFemenino = ($trabajador->genero === 'femenino');
        $art        = $esFemenino ? 'la' : 'el';

        $textoCierre =
            "Se da por terminada la presente Diligencia Administrativa a las {$horaFin} del {$fechaTexto}. " .
            "{$nombreTrab} participó en calidad de {$art} investigado" . ($esFemenino ? 'a' : '') .
            " a través de la plataforma digital CES Legal, ejerciendo su derecho de defensa y contradicción, " .
            "respondió las preguntas formuladas y manifestó lo que tuvo a bien en su defensa. " .
            "Se le informa que la empresa procederá al análisis jurídico de los hechos, los descargos " .
            "presentados y las pruebas aportadas, y que se le notificará oportunamente la decisión que se adopte. " .
            "Así mismo, se le recuerda su derecho a impugnar dicha decisión dentro de los tres (3) días " .
            "hábiles siguientes a su notificación, conforme al Reglamento Interno de Trabajo y la " .
            "legislación laboral vigente.";

        $section->addText(
            $textoCierre,
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 400]
        );
    }

    /**
     * Agrega la sección de firmas adaptada al contexto digital
     */
    protected function agregarFirmas($section, $trabajador, $diligencia): void
    {
        // Firma del representante del empleador (física — se imprime para firmar)
        $table = $section->addTable([
            'borderSize' => 0,
            'width'      => 100 * 50,
        ]);

        $table->addRow();
        $table->addCell(4500)->addText(
            '_____________________________',
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::CENTER]
        );
        $table->addCell(1000)->addText('');
        $table->addCell(4500)->addText(
            '[ AUTENTICACIÓN DIGITAL ]',
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::CENTER]
        );

        $table->addRow();
        $table->addCell(4500)->addText(
            'Representante del Empleador',
            ['bold' => true, 'name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::CENTER]
        );
        $table->addCell(1000)->addText('');
        $table->addCell(4500)->addText(
            $this->limpiarTextoParaWord($trabajador->nombre_completo ?? ''),
            ['bold' => true, 'name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::CENTER]
        );

        $table->addRow();
        $table->addCell(4500)->addText(
            $this->limpiarTextoParaWord($diligencia->proceso->empresa->razon_social ?? 'Empleador'),
            ['name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::CENTER]
        );
        $table->addCell(1000)->addText('');
        $table->addCell(4500)->addText(
            $this->limpiarTextoParaWord($trabajador->tipo_documento ?? '') . ' N.° ' .
            $this->limpiarTextoParaWord($trabajador->numero_documento ?? ''),
            ['name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::CENTER]
        );

        // Nota sobre la autenticación digital del trabajador
        $section->addText(
            '',
            ['name' => 'Arial', 'size' => 11],
            ['spaceAfter' => 200]
        );

        $section->addText(
            'NOTA SOBRE LA PARTICIPACIÓN DIGITAL DEL TRABAJADOR:',
            ['bold' => true, 'name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 60]
        );

        $otpVerificado = $diligencia->otp_verificado_en
            ? \Carbon\Carbon::parse($diligencia->otp_verificado_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'registrado en el sistema';

        $section->addText(
            'La firma física del/la trabajador/a no aplica en diligencias realizadas a través de la plataforma '.
            'virtual CES Legal. Su identidad fue verificada mediante código OTP (verificado el '.
            $otpVerificado . ') y doble verificación facial mediante inteligencia artificial, '.
            'lo que constituye su participación válida y consentida en la diligencia, conforme a '.
            'lo dispuesto en la Ley 527 de 1999 y el Decreto 2364 de 2012 sobre firma electrónica. '.
            'Los registros digitales de autenticación reposan en los servidores de CES Legal y '.
            'están disponibles como prueba en el expediente digital del proceso.',
            ['name' => 'Arial', 'size' => 10, 'italic' => true],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
        );

        // Pie de documento
        $section->addText(
            'Documento generado por la plataforma CES Legal | www.ceslegal.com.co',
            ['name' => 'Arial', 'size' => 9, 'italic' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
        );

        $fechaGeneracion = now()->timezone('America/Bogota')->format('d/m/Y h:i A');
        $section->addText(
            "Generado el {$fechaGeneracion} | Proceso N.° " .
            $this->limpiarTextoParaWord($diligencia->proceso->codigo ?? ''),
            ['name' => 'Arial', 'size' => 9, 'italic' => true],
            ['alignment' => Jc::CENTER]
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    protected function limpiarTextoParaWord(?string $texto): string
    {
        if (empty($texto)) return '';

        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = strip_tags($texto);
        $texto = preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $texto
        );
        $texto = preg_replace('/\s+/', ' ', $texto);

        return trim($texto);
    }

    protected function obtenerInfoAcompanante($diligencia): array
    {
        $preguntas = $diligencia->preguntas()->with('respuesta')->ordenadas()->get();

        $deseaAcompanante   = false;
        $nombreAcompanante  = '';
        $cargoAcompanante   = '';

        foreach ($preguntas as $pregunta) {
            $preguntaTexto = strtolower($pregunta->pregunta);
            $respuesta     = $pregunta->respuesta?->respuesta ?? '';

            if (str_contains($preguntaTexto, 'desea hacerse acompañar')) {
                $r = strtolower(trim($respuesta));
                $deseaAcompanante = str_contains($r, 'sí') || str_contains($r, 'si') || str_contains($r, 'yes');
            }

            if (str_contains($preguntaTexto, 'nombre completo de la persona que lo acompañará')) {
                $nombreAcompanante = trim($respuesta);
                if (strtolower($nombreAcompanante) === 'no aplica') $nombreAcompanante = '';
            }

            if (str_contains($preguntaTexto, 'cargo o relación de la persona que lo acompañará')) {
                $cargoAcompanante = trim($respuesta);
                if (strtolower($cargoAcompanante) === 'no aplica') $cargoAcompanante = '';
            }
        }

        return [
            'tiene_acompanante' => $deseaAcompanante && !empty($nombreAcompanante),
            'nombre'            => $nombreAcompanante,
            'cargo'             => $cargoAcompanante,
        ];
    }

    private string $libreOfficePath;

    protected function guardarDocumento($proceso): string
    {
        $directory = storage_path('app/actas_descargos');

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = 'diligencia_descargos_' . $proceso->codigo . '_' . time() . '.docx';
        $filepath = $directory . '/' . $filename;

        $objWriter = IOFactory::createWriter($this->phpWord, 'Word2007');
        $objWriter->save($filepath);

        return $filename;
    }

    protected function convertirFechaATexto($fecha): string
    {
        $dia  = $fecha->day;
        $mes  = $fecha->month;
        $año  = $fecha->year;

        return $this->numeroATexto($dia) . " ({$dia}) de " .
               $this->obtenerMesTexto($mes) . " del año " .
               $this->numeroATexto($año) . " ({$año})";
    }

    protected function numeroATexto($numero): string
    {
        $unidades  = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $decenas   = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $especiales = [
            10 => 'diez', 11 => 'once',    12 => 'doce',      13 => 'trece',
            14 => 'catorce', 15 => 'quince', 16 => 'dieciséis', 17 => 'diecisiete',
            18 => 'dieciocho', 19 => 'diecinueve',
        ];

        if ($numero < 10)  return $unidades[$numero];
        if ($numero < 20)  return $especiales[$numero] ?? '';
        if ($numero < 100) {
            $dec = intdiv($numero, 10);
            $uni = $numero % 10;
            return $decenas[$dec] . ($uni > 0 ? ' y ' . $unidades[$uni] : '');
        }
        if ($numero < 1000) {
            $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
                         'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
            $cent  = intdiv($numero, 100);
            $resto = $numero % 100;
            return $centenas[$cent] . ($resto > 0 ? ' ' . $this->numeroATexto($resto) : '');
        }
        if ($numero < 10000) {
            $mil   = intdiv($numero, 1000);
            $resto = $numero % 1000;
            return ($mil > 1 ? $this->numeroATexto($mil) . ' mil' : 'mil') .
                   ($resto > 0 ? ' ' . $this->numeroATexto($resto) : '');
        }
        return (string) $numero;
    }

    protected function obtenerMesTexto($mes): string
    {
        return [
            1 => 'enero', 2 => 'febrero',   3 => 'marzo',      4 => 'abril',
            5 => 'mayo',  6 => 'junio',     7 => 'julio',      8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ][$mes] ?? '';
    }

    public function convertirAPdf($docxPath): ?string
    {
        return null;
    }
}
