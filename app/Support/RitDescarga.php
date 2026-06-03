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
 *   a. Archivo físico adjunto (ruta_docx) — el documento original del cliente.
 *   b. PDF permanente (ruta_pdf) — típico del RIT mejorado por IA.
 *   c. PDF generado al vuelo desde texto_completo.
 */
class RitDescarga
{
    public static function responder(Empresa $empresa): BinaryFileResponse
    {
        $rit = self::seleccionarRit($empresa);

        if (!$rit) {
            abort(404, 'Documento no encontrado. Genere su RIT primero.');
        }

        // a. Archivo físico adjunto (subido manualmente)
        if (!empty($rit->ruta_docx)) {
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

        // b. PDF permanente (RIT mejorado por IA)
        if (!empty($rit->ruta_pdf)) {
            $rutaAbsoluta = Storage::path($rit->ruta_pdf);
            if (file_exists($rutaAbsoluta)) {
                $nombre = 'Reglamento_Interno_' . Str::slug($empresa->razon_social) . '.pdf';
                return response()->download($rutaAbsoluta, $nombre, [
                    'Content-Type' => 'application/pdf',
                ]);
            }
        }

        // c. PDF generado al vuelo desde el texto
        if (!empty($rit->texto_completo)) {
            $tmpPath = app(RITGeneratorService::class)->generarPDFTemp($rit->texto_completo, $empresa);
            $nombre  = 'Reglamento_Interno_' . Str::slug($empresa->razon_social) . '.pdf';
            return response()->download($tmpPath, $nombre, [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend();
        }

        abort(404, 'Documento no encontrado. Genere su RIT primero.');
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
