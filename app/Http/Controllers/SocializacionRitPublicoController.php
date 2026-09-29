<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\Storage;

class SocializacionRitPublicoController extends Controller
{
    /**
     * Ruta publica (sin middleware auth) - la autorizacion real es poseer el
     * token, no la sesion. Empresa/Trabajador tienen el scope global
     * ScopedToBufeteOrEmpresa, que filtra por la empresa/bufete del usuario
     * AUTENTICADO en la sesion actual - si alguien abre este link con una
     * sesion de otro rol/empresa activa en el mismo navegador, el scope
     * resolveria la empresa equivocada en silencio sin este
     * withoutGlobalScope (mismo patron ya usado en DescargoPublicoController).
     */
    public function mostrar(string $token)
    {
        $empresa = \App\Models\Empresa::withoutGlobalScope('bufeteOrEmpresa')
            ->where('token_socializacion_rit', $token)
            ->first();

        if (!$empresa) {
            abort(404);
        }

        // Esta pagina lleva un token CSRF y un snapshot de Livewire propios de
        // CADA visita/sesion - si un proxy/CDN delante del hosting (Cloudflare,
        // ya documentado cacheando assets estaticos en este mismo dominio) la
        // cachea como una pagina normal, TODOS los visitantes reciben el MISMO
        // token CSRF congelado del momento en que se cacheo, que nunca coincide
        // con su propia sesion -> el submit del formulario falla siempre con
        // 419, y Livewire no muestra ningun aviso visible ante ese error.
        return response()
            ->view('rit.socializacion', ['empresa' => $empresa, 'token' => $token])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Sirve el video didáctico del RIT ACTIVO de la empresa del token - misma
     * autorización que mostrar() (poseer el token es la autorización real,
     * no la sesión). Mismo withoutGlobalScope('bufeteOrEmpresa') que
     * SocializacionRit::resolverRitActivo() - nunca usar
     * $empresa->reglamentoInterno directamente aquí (Gotcha crítico #3).
     */
    public function video(string $token)
    {
        $empresa = Empresa::withoutGlobalScope('bufeteOrEmpresa')
            ->where('token_socializacion_rit', $token)
            ->first();

        if (!$empresa) {
            abort(404);
        }

        $rit = ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->latest('updated_at')
            ->first();

        abort_if(!$rit?->video_didactico_path, 404);

        $ruta = Storage::disk('local')->path($rit->video_didactico_path);
        abort_if(!file_exists($ruta), 404);

        return response()->file($ruta, ['Content-Type' => 'video/mp4']);
    }

    /**
     * Descarga el PDF del RIT ACTIVO de la empresa del token - mismo patrón de
     * autorización que mostrar()/video() (poseer el token, nunca la sesión).
     * Reusa RITGeneratorService::generarPDFTemp() como fallback si todavía no
     * existe un PDF generado (mismo patrón que
     * MiReglamentoInterno::downloadPDFMejorado()).
     */
    public function descargar(string $token)
    {
        $empresa = Empresa::withoutGlobalScope('bufeteOrEmpresa')
            ->where('token_socializacion_rit', $token)
            ->first();

        if (!$empresa) {
            abort(404);
        }

        $rit = ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->latest('updated_at')
            ->first();

        abort_if(!$rit, 404);

        $nombreEmpresa = preg_replace('/[^A-Za-z0-9\-_]/', '_', $empresa->razon_social ?? 'empresa');
        $nombreArchivo = "Reglamento_Interno_{$nombreEmpresa}.pdf";

        if ($rit->ruta_pdf) {
            $rutaAbsoluta = Storage::disk('local')->path($rit->ruta_pdf);
            if (file_exists($rutaAbsoluta)) {
                return response()->download($rutaAbsoluta, $nombreArchivo, ['Content-Type' => 'application/pdf']);
            }
        }

        abort_if(empty($rit->texto_completo), 404);

        $tmpPath = app(\App\Services\RITGeneratorService::class)->generarPDFTemp($rit->texto_completo, $empresa);

        return response()->download($tmpPath, $nombreArchivo, ['Content-Type' => 'application/pdf'])
            ->deleteFileAfterSend();
    }
}
