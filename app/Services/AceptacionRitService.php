<?php

namespace App\Services;

use App\Models\AceptacionReglamentoInterno;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Support\PdfProteccion;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Registra la aceptación del RIT con evidencia jurídica real (pedido
 * explícito del usuario/su equipo, 2026-09-28: "que sea demostrable y
 * jurídicamente válido para el juez"):
 *
 * - Snapshot inmutable del texto exacto + hash SHA-256 (el texto real del
 *   ReglamentoInterno puede mutar después vía Plan B - esta copia nunca
 *   cambia).
 * - Cada aceptación es un registro histórico PERMANENTE - nunca se
 *   sobreescribe una anterior (a diferencia del updateOrCreate() de antes).
 * - Genera un PDF "Acta de Socialización" descargable/archivable.
 */
class AceptacionRitService
{
    /**
     * @param array<int, array{pregunta: string, respuesta_correcta: bool, intentos: array<int, array{respuesta_dada: bool, correcta: bool, respondido_en: string}>}> $quizResultado
     */
    public function registrar(
        Trabajador $trabajador,
        ReglamentoInterno $rit,
        array $quizResultado,
        ?string $fotoBase64,
        ?string $ip,
        ?string $userAgent,
    ): AceptacionReglamentoInterno {
        $textoSnapshot = (string) $rit->texto_completo;

        $aceptacion = AceptacionReglamentoInterno::create([
            'trabajador_id' => $trabajador->id,
            'reglamento_interno_id' => $rit->id,
            'texto_rit_snapshot' => $textoSnapshot,
            'texto_rit_hash' => hash('sha256', $textoSnapshot),
            'aceptado_en' => now(),
            'ip_aceptacion' => $ip,
            'user_agent' => $userAgent,
            'foto_aceptacion_path' => $fotoBase64 ? $this->guardarFoto($trabajador, $fotoBase64) : null,
            'quiz_resultado' => $quizResultado,
        ]);

        $this->generarActaPDF($aceptacion);

        return $aceptacion->refresh();
    }

    private function guardarFoto(Trabajador $trabajador, string $fotoBase64): string
    {
        [, $datos] = explode(',', $fotoBase64, 2);
        $contenido = base64_decode($datos);
        $ruta = "actas-rit/{$trabajador->empresa_id}/fotos/{$trabajador->id}_" . Str::random(8) . '.jpg';
        Storage::disk('local')->makeDirectory(dirname($ruta));
        Storage::disk('local')->put($ruta, $contenido);

        return $ruta;
    }

    public function generarActaPDF(AceptacionReglamentoInterno $aceptacion): string
    {
        $trabajador = $aceptacion->trabajador;
        $empresa = $trabajador->empresa;

        $fotoBase64Inline = null;
        if ($aceptacion->foto_aceptacion_path && Storage::disk('local')->exists($aceptacion->foto_aceptacion_path)) {
            $fotoBase64Inline = 'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get($aceptacion->foto_aceptacion_path));
        }

        $html = view('pdfs.rit.acta-socializacion', [
            'aceptacion' => $aceptacion,
            'trabajador' => $trabajador,
            'empresa' => $empresa,
            'fotoBase64Inline' => $fotoBase64Inline,
        ])->render();

        $directorioRelativo = "actas-rit/{$trabajador->empresa_id}/pdf";
        Storage::disk('local')->makeDirectory($directorioRelativo);
        $rutaRelativa = "{$directorioRelativo}/acta_{$aceptacion->id}.pdf";
        $rutaAbsoluta = Storage::disk('local')->path($rutaRelativa);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        PdfProteccion::proteger($dompdf, PdfProteccion::ownerPassword($trabajador->empresa_id, 'acta-rit'));

        file_put_contents($rutaAbsoluta, $dompdf->output());

        $aceptacion->update([
            'ruta_acta' => $rutaRelativa,
            'fecha_generacion_acta' => now(),
        ]);

        return $rutaRelativa;
    }
}
