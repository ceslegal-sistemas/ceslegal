<?php

namespace App\Services;

use App\Models\DiligenciaDescargo;
use App\Models\ProcesoDisciplinario;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;

class ActaDescargosService
{
    /**
     * Genera el acta de descargos en formato PDF
     */
    public function generarActaDescargos(DiligenciaDescargo $diligencia): array
    {
        try {
            $proceso   = $diligencia->proceso;
            $trabajador = $proceso->trabajador;
            $empresa   = $proceso->empresa;

            $html = $this->generarHTML($diligencia, $proceso, $trabajador, $empresa);

            $filepath = $this->guardarPDF($html, $proceso);

            return [
                'success'  => true,
                'filename' => basename($filepath),
                'path'     => $filepath,
            ];
        } catch (\Exception $e) {
            Log::error('Error generando acta de descargos', [
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
    // Generación HTML
    // ──────────────────────────────────────────────────────────────────────────

    private function generarHTML($diligencia, $proceso, $trabajador, $empresa): string
    {
        $encabezado         = $this->htmlEncabezado($diligencia, $proceso, $trabajador, $empresa);
        $hechos             = $this->htmlHechos($proceso);
        $preguntasRespuestas = $this->htmlPreguntasRespuestas($diligencia);
        $infoAdicional      = $this->htmlInformacionAdicional($diligencia);
        $cierre             = $this->htmlCierre($diligencia);
        $firmas             = $this->htmlFirmas($trabajador);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de Descargos</title>
    <style>
        @page { margin: 2.5cm 2.5cm 2.5cm 2.5cm; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
            text-align: justify;
        }
        h1 {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin: 0 0 20px 0;
        }
        h2 {
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            margin: 20px 0 10px 0;
        }
        p { margin: 0 0 10px 0; }
        .pregunta { font-weight: bold; margin: 12px 0 4px 0; }
        .respuesta { margin: 0 0 14px 0; padding-left: 20px; }
        .firmas {
            margin-top: 50px;
            width: 100%;
            border-collapse: collapse;
        }
        .firmas td {
            width: 50%;
            text-align: center;
            padding-top: 8px;
            font-size: 10pt;
        }
        .linea-firma {
            border-top: 1px solid #000;
            width: 200px;
            margin: 0 auto 4px auto;
        }
        .archivos { font-size: 10pt; font-style: italic; padding-left: 30px; }
    </style>
</head>
<body>
    <h1>ACTA DE DESCARGOS</h1>
    {$encabezado}
    {$hechos}
    {$preguntasRespuestas}
    {$infoAdicional}
    {$cierre}
    {$firmas}
</body>
</html>
HTML;
    }

    private function htmlEncabezado($diligencia, $proceso, $trabajador, $empresa): string
    {
        $municipio    = $this->e($empresa->ciudad ?? '');
        $departamento = $this->e($empresa->departamento ?? '');
        $razonSocial  = $this->e($empresa->razon_social ?? '');
        $nit          = $this->e($empresa->nit ?? '');
        $nombreTrab   = $this->e($trabajador->nombre_completo ?? '');
        $tipoDoc      = $this->e($trabajador->tipo_documento ?? '');
        $numDoc       = $this->e($trabajador->numero_documento ?? '');

        $horaInicio = $diligencia->primer_acceso_en
            ? \Carbon\Carbon::parse($diligencia->primer_acceso_en)->timezone('America/Bogota')->format('h:i A')
            : now()->timezone('America/Bogota')->format('h:i A');

        $fechaBase = $diligencia->fecha_diligencia
            ? \Carbon\Carbon::parse($diligencia->fecha_diligencia)->timezone('America/Bogota')
            : now()->timezone('America/Bogota');

        $fechaTexto = $this->convertirFechaATexto($fechaBase);

        $modalidad = match ($proceso->modalidad_descargos) {
            'presencial' => 'desde las oficinas administrativas de ' . $razonSocial,
            'virtual'    => 'a través del software virtual de descargos',
            'telefonico' => 'vía telefónica',
            default      => 'desde las oficinas administrativas de ' . $razonSocial,
        };

        $esFemenino = ($trabajador->genero === 'femenino');
        $genSufijo  = $esFemenino ? 'a' : 'o';
        $trabSufijo = $esFemenino ? 'a' : '';

        return "<p>En la ciudad de {$municipio}, {$departamento}, el {$fechaTexto}, siendo las {$horaInicio}, {$modalidad}, "
            . "se reunieron por una parte el representante legal de <strong>{$razonSocial}</strong> con NIT {$nit} "
            . "en representación del empleador y, por la otra <strong>{$nombreTrab}</strong>, "
            . "identificad{$genSufijo} con {$tipoDoc} N° {$numDoc}, "
            . "en su condición de trabajador{$trabSufijo} para que rinda sus descargos "
            . "y dé sus explicaciones acerca de los siguientes hechos:</p>";
    }

    private function htmlHechos($proceso): string
    {
        $hechos = $this->e($this->limpiarTexto($proceso->hechos));
        return "<p>{$hechos}</p>";
    }

    private function htmlPreguntasRespuestas($diligencia): string
    {
        $preguntas = $diligencia->preguntas()->with('respuesta')->ordenadas()->get();

        $html = '';
        foreach ($preguntas as $pregunta) {
            $textoPregunta = $this->e($this->limpiarTexto($pregunta->pregunta));
            $html .= "<p class=\"pregunta\">PREGUNTA: {$textoPregunta}</p>";

            if ($pregunta->respuesta) {
                $textoRespuesta = $this->e($this->limpiarTexto($pregunta->respuesta->respuesta));
                $html .= "<p class=\"respuesta\">RESPUESTA: {$textoRespuesta}</p>";

                if (!empty($pregunta->respuesta->archivos_adjuntos)) {
                    $html .= '<div class="archivos">Archivos adjuntos:<ul>';
                    foreach ($pregunta->respuesta->archivos_adjuntos as $archivo) {
                        $nombre = $this->e($archivo['nombre'] ?? 'Archivo adjunto');
                        $html .= "<li>{$nombre}</li>";
                    }
                    $html .= '</ul></div>';
                }
            } else {
                $html .= '<p class="respuesta"><em>RESPUESTA: [Sin respuesta]</em></p>';
            }
        }

        return $html;
    }

    private function htmlInformacionAdicional($diligencia): string
    {
        $acompanante = $this->obtenerInfoAcompanante($diligencia);

        $html = '<h2>INFORMACIÓN ADICIONAL DE LA DILIGENCIA</h2>';

        if ($acompanante['tiene_acompanante']) {
            $nombre = $this->e($acompanante['nombre']);
            $cargo  = $this->e($acompanante['cargo']);
            $html .= "<p><strong>ACOMPAÑANTE DEL TRABAJADOR:</strong><br>"
                . "Nombre: {$nombre}" . (!empty($cargo) ? "<br>Cargo/Relación: {$cargo}" : '') . '</p>';
        } else {
            $html .= '<p><strong>ACOMPAÑANTE DEL TRABAJADOR:</strong> El trabajador no se hizo acompañar en esta diligencia.</p>';
        }

        $html .= '<p><strong>PRUEBAS APORTADAS:</strong> ';
        if ($diligencia->pruebas_aportadas) {
            $desc = !empty($diligencia->descripcion_pruebas)
                ? $this->e($this->limpiarTexto($diligencia->descripcion_pruebas))
                : 'El trabajador aportó pruebas durante la diligencia.';
            $html .= $desc;
        } else {
            $html .= '<em>El trabajador no aportó pruebas durante esta diligencia.</em>';
        }
        $html .= '</p>';

        return $html;
    }

    private function htmlCierre($diligencia): string
    {
        $fechaCierre = $diligencia->tiempo_limite
            ? \Carbon\Carbon::parse($diligencia->tiempo_limite)->timezone('America/Bogota')
            : now()->timezone('America/Bogota');

        $horaFin    = $fechaCierre->format('h:i A');
        $fechaTexto = $this->convertirFechaATexto($fechaCierre);

        $texto = "Se da por terminada la presente Diligencia a las {$horaFin} del {$fechaTexto}, "
            . "anunciando al trabajador que se estudiará el asunto y que a la menor brevedad posible "
            . "se le informará el resultado de la investigación de los hechos, y se suscribe por quienes "
            . "en ella intervinieron:";

        return "<p>{$texto}</p>";
    }

    private function htmlFirmas($trabajador): string
    {
        $nombre  = $this->e($trabajador->nombre_completo ?? '');
        $tipoDoc = $this->e($trabajador->tipo_documento ?? '');
        $numDoc  = $this->e($trabajador->numero_documento ?? '');

        return <<<HTML
<table class="firmas">
    <tr>
        <td>
            <div class="linea-firma"></div>
            <strong>Representante del Empleador</strong><br>
            CES Legal
        </td>
        <td>
            <div class="linea-firma"></div>
            <strong>{$nombre}</strong><br>
            {$tipoDoc} N° {$numDoc}
        </td>
    </tr>
</table>
HTML;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Generación PDF con Dompdf
    // ──────────────────────────────────────────────────────────────────────────

    private function guardarPDF(string $html, $proceso): string
    {
        $directory = storage_path('app/actas_descargos');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = 'acta_descargos_' . $proceso->codigo . '_' . time() . '.pdf';
        $filepath = $directory . '/' . $filename;

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        file_put_contents($filepath, $dompdf->output());

        Log::info('Acta de descargos generada en PDF', [
            'proceso_id' => $proceso->id,
            'path'       => $filepath,
        ]);

        return $filepath;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers de texto
    // ──────────────────────────────────────────────────────────────────────────

    /** Escapa para HTML */
    private function e(?string $texto): string
    {
        return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
    }

    /** Limpia texto HTML a texto plano */
    private function limpiarTexto(?string $texto): string
    {
        if (empty($texto)) {
            return '';
        }
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = strip_tags($texto);
        $texto = preg_replace('/\s+/', ' ', $texto);
        return trim($texto);
    }

    /**
     * Convierte una fecha a texto en español
     */
    protected function convertirFechaATexto($fecha): string
    {
        $dia = $fecha->day;
        $mes = $fecha->month;
        $año = $fecha->year;

        $diaTexto = $this->numeroATexto($dia);
        $mesTexto = $this->obtenerMesTexto($mes);
        $añoTexto = $this->numeroATexto($año);

        return "{$diaTexto} ({$dia}) de {$mesTexto} del año {$añoTexto} ({$año})";
    }

    /**
     * Convierte un número a texto (simplificado)
     */
    protected function numeroATexto($numero): string
    {
        $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $decenas = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $especiales = [
            10 => 'diez',
            11 => 'once',
            12 => 'doce',
            13 => 'trece',
            14 => 'catorce',
            15 => 'quince',
            16 => 'dieciséis',
            17 => 'diecisiete',
            18 => 'dieciocho',
            19 => 'diecinueve',
        ];

        if ($numero < 10) {
            return $unidades[$numero];
        }

        if ($numero >= 10 && $numero < 20) {
            return $especiales[$numero] ?? '';
        }

        if ($numero >= 20 && $numero < 100) {
            $dec = intdiv($numero, 10);
            $uni = $numero % 10;
            return $decenas[$dec] . ($uni > 0 ? ' y ' . $unidades[$uni] : '');
        }

        if ($numero >= 100 && $numero < 1000) {
            // Implementación simplificada para centenas
            $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
            $cent = intdiv($numero, 100);
            $resto = $numero % 100;

            $texto = $centenas[$cent];
            if ($resto > 0) {
                $texto .= ' ' . $this->numeroATexto($resto);
            }
            return $texto;
        }

        if ($numero >= 1000 && $numero < 10000) {
            $mil = intdiv($numero, 1000);
            $resto = $numero % 1000;

            $texto = ($mil > 1 ? $this->numeroATexto($mil) . ' mil' : 'mil');
            if ($resto > 0) {
                $texto .= ' ' . $this->numeroATexto($resto);
            }
            return $texto;
        }

        // Para números más grandes, usar el número tal cual
        return (string) $numero;
    }

    /**
     * Obtiene el nombre del mes en texto
     */
    protected function obtenerMesTexto($mes): string
    {
        $meses = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre'
        ];

        return $meses[$mes] ?? '';
    }

    /**
     * Obtiene la información del acompañante desde las respuestas a las preguntas
     */
    protected function obtenerInfoAcompanante($diligencia): array
    {
        $preguntas = $diligencia->preguntas()
            ->with('respuesta')
            ->ordenadas()
            ->get();

        $deseaAcompanante = false;
        $nombreAcompanante = '';
        $cargoAcompanante = '';

        foreach ($preguntas as $pregunta) {
            $preguntaTexto = strtolower($pregunta->pregunta);
            $respuesta = $pregunta->respuesta?->respuesta ?? '';

            // Primera pregunta: ¿Desea hacerse acompañar?
            if (str_contains($preguntaTexto, 'desea hacerse acompañar')) {
                $respuestaLower = strtolower(trim($respuesta));
                $deseaAcompanante = str_contains($respuestaLower, 'sí') ||
                    str_contains($respuestaLower, 'si') ||
                    str_contains($respuestaLower, 'yes');
            }

            // Segunda pregunta: Nombre del acompañante
            if (str_contains($preguntaTexto, 'nombre completo de la persona que lo acompañará')) {
                $nombreAcompanante = trim($respuesta);
                // Si respondió "No aplica", ignorar
                if (strtolower($nombreAcompanante) === 'no aplica') {
                    $nombreAcompanante = '';
                }
            }

            // Tercera pregunta: Cargo/relación del acompañante
            if (str_contains($preguntaTexto, 'cargo o relación de la persona que lo acompañará')) {
                $cargoAcompanante = trim($respuesta);
                // Si respondió "No aplica", ignorar
                if (strtolower($cargoAcompanante) === 'no aplica') {
                    $cargoAcompanante = '';
                }
            }
        }

        return [
            'tiene_acompanante' => $deseaAcompanante && !empty($nombreAcompanante),
            'nombre' => $nombreAcompanante,
            'cargo' => $cargoAcompanante,
        ];
    }

}
