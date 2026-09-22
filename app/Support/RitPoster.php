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
 *
 * Reutiliza el mismo sistema visual "Legal Design" de los contratos
 * (pdfs/contratos/termino-fijo.blade.php): paleta teal fija del sistema
 * (no cambia por empresa, ni usa el rojo de LUPE Legal) + los mismos
 * iconos reales de Lordicon (SVG estático descargado, no Lottie/animado -
 * Dompdf no ejecuta JavaScript) ya presentes en
 * public/images/contrato-legal-design/.
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
        $qrSvg = QrCode::size(300)->margin(1)->generate($empresa->urlSocializacionRit());

        return view('pdf.rit-poster', [
            'empresa' => $empresa,
            'qrBase64' => base64_encode($qrSvg),
            'logoBase64' => self::logoBase64($empresa),
            'iconoPortada' => self::iconoLegalDesign('portada'),
            'iconoTelefono' => self::iconoLegalDesign('parte-02'),
            'iconoTelefonoBlanco' => self::iconoLegalDesign('parte-02-white'),
        ])->render();
    }

    /** Mismos iconos reales del contrato (Lordicon SVG estático, no Lottie). */
    private static function iconoLegalDesign(string $nombre): ?string
    {
        $ruta = public_path('images/contrato-legal-design/' . $nombre . '.svg');
        if (!is_file($ruta)) {
            return null;
        }

        return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($ruta));
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
