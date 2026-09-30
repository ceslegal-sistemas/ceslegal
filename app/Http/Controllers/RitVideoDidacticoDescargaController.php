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
     *
     * ?capitulo=N (query, default 0) - el video ahora es una serie de
     * capítulos independientes (ver RitVideoDidacticoService, 2026-09-30),
     * no un solo archivo. `capitulosVideoDidactico()` envuelve los videos
     * generados ANTES de ese cambio como un capítulo único, así que esta
     * misma ruta sigue funcionando sin parámetro para esos casos legado.
     */
    public function descargar(ReglamentoInterno $reglamento)
    {
        $capitulos = $reglamento->capitulosVideoDidactico();
        abort_if(empty($capitulos), 404, 'Este Reglamento aún no tiene un video didáctico generado.');

        $indice = (int) request()->query('capitulo', 0);
        $capitulo = $capitulos[$indice] ?? null;
        abort_if(!$capitulo, 404, 'Capítulo no encontrado.');

        $ruta = Storage::disk('local')->path($capitulo['path']);

        abort_if(!file_exists($ruta), 404, 'Archivo no encontrado.');

        return response()->file($ruta, [
            'Content-Type' => 'video/mp4',
        ]);
    }
}
