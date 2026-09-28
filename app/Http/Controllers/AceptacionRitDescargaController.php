<?php

namespace App\Http\Controllers;

use App\Models\AceptacionReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AceptacionRitDescargaController extends Controller
{
    /**
     * $trabajador viene con route-model-binding, así que ya pasó por el
     * global scope 'bufeteOrEmpresa' (Trabajador la usa - ver
     * ScopedToBufeteOrEmpresa) - un usuario de otra empresa/bufete recibe
     * 404 antes de llegar aquí. AceptacionReglamentoInterno NO tiene ese
     * scope propio, por eso se verifica manualmente que la acta realmente
     * pertenezca a ESTE trabajador (evita IDOR pasando un id de acta ajeno
     * en la URL).
     */
    public function descargar(Trabajador $trabajador, AceptacionReglamentoInterno $aceptacion)
    {
        abort_if($aceptacion->trabajador_id !== $trabajador->id, 404);
        abort_if(!$aceptacion->ruta_acta, 404, 'Esta aceptación aún no tiene un acta generada.');

        $ruta = Storage::disk('local')->path($aceptacion->ruta_acta);

        abort_if(!file_exists($ruta), 404, 'Archivo no encontrado.');

        $nombreArchivo = 'Acta_RIT_'
            . self::sanitizarNombreArchivo($trabajador->nombre_completo)
            . '_' . $aceptacion->aceptado_en?->format('Y-m-d')
            . '.pdf';

        return response()->file($ruta, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
        ]);
    }

    private static function sanitizarNombreArchivo(string $texto): string
    {
        return preg_replace('/[^A-Za-z0-9\-_]/', '_', Str::ascii($texto));
    }
}
