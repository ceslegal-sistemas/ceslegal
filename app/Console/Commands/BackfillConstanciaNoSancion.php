<?php

namespace App\Console\Commands;

use App\Models\Documento;
use App\Models\ProcesoDisciplinario;
use App\Services\DocumentGeneratorService;
use Illuminate\Console\Command;

class BackfillConstanciaNoSancion extends Command
{
    /**
     * Procesos cerrados con decisión "no_sancion" que se crearon antes de que
     * el sistema generara la constancia: no tienen documento ni se envió correo.
     * Este comando genera la constancia con IA y la envía al trabajador.
     */
    protected $signature = 'procesos:backfill-constancia-no-sancion
                            {--id= : ID de un proceso específico (opcional)}
                            {--dry-run : Solo listar los procesos afectados, sin generar ni enviar nada}';

    protected $description = 'Genera y envía la constancia de no sanción para procesos cerrados sin documento';

    public function handle(DocumentGeneratorService $service): int
    {
        $query = ProcesoDisciplinario::query()
            ->where('tipo_sancion', 'no_sancion')
            ->where('estado', 'cerrado');

        if ($this->option('id')) {
            $query->where('id', $this->option('id'));
        }

        $procesos = $query->get()->filter(function (ProcesoDisciplinario $p) {
            $tieneDoc = Documento::where('documentable_type', ProcesoDisciplinario::class)
                ->where('documentable_id', $p->id)
                ->where('tipo_documento', 'sancion')
                ->exists();
            return !$tieneDoc;
        });

        if ($procesos->isEmpty()) {
            $this->info('No hay procesos cerrados sin sanción que requieran constancia.');
            return self::SUCCESS;
        }

        $this->info("Procesos afectados: {$procesos->count()}");
        $this->table(
            ['ID', 'Código', 'Trabajador', 'Email'],
            $procesos->map(fn(ProcesoDisciplinario $p) => [
                $p->id,
                $p->codigo,
                optional($p->trabajador)->nombre_completo,
                optional($p->trabajador)->email ?: '(sin email)',
            ])->all()
        );

        if ($this->option('dry-run')) {
            $this->warn('Modo dry-run: no se generó ni envió nada.');
            return self::SUCCESS;
        }

        if (!$this->confirm('¿Generar la constancia y ENVIAR el correo al trabajador para estos procesos?', false)) {
            $this->warn('Operación cancelada.');
            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;
        foreach ($procesos as $p) {
            if (empty(optional($p->trabajador)->email)) {
                $this->error("Proceso {$p->codigo}: el trabajador no tiene email. Omitido.");
                $fail++;
                continue;
            }

            try {
                $service->generarYEnviarConstanciaNoSancion($p);
                $this->info("Proceso {$p->codigo}: constancia generada y enviada.");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("Proceso {$p->codigo}: error - {$e->getMessage()}");
                $fail++;
            }
        }

        $this->newLine();
        $this->info("Completado. Éxitos: {$ok} | Fallos: {$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
