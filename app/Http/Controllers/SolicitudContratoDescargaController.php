<?php

namespace App\Http\Controllers;

use App\Models\SolicitudContrato;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SolicitudContratoDescargaController extends Controller
{
    /**
     * El route-model-binding implícito ya aplica el global scope de
     * ScopedToBufeteOrEmpresa (SolicitudContrato::class) - un usuario de
     * otra empresa/bufete recibe 404 antes de llegar aquí, no hace falta
     * una verificación de autorización manual adicional.
     */
    public function contrato(SolicitudContrato $solicitud)
    {
        abort_if(!$solicitud->ruta_contrato, 404, 'Esta solicitud aún no tiene un contrato generado.');

        $ruta = Storage::disk('local')->path($solicitud->ruta_contrato);

        abort_if(!file_exists($ruta), 404, 'Archivo no encontrado.');

        // Sin estos headers el navegador (y en especial el visor de PDF de
        // Chrome) sirve el PDF cacheado de esta misma URL en vez de pedirlo
        // de nuevo tras "Regenerar Borrador" - bug real reportado por el
        // usuario: el archivo en disco SÍ se actualizaba (confirmado por
        // fecha_generacion_contrato + filemtime), pero "Ver Contrato" seguía
        // mostrando la versión vieja.
        //
        // 'Content-Disposition' explícito (bug real reportado por el
        // usuario, 2026-09-28): sin nombre propio, el visor de PDF de Chrome
        // sugiere "descargar.pdf" (toma el último segmento de la URL de la
        // ruta, /solicitud-contrato/{id}/descargar, no el nombre real del
        // archivo en disco) al usar "Guardar como".
        $nombreArchivo = ($solicitud->estado === 'aprobado' ? 'Contrato_' : 'Borrador_')
            . self::sanitizarNombreArchivo($solicitud->codigo . '_' . $solicitud->trabajador_nombres . '_' . $solicitud->trabajador_apellidos)
            . '.pdf';

        return response()->file($ruta, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nombreArchivo . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /**
     * Mismo patrón que contrato() - antes el link de "Descargar Preaviso"
     * usaba Storage::disk('local')->url(), que depende de la ruta global
     * `storage/{path}` de Laravel: SIN autenticación, sirviendo todo el
     * disco privado a cualquiera que adivine la ruta del archivo (hallazgo
     * real, 2026-09-02).
     */
    public function preaviso(SolicitudContrato $solicitud)
    {
        abort_if(!$solicitud->ruta_preaviso, 404, 'Esta solicitud aún no tiene un preaviso generado.');

        $ruta = Storage::disk('local')->path($solicitud->ruta_preaviso);

        abort_if(!file_exists($ruta), 404, 'Archivo no encontrado.');

        // Mismo fix de nombre de archivo que contrato() - ver ese método.
        $nombreArchivo = 'Preaviso_'
            . self::sanitizarNombreArchivo($solicitud->codigo . '_' . $solicitud->trabajador_nombres . '_' . $solicitud->trabajador_apellidos)
            . '.pdf';

        return response()->file($ruta, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nombreArchivo . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /** Str::ascii() transiliera tildes/eñes (ej. "Pérez" -> "Perez") antes de quitar cualquier carácter no seguro para un nombre de archivo. */
    private static function sanitizarNombreArchivo(string $texto): string
    {
        return preg_replace('/[^A-Za-z0-9\-_]/', '_', Str::ascii($texto));
    }
}
