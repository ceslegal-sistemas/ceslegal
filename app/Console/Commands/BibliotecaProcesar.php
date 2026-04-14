<?php

namespace App\Console\Commands;

use App\Models\DocumentoLegal;
use App\Services\BibliotecaLegalService;
use Illuminate\Console\Command;

class BibliotecaProcesar extends Command
{
    protected $signature = 'biblioteca:procesar
                            {id? : ID del documento a procesar (omitir para procesar todos los pendientes)}
                            {--todos : Reprocesar todos los documentos, incluso los ya procesados}';

    protected $description = 'Extrae texto, fragmenta y genera embeddings de los documentos de la biblioteca legal.';

    public function handle(BibliotecaLegalService $biblioteca): int
    {
        $id    = $this->argument('id');
        $todos = $this->option('todos');

        if ($id) {
            $documento = DocumentoLegal::find($id);
            if (!$documento) {
                $this->error("Documento #{$id} no encontrado.");
                return self::FAILURE;
            }
            return $this->procesarUno($documento, $biblioteca);
        }

        // Resetear documentos atascados en "procesando" por más de 10 minutos
        $atascados = DocumentoLegal::where('estado', 'procesando')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->get();

        foreach ($atascados as $doc) {
            $doc->update(['estado' => 'pendiente']);
            $this->line("  ↺ Reseteado (atascado): <comment>{$doc->titulo}</comment>");
        }

        // Procesar pendientes (o todos si --todos)
        $query = DocumentoLegal::activos();
        if (!$todos) {
            $query->where('estado', '!=', 'procesado');
        }

        $documentos = $query->get();

        if ($documentos->isEmpty()) {
            $this->info('No hay documentos pendientes de procesar.');
            return self::SUCCESS;
        }

        $this->info("Procesando {$documentos->count()} documento(s)...\n");

        $exitosos = 0;
        $fallidos  = 0;

        foreach ($documentos as $doc) {
            $resultado = $this->procesarUno($doc, $biblioteca);
            $resultado === self::SUCCESS ? $exitosos++ : $fallidos++;
        }

        $this->newLine();
        $this->info("Completado: {$exitosos} exitoso(s), {$fallidos} fallido(s).");

        return $fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function procesarUno(DocumentoLegal $documento, BibliotecaLegalService $biblioteca): int
    {
        $this->line("⏳ Procesando: <info>{$documento->titulo}</info> (#{$documento->id})");

        try {
            $biblioteca->procesarDocumento($documento);
            $documento->refresh();
            $this->line("   ✓ {$documento->total_fragmentos} fragmentos · {$documento->total_palabras} palabras");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->line("   ✗ Error: <error>{$e->getMessage()}</error>");
            return self::FAILURE;
        }
    }
}
