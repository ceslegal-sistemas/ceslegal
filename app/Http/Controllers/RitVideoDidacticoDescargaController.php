<?php

namespace App\Http\Controllers;

use App\Models\HistoricoVideoDidacticoRit;
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

    /**
     * Video didáctico HISTÓRICO (pedido de Andrés Sarmiento, reunión
     * 2026-10-05, item 3: "guardar el vídeo, que uno lo pueda ir a invocar
     * cuando quiera") - un capítulo de un video que ya fue reemplazado por
     * una regeneración posterior. El route-model-binding de $reglamento ya
     * aplica ScopedToBufeteOrEmpresa; se valida además que $historico
     * pertenezca a ESE reglamento, para no servir el histórico de otro RIT
     * solo porque el usuario cambió el ID en la URL.
     */
    public function descargarHistorico(ReglamentoInterno $reglamento, HistoricoVideoDidacticoRit $historico)
    {
        abort_if($historico->reglamento_interno_id !== $reglamento->id, 404);

        $capitulos = $historico->capitulos;
        abort_if(empty($capitulos), 404, 'Este video histórico no tiene capítulos.');

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
