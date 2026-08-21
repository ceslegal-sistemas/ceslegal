<?php

namespace App\Http\Controllers;

use App\Models\SolicitudContrato;
use Illuminate\Support\Facades\Storage;

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

        return response()->file($ruta, ['Content-Type' => 'application/pdf']);
    }
}
