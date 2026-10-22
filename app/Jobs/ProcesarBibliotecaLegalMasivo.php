<?php

namespace App\Jobs;

use App\Models\DocumentoLegal;
use App\Services\BibliotecaLegalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Variante de ProcesarBibliotecaLegal para la importación masiva
 * (BibliotecaLegalResource\Pages\ListBibliotecaLegals, acción "Importar
 * varios documentos"): el documento llega SIN título/tipo/referencia reales
 * (solo un título provisional basado en el nombre del archivo y
 * tipo='otro', ver crearDocumentosDesdeArchivosMasivo()), así que primero
 * identifica esos metadatos con IA y LUEGO fragmenta + genera embeddings -
 * todo a partir de UN solo texto extraído, para no pagar OCR dos veces en
 * documentos escaneados (mismo criterio que sugerirMetadatosAlSubir() en
 * BibliotecaLegalResource, ver memoria "biblioteca-legal-mejoras-formulario").
 */
class ProcesarBibliotecaLegalMasivo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly DocumentoLegal $documento,
    ) {
        // Misma cola y throttle que ProcesarBibliotecaLegal y la auditoría de RIT.
        $this->onQueue('gemini');
    }

    public function middleware(): array
    {
        return [new RateLimited('gemini-api')];
    }

    public function handle(BibliotecaLegalService $biblioteca): void
    {
        $documento = $this->documento;
        $documento->update(['estado' => 'procesando', 'error_mensaje' => null]);

        try {
            $texto = $biblioteca->extraerTexto($documento);

            if (empty(trim($texto))) {
                throw new \RuntimeException('No se pudo extraer texto del documento. El archivo puede estar dañado o protegido.');
            }

            // En el flujo masivo nada se escribió a mano todavía - a
            // diferencia de sugerirMetadatosAlSubir() (que solo llena
            // campos vacíos para no pisar lo que el abogado ya tecleó), acá
            // la sugerencia de la IA SIEMPRE tiene prioridad sobre el
            // título/tipo provisionales, y solo se conserva el provisional
            // si la IA no identificó nada con confianza.
            $sugerencia = $biblioteca->sugerirMetadatos($texto);

            $documento->update([
                'titulo'               => $sugerencia['titulo'] ?? $documento->titulo,
                'tipo'                 => $sugerencia['tipo'] ?? $documento->tipo,
                'referencia'           => $sugerencia['referencia'] ?? $documento->referencia,
                'fecha_expedicion'     => $sugerencia['fecha_expedicion'] ?? $documento->fecha_expedicion,
                'incorporar_completo'  => $sugerencia['incorporar_completo'] ?? false,
            ]);

            // procesarTexto() reutiliza el texto ya extraído (no vuelve a
            // leer el archivo ni a llamar OCR) y deja el registro en
            // 'procesado' o 'error' con su mensaje.
            $biblioteca->procesarTexto($documento, $texto);

            Log::info('BibliotecaLegal: documento importado masivamente', [
                'id'     => $documento->id,
                'titulo' => $documento->titulo,
                'tipo'   => $documento->tipo,
            ]);

        } catch (\Throwable $e) {
            $documento->update([
                'estado'        => 'error',
                'error_mensaje' => $e->getMessage(),
            ]);

            Log::error('BibliotecaLegal: error en importación masiva', [
                'id'    => $documento->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->documento->update([
            'estado'        => 'error',
            'error_mensaje' => $e->getMessage(),
        ]);
    }
}
