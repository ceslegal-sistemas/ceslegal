<?php

namespace App\Services;

use App\Models\AuditoriaRIT;
use App\Models\Empresa;
use App\Models\GapReporte;
use Dompdf\Adapter\CPDF as CpdfAdapter;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GAPReporteService
{
    /**
     * Genera ambos reportes PDF (ejecutivo y técnico) a partir de una auditoría completada.
     * No realiza llamadas a Gemini; trabaja exclusivamente con datos ya almacenados.
     */
    public function generarAmbosReportes(AuditoriaRIT $auditoria): GapReporte
    {
        if ($auditoria->estado !== 'completado') {
            throw new \RuntimeException('La auditoría debe estar completada antes de generar el reporte GAP.');
        }

        $empresa  = $auditoria->empresa;
        $secciones = $auditoria->secciones ?? [];

        if (empty($secciones)) {
            throw new \RuntimeException('La auditoría no tiene secciones registradas.');
        }

        // Crear/actualizar registro en estado 'generando'
        $reporte = GapReporte::updateOrCreate(
            ['auditoria_rit_id' => $auditoria->id],
            [
                'empresa_id'     => $empresa->id,
                'estado'         => 'generando',
                'score_snapshot' => $auditoria->score,
                'mensaje_error'  => null,
            ]
        );

        try {
            $gapsAgrupados = $this->agruparPorRiesgo($secciones);

            $rutaEjecutivo = $this->generarPDF($auditoria, $empresa, $gapsAgrupados, 'ejecutivo');
            $rutaTecnico   = $this->generarPDF($auditoria, $empresa, $gapsAgrupados, 'tecnico');

            $reporte->update([
                'estado'         => 'completado',
                'ruta_ejecutivo' => $rutaEjecutivo,
                'ruta_tecnico'   => $rutaTecnico,
            ]);

            Log::info('GAPReporteService: reportes generados', [
                'auditoria_id' => $auditoria->id,
                'empresa_id'   => $empresa->id,
            ]);

        } catch (\Throwable $e) {
            $reporte->update([
                'estado'        => 'error',
                'mensaje_error' => $e->getMessage(),
            ]);

            Log::error('GAPReporteService: fallo al generar reportes', [
                'auditoria_id' => $auditoria->id,
                'error'        => $e->getMessage(),
            ]);

            throw $e;
        }

        return $reporte;
    }

    /**
     * Agrupa secciones por nivel de riesgo según su score:
     * 0–39 → alto, 40–69 → medio, 70–99 → bajo, 100 → sin_gap
     */
    public function agruparPorRiesgo(array $secciones): array
    {
        $grupos = ['alto' => [], 'medio' => [], 'bajo' => [], 'sin_gap' => []];

        foreach ($secciones as $clave => $seccion) {
            $score = $seccion['score'] ?? 0;
            $nivel = match (true) {
                $score <= 39  => 'alto',
                $score <= 69  => 'medio',
                $score <= 99  => 'bajo',
                default       => 'sin_gap',
            };
            $grupos[$nivel][$clave] = $seccion;
        }

        return $grupos;
    }

    /**
     * Genera el PDF para el tipo dado ('ejecutivo' o 'tecnico') y lo guarda en storage.
     * Retorna la ruta relativa al archivo guardado.
     */
    private function generarPDF(
        AuditoriaRIT $auditoria,
        Empresa $empresa,
        array $gapsAgrupados,
        string $tipo
    ): string {
        $html = view("gap-reportes.{$tipo}", compact('empresa', 'auditoria', 'gapsAgrupados'))->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultPaperSize', 'letter');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        if ($canvas instanceof CpdfAdapter) {
            $ownerPass = substr(hash('sha256', config('app.key') . $empresa->id . 'gap_' . $tipo), 0, 32);
            $canvas->get_cpdf()->setEncryption('', $ownerPass, ['print']);
        }

        $directorio   = "private/gap-reportes/{$empresa->id}";
        $rutaRelativa = "{$directorio}/gap_{$tipo}_{$auditoria->id}.pdf";

        Storage::makeDirectory($directorio);
        Storage::put($rutaRelativa, $dompdf->output());

        return $rutaRelativa;
    }
}
