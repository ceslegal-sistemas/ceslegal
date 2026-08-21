<?php

namespace App\Support;

use Dompdf\Adapter\CPDF as CpdfAdapter;
use Dompdf\Dompdf;

/**
 * Protección real de PDF (no de interfaz): cifra el documento con una
 * contraseña de propietario y deja habilitado ÚNICAMENTE el permiso de
 * impresión - por eso un PDF generado con esto no se puede convertir a
 * Word ni editar en herramientas externas (Nitro PDF y similares), a
 * diferencia de ocultar botones en el visor, que no protege nada fuera
 * de la propia app. Mismo mecanismo ya usado para el RIT generado con
 * IA, extraído aquí para reutilizarlo también en Solicitud de Contrato.
 */
class PdfProteccion
{
    /**
     * Contraseña de propietario determinística por empresa + tipo de
     * documento (la $sal evita que la misma contraseña sirva para
     * proteger/desproteger documentos de otro tipo de la misma empresa).
     */
    public static function ownerPassword(int $empresaId, string $sal): string
    {
        return substr(hash('sha256', config('app.key') . $empresaId . $sal), 0, 32);
    }

    /**
     * Cifra el PDF ya renderizado (llamar DESPUÉS de $dompdf->render()).
     * No hace nada si el adaptador de Dompdf no es CPDF (ej. si en algún
     * entorno se usa otro renderer) - falla en silencio a propósito,
     * igual que ya hace el código original del RIT.
     */
    public static function proteger(Dompdf $dompdf, string $ownerPassword): void
    {
        $canvas = $dompdf->getCanvas();
        if ($canvas instanceof CpdfAdapter) {
            $canvas->get_cpdf()->setEncryption('', $ownerPassword, ['print']);
        }
    }
}
