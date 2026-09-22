<?php

namespace App\Support;

use App\Models\Empresa;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response;

/**
 * Genera el poster imprimible (PDF) con el QR del link fijo de socialización
 * del RIT de una empresa - pensado para pegar en carteleras/áreas comunes.
 */
class RitPoster
{
    public static function responder(Empresa $empresa): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::html($empresa), 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $nombre = 'Poster_QR_RIT_' . Str::slug($empresa->razon_social) . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nombre . '"',
        ]);
    }

    public static function html(Empresa $empresa): string
    {
        $qrSvg = QrCode::size(320)->margin(1)->generate($empresa->urlSocializacionRit());
        // El poster es de la empresa cliente, no de LUPE Legal: usa el color
        // de marca que la empresa ya configuró (mismo campo que membrete-empresa),
        // con un gris oscuro neutro como respaldo si no configuró ninguno.
        $colorAcento = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $empresa->logo_color_acento)
            ? $empresa->logo_color_acento
            : '#27272a';

        return view('pdf.rit-poster', [
            'empresa' => $empresa,
            'colorAcento' => $colorAcento,
            'qrBase64' => base64_encode($qrSvg),
            'logoBase64' => self::logoBase64($empresa),
            'iconoCamara' => self::iconoBase64('M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316zM16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z', $colorAcento),
            'iconoScan' => self::iconoBase64('M7.5 3.75H6A2.25 2.25 0 003.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0120.25 6v1.5m0 9V18A2.25 2.25 0 0118 20.25h-1.5m-9 0H6A2.25 2.25 0 013.75 18v-1.5M15 12a3 3 0 11-6 0 3 3 0 016 0z', $colorAcento),
            'iconoCheck' => self::iconoBase64('M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z', $colorAcento),
        ])->render();
    }

    /**
     * Íconos como imagen (data URI), no <svg> inline: Dompdf renderiza bien
     * SVG embebido como <img src="data:image/svg+xml;base64,...">  (mismo
     * mecanismo que el QR), pero su parser nativo de <svg> inline en el DOM
     * es mucho más limitado y no dibujaba nada (círculos vacíos).
     */
    private static function iconoBase64(string $path, string $color): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="' . $path . '"/></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** Mismo patron que pdfs/components/membrete-empresa.blade.php. */
    private static function logoBase64(Empresa $empresa): ?string
    {
        if (!$empresa->logo_path) {
            return null;
        }

        $rutaAbsoluta = \Illuminate\Support\Facades\Storage::disk('local')->path($empresa->logo_path);
        if (!is_file($rutaAbsoluta)) {
            return null;
        }

        $mime = mime_content_type($rutaAbsoluta) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($rutaAbsoluta));
    }
}
