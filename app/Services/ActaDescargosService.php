<?php

namespace App\Services;

use App\Models\DiligenciaDescargo;
use Dompdf\Dompdf;
use Dompdf\Options;
use Dompdf\Adapter\CPDF as CpdfAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ActaDescargosService
{
    // ──────────────────────────────────────────────────────────────────────────
    // Helper de género
    // ──────────────────────────────────────────────────────────────────────────

    private function g(object $trabajador): array
    {
        $f = strtolower($trabajador->genero ?? '') === 'femenino';
        return [
            'f'            => $f,
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
            $g          = $this->g($trabajador);

            // ── Token de verificación: uno por diligencia, permanente ────────
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

            // ── Fechas y textos ──────────────────────────────────────────────
            $horaInicio = $diligencia->primer_acceso_en
                ? \Carbon\Carbon::parse($diligencia->primer_acceso_en)->timezone('America/Bogota')->format('h:i A')
                : now()->timezone('America/Bogota')->format('h:i A');

            $fechaBase  = $diligencia->fecha_diligencia
                ? \Carbon\Carbon::parse($diligencia->fecha_diligencia)->timezone('America/Bogota')
                : now()->timezone('America/Bogota');
            $fechaTexto = $this->convertirFechaATexto($fechaBase);

            $municipio    = $this->limpiarTextoParaWord($empresa->ciudad       ?? 'Colombia');
            $departamento = $this->limpiarTextoParaWord($empresa->departamento ?? '');
            $razonSocial  = $this->limpiarTextoParaWord($empresa->razon_social ?? '');
            $empresaNombre = $razonSocial ?: 'Empleador';
            $nit          = $this->limpiarTextoParaWord($empresa->nit          ?? '');
            $nombreTrab   = $this->limpiarTextoParaWord($trabajador->nombre_completo  ?? '');
            $tipoDoc      = $this->limpiarTextoParaWord($trabajador->tipo_documento   ?? 'C.C.');
            $numDoc       = $this->limpiarTextoParaWord($trabajador->numero_documento ?? '');
            $cargo        = $this->limpiarTextoParaWord($trabajador->cargo ?? '');

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

            // ── OTP y timestamps ─────────────────────────────────────────────
            $otpCanal    = $this->limpiarTextoParaWord($diligencia->otp_canal    ?? 'correo electrónico');
            $otpEnviadoA = $this->limpiarTextoParaWord($diligencia->otp_enviado_a ?? '');
            $otpVerif    = $diligencia->otp_verificado_en
                ? \Carbon\Carbon::parse($diligencia->otp_verificado_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
                : 'No registrado';

            $disclaimerEn = $diligencia->disclaimer_aceptado_en
                ? \Carbon\Carbon::parse($diligencia->disclaimer_aceptado_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
                : 'No registrado';

            $fotoInicioEn = $diligencia->foto_inicio_en
                ? \Carbon\Carbon::parse($diligencia->foto_inicio_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
                : 'No registrada';

            $fotoFinEn = $diligencia->foto_fin_en
                ? \Carbon\Carbon::parse($diligencia->foto_fin_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
                : 'No registrada';

            $ipAcceso = $this->limpiarTextoParaWord($diligencia->ip_acceso ?? 'No registrada');

            $primerAcceso = $diligencia->primer_acceso_en
                ? \Carbon\Carbon::parse($diligencia->primer_acceso_en)->timezone('America/Bogota')->format('d/m/Y h:i A')
                : 'No registrado';

            // ── Fotos como base64 data URI ───────────────────────────────────
            $fotoInicioBase64 = null;
            if (!empty($diligencia->foto_inicio_path)) {
                $absPath = Storage::path($diligencia->foto_inicio_path);
                if (file_exists($absPath)) {
                    $mime = mime_content_type($absPath) ?: 'image/jpeg';
                    $fotoInicioBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absPath));
                }
            }

            $fotoFinBase64 = null;
            if (!empty($diligencia->foto_fin_path)) {
                $absPath = Storage::path($diligencia->foto_fin_path);
                if (file_exists($absPath)) {
                    $mime = mime_content_type($absPath) ?: 'image/jpeg';
                    $fotoFinBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absPath));
                }
            }

            // ── QR como base64 data URI ──────────────────────────────────────
            $qrBase64 = null;
            try {
                $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=10&ecc=H&data=' . rawurlencode($urlVerificacion);
                $context  = stream_context_create([
                    'http' => ['timeout' => 10, 'user_agent' => 'CESLegal/1.0'],
                    'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
                ]);
                $qrPng = @file_get_contents($qrApiUrl, false, $context);
                if ($qrPng && strlen($qrPng) >= 100) {
                    $qrBase64 = 'data:image/png;base64,' . base64_encode($qrPng);
                }
            } catch (\Throwable $e) {
                Log::warning('ActaDescargosService: no se pudo obtener QR', ['error' => $e->getMessage()]);
            }

            // ── Hechos ───────────────────────────────────────────────────────
            $hechos = $this->limpiarTextoParaWord($proceso->hechos ?? '');

            // ── Preguntas/respuestas ─────────────────────────────────────────
            $preguntas = $diligencia->preguntas()
                ->with('respuesta')
                ->ordenadas()
                ->get();

            // ── Acompañante ──────────────────────────────────────────────────
            $acompananteInfo = $this->obtenerInfoAcompanante($diligencia);

            // ── Pruebas ──────────────────────────────────────────────────────
            $archivosEvidencia  = $diligencia->archivos_evidencia ?? [];
            $tieneEvidencias    = $diligencia->pruebas_aportadas || !empty($archivosEvidencia);
            $descripcionPruebas = $diligencia->descripcion_pruebas ?? '';

            // ── Cierre ───────────────────────────────────────────────────────
            $fechaCierre = $diligencia->tiempo_limite
                ? \Carbon\Carbon::parse($diligencia->tiempo_limite)->timezone('America/Bogota')
                : now()->timezone('America/Bogota');

            $horaFin          = $fechaCierre->format('h:i A');
            $fechaCierreTexto = $this->convertirFechaATexto($fechaCierre);

            $textoCierre =
                "Se da por terminada la presente Diligencia Administrativa a las {$horaFin} del {$fechaCierreTexto}. " .
                "{$nombreTrab} participó en calidad de {$g['investigado']} a través de la plataforma digital CES Legal, " .
                "ejerciendo su derecho de defensa y contradicción, respondió las preguntas formuladas " .
                "y manifestó lo que tuvo a bien en su defensa. " .
                "Se le informa que la empresa procederá al análisis jurídico de los hechos, los descargos " .
                "presentados y las pruebas aportadas, y que se le notificará oportunamente la decisión que se adopte. " .
                "Así mismo, se le recuerda su derecho a impugnar dicha decisión dentro de los tres (3) días " .
                "hábiles siguientes a su notificación, conforme al Reglamento Interno de Trabajo y la " .
                "legislación laboral vigente.";

            $fechaGeneracion = now()->timezone('America/Bogota')->format('d/m/Y h:i:s A');

            // ── Renderizar vista Blade ───────────────────────────────────────
            $html = view('actas.descargos', compact(
                'proceso', 'trabajador', 'empresa', 'diligencia', 'g',
                'apertura', 'otpCanal', 'otpEnviadoA', 'otpVerif', 'disclaimerEn',
                'fotoInicioBase64', 'fotoInicioEn', 'fotoFinBase64', 'fotoFinEn',
                'ipAcceso', 'primerAcceso', 'hechos', 'preguntas',
                'acompananteInfo', 'tieneEvidencias', 'descripcionPruebas', 'archivosEvidencia',
                'textoCierre', 'nombreTrab', 'tipoDoc', 'numDoc', 'cargo',
                'empresaNombre', 'urlVerificacion', 'token', 'hash', 'qrBase64', 'fechaGeneracion'
            ))->render();

            // ── Generar PDF con DomPDF ───────────────────────────────────────
            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultPaperSize', 'letter');
            $options->set('isFontSubsettingEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();

            // ── Cifrado: solo lectura e impresión (sin edición) ──────────────
            $canvas = $dompdf->getCanvas();
            if ($canvas instanceof CpdfAdapter) {
                $ownerPass = substr(hash('sha256', config('app.key') . $diligencia->id . 'acta_descargos'), 0, 32);
                // '' = sin contraseña para abrir; $ownerPass bloquea edición; ['print'] = solo imprimir
                $canvas->get_cpdf()->setEncryption('', $ownerPass, ['print']);
            }

            $filename = $this->guardarDocumento($proceso, $dompdf->output());

            return [
                'success'          => true,
                'filename'         => $filename,
                'path'             => storage_path('app/actas_descargos/' . $filename),
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

    protected function guardarDocumento($proceso, string $pdfOutput): string
    {
        $directory = storage_path('app/actas_descargos');

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = 'diligencia_descargos_' . $proceso->codigo . '_' . time() . '.pdf';
        $filepath = $directory . '/' . $filename;

        file_put_contents($filepath, $pdfOutput);

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
}
