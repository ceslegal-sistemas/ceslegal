<?php

namespace App\Console\Commands;

use App\Models\DocumentoLegal;
use App\Services\BibliotecaLegalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BibliotecaImportar extends Command
{
    protected $signature = 'biblioteca:importar
                            {directorio : Ruta absoluta del directorio con los archivos a importar}
                            {--recursivo : Buscar archivos en subcarpetas también}';

    protected $description = 'Importa masivamente archivos PDF/DOCX/TXT a la Biblioteca Legal desde un directorio local del servidor.';

    // Inferir tipo según nombre de archivo o carpeta
    private array $tipoRules = [
        'sentencia_cc'        => ['sentencia c ', 'sentencia t ', 'sentencia su ', 'sentencia-c', 'sentencia-t', 'corte constitucional'],
        'sentencia_csj'       => ['sl', 'stl', 'sentencia sl', 'corte suprema', 'csj'],
        'sentencia_ce'        => ['sentencia ce', 'consejo de estado'],
        'cst'                 => ['art', 'cst', 'código sustantivo', 'codigo sustantivo'],
        'ley'                 => ['ley '],
        'concepto_ministerio' => ['concepto', 'mintrabajo', 'ministerio'],
        'doctrina'            => ['principios', 'doctrina', 'apuntes'],
        'rit_referencia'      => ['reglamento interno', 'rit'],
    ];

    public function handle(BibliotecaLegalService $biblioteca): int
    {
        $directorio = rtrim($this->argument('directorio'), '/\\');

        if (!is_dir($directorio)) {
            $this->error("El directorio no existe: {$directorio}");
            return self::FAILURE;
        }

        $extensiones = ['pdf', 'docx', 'txt'];
        $archivos    = $this->listarArchivos($directorio, $extensiones, $this->option('recursivo'));

        if (empty($archivos)) {
            $this->info('No se encontraron archivos PDF, DOCX o TXT en el directorio.');
            return self::SUCCESS;
        }

        $this->info("Encontrados " . count($archivos) . " archivo(s) para importar.\n");

        $exitosos = 0;
        $fallidos  = 0;
        $omitidos  = 0;

        foreach ($archivos as $ruta) {
            $nombreOriginal = basename($ruta);
            $titulo         = $this->limpiarNombre($nombreOriginal);
            $tipo           = $this->inferirTipo($nombreOriginal, $ruta);

            // Evitar duplicados por nombre de archivo
            if (DocumentoLegal::where('archivo_nombre_original', $nombreOriginal)->exists()) {
                $this->line("  ⊘ Omitido (ya existe): <comment>{$nombreOriginal}</comment>");
                $omitidos++;
                continue;
            }

            // Copiar a storage/app/public/biblioteca-legal/
            $extension   = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
            $nombreDisco = Str::uuid() . '.' . $extension;
            $destino     = 'biblioteca-legal/' . $nombreDisco;

            if (!Storage::disk('public')->put($destino, file_get_contents($ruta))) {
                $this->line("  ✗ Error copiando: <error>{$nombreOriginal}</error>");
                $fallidos++;
                continue;
            }

            $documento = DocumentoLegal::create([
                'titulo'                 => $titulo,
                'tipo'                   => $tipo,
                'referencia'             => $this->inferirReferencia($titulo),
                'descripcion'            => null,
                'archivo_path'           => $destino,
                'archivo_nombre_original'=> $nombreOriginal,
                'estado'                 => 'pendiente',
                'activo'                 => true,
            ]);

            $this->line("⏳ Procesando: <info>{$titulo}</info>");

            try {
                $biblioteca->procesarDocumento($documento);
                $documento->refresh();
                $this->line("   ✓ {$documento->total_fragmentos} fragmentos · {$documento->total_palabras} palabras");
                $exitosos++;
            } catch (\Throwable $e) {
                $this->line("   ✗ Error embeddings: <error>{$e->getMessage()}</error>");
                $this->line("   (El archivo fue guardado — puede reprocesar desde el panel)");
                $fallidos++;
            }
        }

        $this->newLine();
        $this->info("Completado: {$exitosos} exitoso(s), {$fallidos} con error, {$omitidos} omitido(s).");

        return $fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function listarArchivos(string $directorio, array $extensiones, bool $recursivo): array
    {
        $archivos = [];
        $items    = scandir($directorio);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $ruta = $directorio . DIRECTORY_SEPARATOR . $item;

            if (is_dir($ruta) && $recursivo) {
                $archivos = array_merge($archivos, $this->listarArchivos($ruta, $extensiones, true));
            } elseif (is_file($ruta)) {
                $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
                if (in_array($ext, $extensiones)) {
                    $archivos[] = $ruta;
                }
            }
        }

        return $archivos;
    }

    private function limpiarNombre(string $nombre): string
    {
        $titulo = pathinfo($nombre, PATHINFO_FILENAME);
        // Reemplazar guiones/guiones bajos por espacios
        $titulo = str_replace(['-', '_'], ' ', $titulo);
        // Colapsar espacios múltiples
        $titulo = preg_replace('/\s+/', ' ', $titulo);
        return trim($titulo);
    }

    private function inferirTipo(string $nombre, string $ruta): string
    {
        $haystack = mb_strtolower($nombre . ' ' . $ruta);

        foreach ($this->tipoRules as $tipo => $patrones) {
            foreach ($patrones as $patron) {
                if (str_contains($haystack, $patron)) {
                    return $tipo;
                }
            }
        }

        return 'otro';
    }

    private function inferirReferencia(string $titulo): ?string
    {
        // Extraer patrones como "T-239 DE 2021", "C 1270 DE 2000", "SL1861-2024"
        if (preg_match('/\b([A-Z]{1,3}[\s-]?\d{3,5}[-\s](?:DE\s)?\d{4})\b/i', $titulo, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\b(SL\d{4}-\d{4}|STL\d{4}-\d{4})\b/i', $titulo, $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }
}
