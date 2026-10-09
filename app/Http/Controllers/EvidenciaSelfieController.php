<?php

namespace App\Http\Controllers;

use App\Services\EquivalenteFuncionalService;

class EvidenciaSelfieController extends Controller
{
    /**
     * Selfie de verificación de un evento del reporte "Equivalente Funcional".
     * Las selfies viven en discos distintos según el proceso (public, local o el
     * disco por defecto) y nunca deben abrirse por una URL pública adivinable:
     * se sirven por aquí, solo a quien tiene el permiso del reporte y únicamente
     * si el evento pertenece a una empresa a la que ese usuario tiene acceso
     * (EquivalenteFuncionalService::fotoDe() consulta con el mismo alcance).
     */
    public function mostrar(string $tipo, int $id, EquivalenteFuncionalService $servicio)
    {
        abort_unless(auth()->user()?->can('page_EquivalenteFuncional'), 403);

        $foto = $servicio->fotoDe($tipo, $id);
        abort_if(!$foto, 404, 'Este evento no tiene selfie de verificación.');

        $ruta = $servicio->rutaAbsoluta($foto['disco'], $foto['ruta']);
        abort_if(!$ruta, 404, 'Archivo no encontrado.');

        return response()->file($ruta, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
