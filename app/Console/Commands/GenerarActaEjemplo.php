<?php

namespace App\Console\Commands;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Console\Command;

class GenerarActaEjemplo extends Command
{
    protected $signature   = 'acta:ejemplo';
    protected $description = 'Genera un acta de descargos de ejemplo en PDF para revisar el formato';

    public function handle(): int
    {
        $html = $this->buildHtml();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $dir = storage_path('app/actas_descargos');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/acta_ejemplo.pdf';
        file_put_contents($path, $dompdf->output());

        $this->info("PDF generado en: {$path}");

        return self::SUCCESS;
    }

    private function buildHtml(): string
    {
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
    </style>
</head>
<body>

    <h1>ACTA DE DESCARGOS</h1>

    <p>En la ciudad de <strong>Bogotá</strong>, Cundinamarca, el día veintiocho (28) de abril del año dos mil
    veinticinco (2025), siendo las 09:30 AM, a través del software virtual de descargos, se reunieron por una parte
    el representante legal de <strong>EMPRESA EJEMPLO S.A.S.</strong> con NIT 900.123.456-7 en representación del
    empleador y, por la otra <strong>JUAN CARLOS RODRÍGUEZ PÉREZ</strong>, identificado con C.C. N° 1.012.345.678,
    en su condición de trabajador para que rinda sus descargos y dé sus explicaciones acerca de los siguientes
    hechos:</p>

    <p>El día veinte (20) de abril de dos mil veinticinco (2025), el señor Juan Carlos Rodríguez Pérez, quien se
    desempeña como Técnico de Mantenimiento, presuntamente no asistió a su turno laboral sin justificación alguna,
    incumpliendo con su obligación contractual de cumplir el horario de trabajo establecido por la empresa, que
    corresponde a los días lunes a viernes de 7:00 AM a 5:00 PM, según lo estipulado en el contrato de trabajo y
    el reglamento interno de la compañía.</p>

    <p class="pregunta">PREGUNTA 1: ¿Acepta usted haber recibido la citación a diligencia de descargos y conocer
    el motivo de la misma?</p>
    <p class="respuesta">RESPUESTA: Sí, acepto haber recibido la citación y conozco el motivo de la diligencia
    de descargos relacionada con mi ausencia del día 20 de abril de 2025.</p>

    <p class="pregunta">PREGUNTA 2: ¿Desea hacerse acompañar en esta diligencia por alguna persona de su
    confianza, ya sea un abogado, un representante sindical u otra persona?</p>
    <p class="respuesta">RESPUESTA: No, no deseo hacerme acompañar en esta diligencia.</p>

    <p class="pregunta">PREGUNTA 3: ¿Cuál es su versión de los hechos ocurridos el día 20 de abril de 2025
    relacionados con su ausencia al trabajo?</p>
    <p class="respuesta">RESPUESTA: El día indicado sufrí un accidente de tránsito menor de camino al trabajo.
    Me encontraba en el bus cuando ocurrió el percance, lo que generó una demora considerable. Al llegar a la
    empresa ya habían pasado más de dos horas y el jefe de turno me indicó que no podía ingresar a trabajar.
    Intenté comunicarme con el área de recursos humanos pero no logré contacto.</p>

    <p class="pregunta">PREGUNTA 4: ¿Cuenta usted con algún documento, prueba o testigo que respalde su versión
    de los hechos?</p>
    <p class="respuesta">RESPUESTA: Sí, tengo el reporte del accidente que entregó el conductor del bus y
    también tengo un mensaje de texto que le envié a mi supervisora ese día informando la situación. Los
    puedo adjuntar si es necesario.</p>

    <p class="pregunta">PREGUNTA 5: ¿Desea agregar algo más a sus descargos que considere relevante para la
    decisión que debe tomar la empresa?</p>
    <p class="respuesta">RESPUESTA: Quiero aclarar que en mis más de tres años trabajando en la empresa nunca
    había faltado sin justificación. Siempre he cumplido con mis responsabilidades y este fue un caso
    excepcional que ocurrió por circunstancias ajenas a mi voluntad. Solicito respetuosamente que se
    tengan en cuenta estos antecedentes al tomar la decisión.</p>

    <h2>INFORMACIÓN ADICIONAL DE LA DILIGENCIA</h2>

    <p><strong>ACOMPAÑANTE DEL TRABAJADOR:</strong> El trabajador no se hizo acompañar en esta diligencia.</p>

    <p><strong>PRUEBAS APORTADAS:</strong> <em>El trabajador no aportó pruebas durante esta diligencia.</em></p>

    <p>Se da por terminada la presente Diligencia a las 10:15 AM del día veintiocho (28) de abril del año
    dos mil veinticinco (2025), anunciando al trabajador que se estudiará el asunto y que a la menor brevedad
    posible se le informará el resultado de la investigación de los hechos, y se suscribe por quienes en ella
    intervinieron:</p>

    <table class="firmas">
        <tr>
            <td>
                <div class="linea-firma"></div>
                <strong>Representante del Empleador</strong><br>
                CES Legal
            </td>
            <td>
                <div class="linea-firma"></div>
                <strong>JUAN CARLOS RODRÍGUEZ PÉREZ</strong><br>
                C.C. N° 1.012.345.678
            </td>
        </tr>
    </table>

</body>
</html>
HTML;
    }
}
