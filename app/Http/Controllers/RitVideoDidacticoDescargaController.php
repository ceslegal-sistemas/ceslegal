<?php

namespace App\Http\Controllers;

use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Storage;

class RitVideoDidacticoDescargaController extends Controller
{
    /**
     * El route-model-binding implícito ya aplica ScopedToBufeteOrEmpresa
     * (ReglamentoInterno) - un usuario de otra empresa/bufete recibe 404
     * antes de llegar aquí, mismo patrón que SolicitudContratoDescargaController.
     */
    public function descargar(ReglamentoInterno $reglamento)
    {
        abort_if(!$reglamento->video_didactico_path, 404, 'Este Reglamento aún no tiene un video didáctico generado.');

        $ruta = Storage::disk('local')->path($reglamento->video_didactico_path);

        abort_if(!file_exists($ruta), 404, 'Archivo no encontrado.');

        return response()->file($ruta, [
            'Content-Type' => 'video/mp4',
        ]);
    }
}
