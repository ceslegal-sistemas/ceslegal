<?php

namespace App\Services;

use App\Models\Documento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentoService
{
    /**
     * Genera un documento a partir de una plantilla
     */
    public function generar(
        string $documentableTipo,
        int $documentableId,
        string $tipoDocumento,
        string $plantilla,
        array $variables,
        string $formato = 'pdf'
    ): Documento {
        // Generar el contenido reemplazando las variables en la plantilla
        $contenido = $this->reemplazarVariables($plantilla, $variables);

        // Generar nombre de archivo único
        $nombreArchivo = $this->generarNombreArchivo($tipoDocumento, $documentableId, $formato);

        // Guardar el archivo (aquí se puede integrar con una librería de generación de PDF)
        $rutaArchivo = $this->guardarArchivo($nombreArchivo, $contenido, $formato);

        // Registrar en la base de datos
        return Documento::create([
            'documentable_type' => $documentableTipo,
            'documentable_id' => $documentableId,
            'tipo_documento' => $tipoDocumento,
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $rutaArchivo,
            'formato' => $formato,
            'generado_por' => Auth::id(),
            'plantilla_usada' => $plantilla,
            'variables_usadas' => $variables,
            'version' => $this->obtenerSiguienteVersion($documentableTipo, $documentableId, $tipoDocumento),
            'fecha_generacion' => now(),
        ]);
    }

    /**
     * Genera un memorando de llamado de atención
     */
    public function generarMemorandoLlamado(
        int $procesoId,
        array $datosVariable
    ): Documento {
        $plantilla = $this->obtenerPlantilla('memorando_llamado');

        return $this->generar(
            documentableTipo: 'App\Models\ProcesoDisciplinario',
            documentableId: $procesoId,
            tipoDocumento: 'memorando_llamado',
            plantilla: $plantilla,
            variables: $datosVariable,
            formato: 'pdf'
        );
    }

    /**
     * Genera un memorando de suspensión
     */
    public function generarMemorandoSuspension(
        int $procesoId,
        array $datosVariable
    ): Documento {
        $plantilla = $this->obtenerPlantilla('memorando_suspension');

        return $this->generar(
            documentableTipo: 'App\Models\ProcesoDisciplinario',
            documentableId: $procesoId,
            tipoDocumento: 'memorando_suspension',
            plantilla: $plantilla,
            variables: $datosVariable,
            formato: 'pdf'
        );
    }

    /**
     * Genera un memorando de terminación de contrato
     */
    public function generarMemorandoTerminacion(
        int $procesoId,
        array $datosVariable
    ): Documento {
        $plantilla = $this->obtenerPlantilla('memorando_terminacion');

        return $this->generar(
            documentableTipo: 'App\Models\ProcesoDisciplinario',
            documentableId: $procesoId,
            tipoDocumento: 'memorando_terminacion',
            plantilla: $plantilla,
            variables: $datosVariable,
            formato: 'pdf'
        );
    }

    /**
     * Genera el acta de diligencia de descargos
     */
    public function generarActaDescargos(
        int $diligenciaId,
        array $datosVariable
    ): Documento {
        $plantilla = $this->obtenerPlantilla('acta_descargos');

        return $this->generar(
            documentableTipo: 'App\Models\DiligenciaDescargo',
            documentableId: $diligenciaId,
            tipoDocumento: 'acta_descargos',
            plantilla: $plantilla,
            variables: $datosVariable,
            formato: 'pdf'
        );
    }

    /**
     * Genera el documento de apertura de proceso disciplinario
     */
    public function generarAperturaProceso(
        int $procesoId,
        array $datosVariable
    ): Documento {
        $plantilla = $this->obtenerPlantilla('apertura_proceso');

        return $this->generar(
            documentableTipo: 'App\Models\ProcesoDisciplinario',
            documentableId: $procesoId,
            tipoDocumento: 'apertura_proceso',
            plantilla: $plantilla,
            variables: $datosVariable,
            formato: 'pdf'
        );
    }

    /**
     * Genera el análisis jurídico del proceso
     */
    public function generarAnalisisJuridico(
        int $analisisId,
        array $datosVariable
    ): Documento {
        $plantilla = $this->obtenerPlantilla('analisis_juridico');

        return $this->generar(
            documentableTipo: 'App\Models\AnalisisJuridico',
            documentableId: $analisisId,
            tipoDocumento: 'analisis_juridico',
            plantilla: $plantilla,
            variables: $datosVariable,
            formato: 'pdf'
        );
    }

    /**
     * Genera un contrato de labor u obra
     */
    public function generarContratoLaborObra(
        int $solicitudId,
        array $datosVariable
    ): Documento {
        $plantilla = $this->obtenerPlantilla('contrato_labor_obra');

        return $this->generar(
            documentableTipo: 'App\Models\SolicitudContrato',
            documentableId: $solicitudId,
            tipoDocumento: 'contrato_labor_obra',
            plantilla: $plantilla,
            variables: $datosVariable,
            formato: 'pdf'
        );
    }

    /**
     * Genera la decisión de impugnación
     */
    public function generarDecisionImpugnacion(
        int $impugnacionId,
        array $datosVariable
    ): Documento {
        $plantilla = $this->obtenerPlantilla('decision_impugnacion');

        return $this->generar(
            documentableTipo: 'App\Models\Impugnacion',
            documentableId: $impugnacionId,
            tipoDocumento: 'decision_impugnacion',
            plantilla: $plantilla,
            variables: $datosVariable,
            formato: 'pdf'
        );
    }

    /**
     * Reemplaza las variables en la plantilla
     */
    private function reemplazarVariables(string $plantilla, array $variables): string
    {
        $contenido = $plantilla;

        foreach ($variables as $clave => $valor) {
            $contenido = str_replace('{{' . $clave . '}}', $valor, $contenido);
        }

        return $contenido;
    }

    /**
     * Genera un nombre de archivo único
     */
    private function generarNombreArchivo(string $tipo, int $id, string $formato): string
    {
        $timestamp = now()->format('Ymd_His');
        $random = Str::random(6);
        return "{$tipo}_{$id}_{$timestamp}_{$random}.{$formato}";
    }

    /**
     * Guarda el archivo en el sistema de almacenamiento
     */
    private function guardarArchivo(string $nombreArchivo, string $contenido, string $formato): string
    {
        $directorio = 'documentos/' . now()->format('Y/m');
        $ruta = "{$directorio}/{$nombreArchivo}";

        // Aquí se puede integrar con librerías como DOMPDF, TCPDF, etc.
        // Por ahora solo guardamos el contenido como HTML
        Storage::disk('public')->put($ruta, $contenido);

        return $ruta;
    }

    /**
     * Obtiene la plantilla desde el sistema de archivos o base de datos
     */
    private function obtenerPlantilla(string $tipoDocumento): string
    {
        $rutaPlantilla = resource_path("views/plantillas/{$tipoDocumento}.blade.php");

        if (file_exists($rutaPlantilla)) {
            return file_get_contents($rutaPlantilla);
        }

        // Plantilla por defecto si no existe
        return $this->obtenerPlantillaPorDefecto($tipoDocumento);
    }

    /**
     * Obtiene la siguiente versión del documento
     */
    private function obtenerSiguienteVersion(
        string $documentableTipo,
        int $documentableId,
        string $tipoDocumento
    ): int {
        $ultimaVersion = Documento::where('documentable_type', $documentableTipo)
            ->where('documentable_id', $documentableId)
            ->where('tipo_documento', $tipoDocumento)
            ->max('version');

        return ($ultimaVersion ?? 0) + 1;
    }

    /**
     * Obtiene los documentos de un proceso
     */
    public function obtenerDocumentos(string $documentableTipo, int $documentableId)
    {
        return Documento::where('documentable_type', $documentableTipo)
            ->where('documentable_id', $documentableId)
            ->orderBy('fecha_generacion', 'desc')
            ->get();
    }

    /**
     * Descarga un documento
     */
    public function descargar(Documento $documento)
    {
        return Storage::disk('public')->download($documento->ruta_archivo, $documento->nombre_archivo);
    }

    /**
     * Elimina un documento
     */
    public function eliminar(Documento $documento): bool
    {
        // Eliminar el archivo físico
        if (Storage::disk('public')->exists($documento->ruta_archivo)) {
            Storage::disk('public')->delete($documento->ruta_archivo);
        }

        // Eliminar el registro de la base de datos
        return $documento->delete();
    }

    /**
     * Plantillas por defecto (básicas)
     */
    private function obtenerPlantillaPorDefecto(string $tipo): string
    {
        $plantillas = [
            'memorando_llamado' => '
                <h1>MEMORANDO DE LLAMADO DE ATENCIÓN</h1>
                <p><strong>Código:</strong> {{codigo}}</p>
                <p><strong>Fecha:</strong> {{fecha}}</p>
                <p><strong>Para:</strong> {{trabajador_nombre}}</p>
                <p><strong>De:</strong> {{empresa_nombre}}</p>
                <p><strong>Asunto:</strong> Llamado de Atención</p>
                <p>{{contenido}}</p>
                <p>{{firma}}</p>
            ',
            'memorando_suspension' => '
                <h1>MEMORANDO DE SUSPENSIÓN</h1>
                <p><strong>Código:</strong> {{codigo}}</p>
                <p><strong>Fecha:</strong> {{fecha}}</p>
                <p><strong>Para:</strong> {{trabajador_nombre}}</p>
                <p><strong>De:</strong> {{empresa_nombre}}</p>
                <p><strong>Asunto:</strong> Suspensión Laboral</p>
                <p><strong>Días de Suspensión:</strong> {{dias_suspension}}</p>
                <p>{{contenido}}</p>
                <p>{{firma}}</p>
            ',
            'memorando_terminacion' => '
                <h1>MEMORANDO DE TERMINACIÓN DE CONTRATO</h1>
                <p><strong>Código:</strong> {{codigo}}</p>
                <p><strong>Fecha:</strong> {{fecha}}</p>
                <p><strong>Para:</strong> {{trabajador_nombre}}</p>
                <p><strong>De:</strong> {{empresa_nombre}}</p>
                <p><strong>Asunto:</strong> Terminación de Contrato Laboral</p>
                <p>{{contenido}}</p>
                <p>{{firma}}</p>
            ',
        ];

        return $plantillas[$tipo] ?? '<p>Plantilla no disponible</p>';
    }
}
