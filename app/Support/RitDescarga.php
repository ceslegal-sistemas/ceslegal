<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Services\RITGeneratorService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Resuelve la descarga del Reglamento Interno vigente de una empresa.
 *
 * Prioridad de selección del RIT:
 *   1. El RIT marcado como activo (respeta la decisión del cliente: adoptó la
 *      versión mejorada o mantuvo el subido manualmente).
 *   2. El RIT base más reciente (subido o construido_ia), nunca mejora_ia suelto.
 *   3. Cualquier RIT de la empresa.
 *
 * Prioridad de formato del RIT elegido:
 *   a. Archivo físico adjunto (ruta_docx) — SOLO si el cliente lo subió él mismo
 *      (fuente='subido'). Es su documento original y puede ser Word/PDF.
 *   b. PDF permanente (ruta_pdf) — típico del RIT mejorado por IA.
 *   c. PDF generado al vuelo desde texto_completo.
 *
 * Los RIT producidos por la IA (construido_ia / mejora_ia) NUNCA se entregan como
 * Word: siempre se sirven como PDF (protegido, solo impresión).
 */
class RitDescarga
{
    public static function responder(Empresa $empresa): BinaryFileResponse
    {
        $rit = self::seleccionarRit($empresa);

        if (!$rit) {
            abort(404, 'Documento no encontrado. Genere su RIT primero.');
        }

        // a. Archivo físico adjunto — solo el documento original que subió el cliente.
        //    Para RIT de IA se ignora ruta_docx (jamás se entrega Word).
        if ($rit->fuente === 'subido' && !empty($rit->ruta_docx)) {
            $rutaAbsoluta = Storage::disk('local')->path($rit->ruta_docx);
            if (file_exists($rutaAbsoluta)) {
                $extension = strtolower(pathinfo($rutaAbsoluta, PATHINFO_EXTENSION)) ?: 'docx';
                $mimeTypes = [
                    'pdf'  => 'application/pdf',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'doc'  => 'application/msword',
                ];
                $nombre = $rit->nombre ?? ('RIT_' . Str::slug($empresa->razon_social) . ".{$extension}");
                return response()->download($rutaAbsoluta, $nombre, [
                    'Content-Type' => $mimeTypes[$extension] ?? 'application/octet-stream',
                ]);
            }
        }

        // b. PDF permanente (RIT mejorado por IA) — solo si está cifrado.
        //    Los PDF antiguos sin cifrar se regeneran en el paso (c) para
        //    garantizar que toda descarga salga protegida (solo impresión).
        if (!empty($rit->ruta_pdf)) {
            $rutaAbsoluta = Storage::path($rit->ruta_pdf);
            if (file_exists($rutaAbsoluta) && self::pdfEstaCifrado($rutaAbsoluta)) {
                $nombre = 'Reglamento_Interno_' . Str::slug($empresa->razon_social) . '.pdf';
                return response()->download($rutaAbsoluta, $nombre, [
                    'Content-Type' => 'application/pdf',
                ]);
            }
        }

        // c. PDF generado al vuelo desde el texto.
        //    Protegido solo si es RIT de IA; los subidos por el cliente van sin protección.
        if (!empty($rit->texto_completo)) {
            $proteger = $rit->fuente !== 'subido';
            $tmpPath  = app(RITGeneratorService::class)->generarPDFTemp($rit->texto_completo, $empresa, $proteger);
            $nombre   = 'Reglamento_Interno_' . Str::slug($empresa->razon_social) . '.pdf';
            return response()->download($tmpPath, $nombre, [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend();
        }

        abort(404, 'Documento no encontrado. Genere su RIT primero.');
    }

    /** Comprueba si un PDF está cifrado (contiene el diccionario /Encrypt). */
    private static function pdfEstaCifrado(string $rutaPdf): bool
    {
        $contenido = @file_get_contents($rutaPdf);
        return $contenido !== false && str_contains($contenido, '/Encrypt');
    }

    private static function seleccionarRit(Empresa $empresa): ?ReglamentoInterno
    {
        return ReglamentoInterno::where('empresa_id', $empresa->id)
                ->where('activo', true)
                ->orderByDesc('updated_at')
                ->first()
            ?? ReglamentoInterno::where('empresa_id', $empresa->id)
                ->where('fuente', '!=', 'mejora_ia')
                ->orderByDesc('updated_at')
                ->first()
            ?? ReglamentoInterno::where('empresa_id', $empresa->id)
                ->orderByDesc('updated_at')
                ->first();
    }
}
