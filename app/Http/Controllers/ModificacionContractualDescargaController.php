<?php

namespace App\Http\Controllers;

use App\Models\ModificacionContractual;
use Illuminate\Support\Facades\Storage;

class ModificacionContractualDescargaController extends Controller
{
    /**
     * Mismo patrón que SolicitudContratoDescargaController::contrato() - el
     * route-model-binding implícito ya aplica el global scope de
     * ScopedToBufeteOrEmpresa (ModificacionContractual::class), así que un
     * usuario de otra empresa/bufete recibe 404 antes de llegar aquí.
     */
    public function otrosi(ModificacionContractual $modificacion)
    {
        abort_if(!$modificacion->ruta_otrosi, 404, 'Este otrosí aún no tiene un documento generado.');

        $ruta = Storage::disk('local')->path($modificacion->ruta_otrosi);

        abort_if(!file_exists($ruta), 404, 'Archivo no encontrado.');

        return response()->file($ruta, [
            'Content-Type'  => 'application/pdf',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }
}
