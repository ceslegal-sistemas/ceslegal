<?php

namespace App\Services;

use App\Models\DiligenciaDescargo;
use App\Models\ProcesoDisciplinario;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ActaDescargosService
{
    protected PhpWord $phpWord;
    private string $libreOfficePath;

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

    // ──────────────────────────────────────────────────────────────────────────
    // Helper de género
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Devuelve formas genéricas resueltas según el género del trabajador,
     * tanto en minúscula como en mayúscula.
     */
    private function g(object $trabajador): array
    {
        $f = strtolower($trabajador->genero ?? '') === 'femenino';
        return [
            'f'            => $f,
            // minúsculas
            'el'           => $f ? 'la'           : 'el',
            'del'          => $f ? 'de la'         : 'del',
            'al'           => $f ? 'a la'          : 'al',
            'un'           => $f ? 'una'           : 'un',
            'trabajador'   => $f ? 'trabajadora'   : 'trabajador',
            'investigado'  => $f ? 'investigada'   : 'investigado',
            'identificado' => $f ? 'identificada'  : 'identificado',
            'acompanado'   => $f ? 'acompañada'    : 'acompañado',
            'este'         => $f ? 'esta'          : 'este',
            'ninguno'      => $f ? 'ninguna'       : 'ninguno',
            // mayúsculas (para encabezados de sección)
            'EL'           => $f ? 'LA'            : 'EL',
            'DEL'          => $f ? 'DE LA'         : 'DEL',
            'AL'           => $f ? 'A LA'          : 'AL',
            'TRABAJADOR'   => $f ? 'TRABAJADORA'   : 'TRABAJADOR',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Método principal
    // ──────────────────────────────────────────────────────────────────────────

    public function generarActaDescargos(DiligenciaDescargo $diligencia): array
    {
        try {
            $proceso    = $diligencia->proceso;
            $trabajador = $proceso->trabajador;
            $empresa    = $proceso->empresa;

            // ── Token de verificación: uno por diligencia, permanente ────────
            // Si ya existe (descarga múltiple) se reutiliza el mismo token.
            if ($diligencia->verificacion_token) {
                $token = $diligencia->verificacion_token;
                $hash  = $diligencia->verificacion_hash;
            } else {
                $token = Str::uuid()->toString();
                $hash  = hash('sha256',
                    $diligencia->id . '|' .
                    ($proceso->codigo ?? '') . '|' .
                    ($trabajador->numero_documento ?? '') . '|' .
                    ($diligencia->otp_verificado_en?->timestamp ?? '') . '|' .
                    config('app.key')
                );
                $diligencia->update([
                    'verificacion_token'       => $token,
                    'verificacion_hash'        => $hash,
                    'verificacion_generada_en' => now(),
                ]);
            }

            $urlVerificacion = 'https://ceslegal2.renbel.com.co/verificar/' . $token;

            $section = $this->phpWord->addSection([
                'marginLeft'   => 1440,
                'marginRight'  => 1440,
                'marginTop'    => 1440,
                'marginBottom' => 1440,
            ]);

            $this->agregarTitulo($section, $proceso);
            $this->agregarEncabezado($section, $diligencia, $proceso, $trabajador, $empresa);
            $this->agregarConstanciaAutenticacion($section, $diligencia, $trabajador);
            $this->agregarHechos($section, $proceso, $trabajador);
            $this->agregarPreguntasRespuestas($section, $diligencia, $trabajador);
            $this->agregarInformacionAdicional($section, $diligencia, $trabajador);
            $this->agregarCierre($section, $diligencia, $trabajador);
            $this->agregarFirmas($section, $trabajador, $diligencia);
            $this->agregarQrVerificacion($section, $urlVerificacion, $token, $hash, $diligencia);

            $filename = $this->guardarDocumento($proceso);

            return [
                'success'  => true,
                'filename' => $filename,
                'path'     => storage_path('app/actas_descargos/' . $filename),
                'verificacion_url' => $urlVerificacion,
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

    // ──────────────────────────────────────────────────────────────────────────
    // Secciones del documento
    // ──────────────────────────────────────────────────────────────────────────

    protected function agregarTitulo($section, $proceso): void
    {
        $section->addText(
            'DILIGENCIA ADMINISTRATIVA DE APERTURA DE INVESTIGACIÓN DISCIPLINARIA',
            ['bold' => true, 'size' => 13, 'name' => 'Arial'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 80]
        );

        $section->addText(
            '(Antes denominada: Acta de Descargos)',
            ['italic' => true, 'size' => 10, 'name' => 'Arial'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 80]
        );

        $section->addText(
            'Proceso Disciplinario N.° ' . $this->limpiarTextoParaWord($proceso->codigo ?? ''),
            ['bold' => true, 'size' => 11, 'name' => 'Arial'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 240]
        );
    }

    protected function agregarEncabezado($section, $diligencia, $proceso, $trabajador, $empresa): void
    {
        $municipio    = $this->limpiarTextoParaWord($empresa->ciudad       ?? 'Colombia');
        $departamento = $this->limpiarTextoParaWord($empresa->departamento ?? '');
        $razonSocial  = $this->limpiarTextoParaWord($empresa->razon_social ?? '');
        $nit          = $this->limpiarTextoParaWord($empresa->nit          ?? '');
        $nombreTrab   = $this->limpiarTextoParaWord($trabajador->nombre_completo  ?? '');
        $tipoDoc      = $this->limpiarTextoParaWord($trabajador->tipo_documento   ?? 'C.C.');
        $numDoc       = $this->limpiarTextoParaWord($trabajador->numero_documento ?? '');
        $cargo        = $this->limpiarTextoParaWord($trabajador->cargo ?? '');
        $g            = $this->g($trabajador);

        $horaInicio = $diligencia->primer_acceso_en
            ? \Carbon\Carbon::parse($diligencia->primer_acceso_en)->timezone('America/Bogota')->format('h:i A')
            : now()->timezone('America/Bogota')->format('h:i A');

        $fechaBase  = $diligencia->fecha_diligencia
            ? \Carbon\Carbon::parse($diligencia->fecha_diligencia)->timezone('America/Bogota')
            : now()->timezone('America/Bogota');
        $fechaTexto = $this->convertirFechaATexto($fechaBase);

        $ubicacion = trim($municipio . ($departamento ? ', ' . $departamento : ''));

        $apertura =
            "En {$ubicacion}, el {$fechaTexto}, siendo las {$horaInicio}, " .
            "se dio inicio a la presente Diligencia Administrativa de Apertura de Investigación Disciplinaria " .
            "dentro del proceso disciplinario N.° {$proceso->codigo}, adelantado por {$razonSocial} " .
            "con NIT {$nit}, en ejercicio de su potestad disciplinaria interna conforme al Reglamento " .
            "Interno de Trabajo y la legislación laboral vigente. " .
            "La presente diligencia fue gestionada a través de la plataforma tecnológica CES Legal " .
            "(www.ceslegal.co), empresa que actúa exclusivamente como proveedora del servicio tecnológico " .
            "de gestión disciplinaria y no tiene participación, intervención ni responsabilidad alguna " .
            "en las decisiones disciplinarias adoptadas por el empleador. " .
            "Participó {$g['el']} {$g['trabajador']} {$nombreTrab}, " .
            "{$g['identificado']} con {$tipoDoc} N.° {$numDoc}, " .
            "con cargo de {$cargo}, " .
            "quien accedió a la plataforma digital, verificó su identidad de forma electrónica " .
            "y rindió sus descargos y explicaciones en relación con los siguientes hechos:";

        $section->addText(
            $apertura,
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
        );
    }

    protected function agregarConstanciaAutenticacion($section, $diligencia, $trabajador): void
    {
        $g = $this->g($trabajador);

        $section->addText(
            'I. CONSTANCIA DE AUTENTICACIÓN DIGITAL',
            ['bold' => true, 'size' => 11, 'name' => 'Arial'],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'spaceBefore' => 120]
        );

        $section->addText(
            "La identidad {$g['del']} {$g['trabajador']} fue verificada mediante los siguientes " .
            "mecanismos de seguridad de la plataforma CES Legal, conforme a lo previsto en la " .
            "Ley 527 de 1999 (Comercio Electrónico) y el Decreto 2364 de 2012 (Firma Electrónica):",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 100]
        );

        // ── 1. OTP ───────────────────────────────────────────────────────────
        $otpCanal    = $this->limpiarTextoParaWord($diligencia->otp_canal    ?? 'correo electrónico');
        $otpEnviadoA = $this->limpiarTextoParaWord($diligencia->otp_enviado_a ?? '');
        $otpVerif    = $diligencia->otp_verificado_en
            ? \Carbon\Carbon::parse($diligencia->otp_verificado_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrado';

        $section->addText(
            "1. Código de verificación OTP (One-Time Password): enviado por {$otpCanal}" .
            ($otpEnviadoA ? " al destinatario {$otpEnviadoA}" : '') .
            " y verificado satisfactoriamente el {$otpVerif}.",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 80]
        );

        // ── 2. Declaración ───────────────────────────────────────────────────
        $disclaimerEn = $diligencia->disclaimer_aceptado_en
            ? \Carbon\Carbon::parse($diligencia->disclaimer_aceptado_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrado';

        $section->addText(
            "2. Declaración de participación voluntaria: {$g['el']} {$g['trabajador']} aceptó " .
            "la declaración de responsabilidad y participación voluntaria en la plataforma el {$disclaimerEn}.",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 80]
        );

        // ── 3. Foto inicio ───────────────────────────────────────────────────
        $fotoInicioEn = $diligencia->foto_inicio_en
            ? \Carbon\Carbon::parse($diligencia->foto_inicio_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrada';

        $section->addText(
            "3. Verificación facial al inicio de la diligencia: fotografía capturada el {$fotoInicioEn} " .
            "mediante reconocimiento facial con inteligencia artificial, registrada en el sistema con fines de trazabilidad.",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 60]
        );

        if (!empty($diligencia->foto_inicio_path)) {
            $absPath = Storage::path($diligencia->foto_inicio_path);
            if (file_exists($absPath)) {
                $section->addImage($absPath, [
                    'width'         => 180,
                    'height'        => 135,
                    'alignment'     => Jc::CENTER,
                    'wrappingStyle' => 'inline',
                ]);
                $section->addText(
                    "Fotografía de verificación — inicio de la diligencia | {$fotoInicioEn}",
                    ['name' => 'Arial', 'size' => 9, 'italic' => true],
                    ['alignment' => Jc::CENTER, 'spaceAfter' => 100]
                );
            }
        }

        // ── 4. Foto fin ──────────────────────────────────────────────────────
        $fotoFinEn = $diligencia->foto_fin_en
            ? \Carbon\Carbon::parse($diligencia->foto_fin_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrada';

        $section->addText(
            "4. Verificación facial al cierre de la diligencia: fotografía capturada el {$fotoFinEn} " .
            "mediante reconocimiento facial con inteligencia artificial, registrada en el sistema con fines de trazabilidad.",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 60]
        );

        if (!empty($diligencia->foto_fin_path)) {
            $absPath = Storage::path($diligencia->foto_fin_path);
            if (file_exists($absPath)) {
                $section->addImage($absPath, [
                    'width'         => 180,
                    'height'        => 135,
                    'alignment'     => Jc::CENTER,
                    'wrappingStyle' => 'inline',
                ]);
                $section->addText(
                    "Fotografía de verificación — cierre de la diligencia | {$fotoFinEn}",
                    ['name' => 'Arial', 'size' => 9, 'italic' => true],
                    ['alignment' => Jc::CENTER, 'spaceAfter' => 100]
                );
            }
        }

        // ── 5. IP ────────────────────────────────────────────────────────────
        $ip = $this->limpiarTextoParaWord($diligencia->ip_acceso ?? 'No registrada');

        $section->addText(
            "5. Dirección IP de acceso: {$ip}.",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 80]
        );

        // ── 6. Primer acceso ─────────────────────────────────────────────────
        $primerAcceso = $diligencia->primer_acceso_en
            ? \Carbon\Carbon::parse($diligencia->primer_acceso_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrado';

        $section->addText(
            "6. Fecha y hora de ingreso a la plataforma: {$primerAcceso}.",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
        );
    }

    protected function agregarHechos($section, $proceso, $trabajador): void
    {
        $g = $this->g($trabajador);

        $section->addText(
            'II. HECHOS OBJETO DE LA INVESTIGACIÓN',
            ['bold' => true, 'size' => 11, 'name' => 'Arial'],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'spaceBefore' => 80]
        );

        $section->addText(
            "Se le informó {$g['al']} {$g['trabajador']} sobre los hechos que dieron origen " .
            "al presente proceso disciplinario:",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 80]
        );

        $section->addText(
            $this->limpiarTextoParaWord($proceso->hechos),
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
        );
    }

    protected function agregarPreguntasRespuestas($section, $diligencia, $trabajador): void
    {
        $g = $this->g($trabajador);

        $section->addText(
            'III. DESARROLLO DE LA DILIGENCIA',
            ['bold' => true, 'size' => 11, 'name' => 'Arial'],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'spaceBefore' => 80]
        );

        $section->addText(
            "A continuación se transcriben las preguntas formuladas {$g['al']} {$g['trabajador']} " .
            "y las respuestas que {$g['este']} suministró de manera escrita a través de la plataforma CES Legal:",
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 120]
        );

        $preguntas = $diligencia->preguntas()
            ->with('respuesta')
            ->ordenadas()
            ->get();

        if ($preguntas->isEmpty()) {
            $section->addText(
                ucfirst("{$g['el']} {$g['trabajador']} no respondió {$g['ninguno']} pregunta durante la diligencia."),
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

                if (!empty($pregunta->respuesta->archivos_adjuntos)) {
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

    protected function agregarInformacionAdicional($section, $diligencia, $trabajador): void
    {
        $g = $this->g($trabajador);

        $section->addText(
            'IV. INFORMACIÓN ADICIONAL',
            ['bold' => true, 'size' => 11, 'name' => 'Arial'],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'spaceBefore' => 80]
        );

        // Acompañante
        $acompananteInfo = $this->obtenerInfoAcompanante($diligencia);

        if ($acompananteInfo['tiene_acompanante']) {
            $section->addText(
                "ACOMPAÑANTE {$g['DEL']} {$g['TRABAJADOR']}:",
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
                "ACOMPAÑANTE {$g['DEL']} {$g['TRABAJADOR']}: {$g['el']} {$g['trabajador']} " .
                "no se hizo {$g['acompanado']} en esta diligencia.",
                ['name' => 'Arial', 'size' => 11],
                ['alignment' => Jc::BOTH, 'spaceAfter' => 160]
            );
        }

        // Pruebas — revisar tanto el boolean como los archivos efectivamente adjuntados
        $archivosEvidencia = $diligencia->archivos_evidencia ?? [];
        $tieneEvidencias   = $diligencia->pruebas_aportadas || !empty($archivosEvidencia);

        $section->addText(
            'PRUEBAS APORTADAS:',
            ['bold' => true, 'name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 60]
        );

        if ($tieneEvidencias) {
            if (!empty($diligencia->descripcion_pruebas)) {
                $section->addText(
                    $this->limpiarTextoParaWord($diligencia->descripcion_pruebas),
                    ['name' => 'Arial', 'size' => 11],
                    ['alignment' => Jc::BOTH, 'spaceAfter' => 80]
                );
            } else {
                $section->addText(
                    ucfirst("{$g['el']} {$g['trabajador']} adjuntó los siguientes archivos como prueba durante la diligencia:"),
                    ['name' => 'Arial', 'size' => 11],
                    ['alignment' => Jc::BOTH, 'spaceAfter' => 60]
                );
            }

            if (!empty($archivosEvidencia)) {
                foreach ($archivosEvidencia as $archivo) {
                    $nombre  = $this->limpiarTextoParaWord($archivo['nombre'] ?? 'Archivo');
                    $kb      = isset($archivo['size']) ? round($archivo['size'] / 1024, 1) . ' KB' : '';
                    $path    = $archivo['path'] ?? null;
                    $label   = '  • ' . $nombre . ($kb ? '  (' . $kb . ')' : '');

                    if ($path) {
                        // Enlace descargable directo desde la plataforma
                        $url = Storage::disk('public')->url($path);
                        $section->addLink(
                            $url,
                            $label,
                            ['color' => '4f46e5', 'underline' => true, 'name' => 'Arial', 'size' => 11],
                            ['alignment' => Jc::BOTH, 'spaceAfter' => 40]
                        );
                    } else {
                        $section->addText(
                            $label,
                            ['name' => 'Arial', 'size' => 11],
                            ['alignment' => Jc::BOTH, 'spaceAfter' => 40]
                        );
                    }
                }
            }

            $section->addText('', ['name' => 'Arial', 'size' => 6], ['spaceAfter' => 160]);
        } else {
            $section->addText(
                ucfirst("{$g['el']} {$g['trabajador']} no aportó pruebas adicionales durante esta diligencia."),
                ['name' => 'Arial', 'size' => 11, 'italic' => true],
                ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
            );
        }
    }

    protected function agregarCierre($section, $diligencia, $trabajador): void
    {
        $g = $this->g($trabajador);

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

        $textoCierre =
            "Se da por terminada la presente Diligencia Administrativa a las {$horaFin} del {$fechaTexto}. " .
            "{$nombreTrab} participó en calidad de {$g['investigado']} a través de la plataforma digital CES Legal, " .
            "ejerciendo su derecho de defensa y contradicción, respondió las preguntas formuladas " .
            "y manifestó lo que tuvo a bien en su defensa. " .
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
     * Firmas: empleador con línea física + trabajador con certificado de verificación digital.
     */
    protected function agregarFirmas($section, $trabajador, $diligencia): void
    {
        $g          = $this->g($trabajador);
        $nombreTrab = $this->limpiarTextoParaWord($trabajador->nombre_completo    ?? '');
        $tipoDoc    = $this->limpiarTextoParaWord($trabajador->tipo_documento     ?? 'C.C.');
        $numDoc     = $this->limpiarTextoParaWord($trabajador->numero_documento   ?? '');
        $cargo      = $this->limpiarTextoParaWord($trabajador->cargo              ?? '');
        $empresa    = $this->limpiarTextoParaWord($diligencia->proceso->empresa->razon_social ?? 'Empleador');

        $otpVerif = $diligencia->otp_verificado_en
            ? \Carbon\Carbon::parse($diligencia->otp_verificado_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrado';
        $fotoInicioEn = $diligencia->foto_inicio_en
            ? \Carbon\Carbon::parse($diligencia->foto_inicio_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrada';
        $fotoFinEn = $diligencia->foto_fin_en
            ? \Carbon\Carbon::parse($diligencia->foto_fin_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
            : 'No registrada';
        $ip = $this->limpiarTextoParaWord($diligencia->ip_acceso ?? 'No registrada');

        // ── Tabla de dos columnas ─────────────────────────────────────────────
        $table = $section->addTable([
            'borderSize' => 0,
            'width'      => 100 * 50,
        ]);

        // Fila 1: encabezados de columna
        $table->addRow();

        $celdaEmp = $table->addCell(4500, ['borderSize' => 0]);
        $celdaEmp->addText(
            'EMPLEADOR',
            ['bold' => true, 'name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
        );

        $table->addCell(800)->addText('');

        $celdaTrab = $table->addCell(4500, ['borderSize' => 0]);
        $celdaTrab->addText(
            strtoupper($g['trabajador']),
            ['bold' => true, 'name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
        );

        // Fila 2: línea de firma física | Certificado de verificación digital
        $table->addRow();

        // — Columna empleador —
        $celdaFirma = $table->addCell(4500, [
            'borderSize' => 6,
            'borderColor' => 'AAAAAA',
        ]);
        $celdaFirma->addText(
            '',
            ['name' => 'Arial', 'size' => 11],
            ['spaceAfter' => 800]  // espacio para firmar físicamente
        );
        $celdaFirma->addText(
            '_________________________________',
            ['name' => 'Arial', 'size' => 11],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 80]
        );
        $celdaFirma->addText(
            'Firma del Representante Legal',
            ['name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
        );
        $celdaFirma->addText(
            $empresa,
            ['bold' => true, 'name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::CENTER]
        );

        $table->addCell(800)->addText('');

        // — Columna trabajador: certificado de verificación digital —
        $celdaDigital = $table->addCell(4500, [
            'borderSize'  => 6,
            'borderColor' => '999999',
        ]);

        $celdaDigital->addText(
            'CERTIFICADO DE VERIFICACIÓN DIGITAL',
            ['bold' => true, 'name' => 'Arial', 'size' => 9],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 60]
        );
        $celdaDigital->addText(
            $nombreTrab,
            ['bold' => true, 'name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 20]
        );
        $celdaDigital->addText(
            "{$tipoDoc} N.° {$numDoc}",
            ['name' => 'Arial', 'size' => 9],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 20]
        );
        $celdaDigital->addText(
            "Cargo: {$cargo}",
            ['name' => 'Arial', 'size' => 9, 'italic' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 80]
        );

        // Línea separadora visual
        $celdaDigital->addText(
            '─────────────────────────────',
            ['name' => 'Arial', 'size' => 8],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 60]
        );

        $celdaDigital->addText(
            "Verificacion OTP:           {$otpVerif}",
            ['name' => 'Courier New', 'size' => 8],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 20]
        );
        $celdaDigital->addText(
            "Verificacion facial inicio: {$fotoInicioEn}",
            ['name' => 'Courier New', 'size' => 8],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 20]
        );
        $celdaDigital->addText(
            "Verificacion facial cierre: {$fotoFinEn}",
            ['name' => 'Courier New', 'size' => 8],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 20]
        );
        $celdaDigital->addText(
            "IP de acceso:               {$ip}",
            ['name' => 'Courier New', 'size' => 8],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 60]
        );

        $celdaDigital->addText(
            'Ley 527/1999 — Decreto 2364/2012',
            ['name' => 'Arial', 'size' => 8, 'italic' => true],
            ['alignment' => Jc::CENTER]
        );

        // ── Nota aclaratoria ─────────────────────────────────────────────────
        $section->addText('', ['name' => 'Arial', 'size' => 6], ['spaceAfter' => 160]);

        $section->addText(
            'NOTA: La plataforma CES Legal actúa únicamente como proveedora del servicio tecnológico ' .
            "de gestión disciplinaria. La identidad {$g['del']} {$g['trabajador']} fue verificada " .
            'mediante código OTP y doble reconocimiento facial con inteligencia artificial, lo que ' .
            'constituye su participación válida y consentida conforme a la Ley 527 de 1999 y el ' .
            'Decreto 2364 de 2012. Los registros de autenticación reposan en el expediente digital ' .
            'del proceso y están disponibles como prueba. CES Legal no asume responsabilidad alguna ' .
            'sobre las decisiones disciplinarias adoptadas por el empleador.',
            ['name' => 'Arial', 'size' => 9, 'italic' => true],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
        );

        // ── Pie de página ────────────────────────────────────────────────────
        $fechaGen = now()->timezone('America/Bogota')->format('d/m/Y h:i A');
        $section->addText(
            "Generado el {$fechaGen}   |   Proceso N.° " .
            $this->limpiarTextoParaWord($diligencia->proceso->codigo ?? '') .
            '   |   www.ceslegal.co',
            ['name' => 'Arial', 'size' => 8, 'italic' => true],
            ['alignment' => Jc::CENTER]
        );
    }

    /**
     * Sección final: bloque de verificación con QR code.
     * Va después de las firmas, en página separada si es posible.
     */
    protected function agregarQrVerificacion($section, string $url, string $token, string $hash, $diligencia): void
    {
        $section->addPageBreak();

        $section->addText(
            'VERIFICACIÓN DE AUTENTICIDAD DEL DOCUMENTO',
            ['bold' => true, 'size' => 13, 'name' => 'Arial'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 60]
        );

        $section->addText(
            'Escanee el código QR o ingrese la URL en su navegador para verificar la autenticidad de este documento.',
            ['name' => 'Arial', 'size' => 10, 'italic' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
        );

        // ── Tabla: QR a la izquierda, datos a la derecha ─────────────────────
        $table = $section->addTable(['borderSize' => 0, 'width' => 100 * 50]);
        $table->addRow();

        // Columna QR
        $celdaQr = $table->addCell(3600, ['borderSize' => 0]);

        $qrTempPath = null;
        try {
            // Generar QR como PNG binario y guardar en archivo temporal
            $qrPng = QrCode::format('png')
                ->size(280)
                ->margin(1)
                ->errorCorrection('H')
                ->generate($url);

            $qrTempPath = tempnam(sys_get_temp_dir(), 'ces_qr_') . '.png';
            file_put_contents($qrTempPath, $qrPng);

            $celdaQr->addImage($qrTempPath, [
                'width'         => 180,
                'height'        => 180,
                'alignment'     => Jc::CENTER,
                'wrappingStyle' => 'inline',
            ]);
        } catch (\Throwable $e) {
            // Captura tanto \Exception como \Error (ej: Class not found si el paquete
            // aún no fue instalado en el servidor con composer install)
            Log::warning('ActaDescargosService: no se pudo generar QR', ['error' => $e->getMessage()]);
            $celdaQr->addText(
                'Verificar en:',
                ['name' => 'Arial', 'size' => 9, 'italic' => true],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
            );
            $celdaQr->addLink(
                $url,
                $url,
                ['color' => '4f46e5', 'underline' => true, 'name' => 'Arial', 'size' => 8],
                ['alignment' => Jc::CENTER]
            );
        }

        // Spacer
        $table->addCell(400)->addText('');

        // Columna datos de verificación
        $celdaDatos = $table->addCell(6200, ['borderSize' => 0]);

        $celdaDatos->addText(
            'DATOS DE VERIFICACIÓN',
            ['bold' => true, 'name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 80]
        );

        $celdaDatos->addText(
            'URL de verificación:',
            ['bold' => true, 'name' => 'Arial', 'size' => 9],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 20]
        );
        $celdaDatos->addText(
            $url,
            ['name' => 'Courier New', 'size' => 8, 'color' => '4f46e5'],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 120]
        );

        $celdaDatos->addText(
            'Token de verificación:',
            ['bold' => true, 'name' => 'Arial', 'size' => 9],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 20]
        );
        $celdaDatos->addText(
            $token,
            ['name' => 'Courier New', 'size' => 7],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 120]
        );

        $celdaDatos->addText(
            'Hash SHA-256 del documento:',
            ['bold' => true, 'name' => 'Arial', 'size' => 9],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 20]
        );
        $celdaDatos->addText(
            $hash,
            ['name' => 'Courier New', 'size' => 7],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 120]
        );

        $fechaGen = now()->timezone('America/Bogota')->format('d/m/Y h:i:s A');
        $celdaDatos->addText(
            'Generado el: ' . $fechaGen,
            ['name' => 'Arial', 'size' => 9],
            ['alignment' => Jc::LEFT, 'spaceAfter' => 80]
        );

        $celdaDatos->addText(
            'Proceso N.° ' . $this->limpiarTextoParaWord($diligencia->proceso->codigo ?? ''),
            ['bold' => true, 'name' => 'Arial', 'size' => 9],
            ['alignment' => Jc::LEFT]
        );

        // Nota legal al pie de la sección QR
        $section->addText('', ['name' => 'Arial', 'size' => 4], ['spaceAfter' => 180]);

        $section->addText(
            '─────────────────────────────────────────────────────────────────────────────────────────',
            ['name' => 'Arial', 'size' => 8, 'color' => 'CCCCCC'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 60]
        );

        $section->addText(
            'Documento con firma electrónica simple conforme a la Ley 527 de 1999 y el Decreto 2364 de 2012 de la República de Colombia. ' .
            'La autenticidad de este documento puede ser verificada en cualquier momento mediante el código QR o la URL indicada. ' .
            'CES Legal actúa como proveedor tecnológico del servicio de gestión disciplinaria y no como parte en el proceso disciplinario.',
            ['name' => 'Arial', 'size' => 8, 'italic' => true, 'color' => '666666'],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 60]
        );

        $section->addText(
            'CES Legal · www.ceslegal.co · Plataforma de Gestión Disciplinaria Laboral',
            ['name' => 'Arial', 'size' => 8, 'color' => '888888'],
            ['alignment' => Jc::CENTER]
        );

        // Limpiar archivo temporal del QR
        if ($qrTempPath && file_exists($qrTempPath)) {
            @unlink($qrTempPath);
        }
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

        $deseaAcompanante  = false;
        $nombreAcompanante = '';
        $cargoAcompanante  = '';

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
        $dia = $fecha->day;
        $mes = $fecha->month;
        $año = $fecha->year;

        return $this->numeroATexto($dia) . " ({$dia}) de " .
               $this->obtenerMesTexto($mes) . " del año " .
               $this->numeroATexto($año) . " ({$año})";
    }

    protected function numeroATexto($numero): string
    {
        $unidades   = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $decenas    = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $especiales = [
            10 => 'diez',       11 => 'once',       12 => 'doce',        13 => 'trece',
            14 => 'catorce',    15 => 'quince',     16 => 'dieciséis',   17 => 'diecisiete',
            18 => 'dieciocho',  19 => 'diecinueve',
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
            1 => 'enero',   2 => 'febrero',    3 => 'marzo',     4 => 'abril',
            5 => 'mayo',    6 => 'junio',      7 => 'julio',     8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ][$mes] ?? '';
    }

    public function convertirAPdf($docxPath): ?string
    {
        return null;
    }
}
