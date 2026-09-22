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

        return view('pdf.rit-poster', [
            'empresa' => $empresa,
            'qrBase64' => base64_encode($qrSvg),
        ])->render();
    }
}
