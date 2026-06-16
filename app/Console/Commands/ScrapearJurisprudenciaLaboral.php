<?php

namespace App\Console\Commands;

use App\Services\JurisprudenciaScraperService;
use Illuminate\Console\Command;

/**
 * Importa un set inicial CURADO de jurisprudencia laboral de la Corte
 * Constitucional (síncrono, no depende de la cola). Las sentencias quedan
 * INACTIVAS hasta que el equipo jurídico las revise y active.
 *
 * Uso:  php artisan jurisprudencia:laboral
 */
class ScrapearJurisprudenciaLaboral extends Command
{
    protected $signature = 'jurisprudencia:laboral {--refs= : Lista separada por coma; si se omite, usa el set por defecto}';

    protected $description = 'Importa un set inicial de jurisprudencia laboral de la Corte Constitucional';

    /** Set inicial por tema (referencias conocidas y relevantes en lo laboral disciplinario). */
    private array $set = [
        // Fuero de maternidad / estabilidad reforzada
        'SU-070/2013',
        'SU-075/2018',
        'C-470/1997',
        'T-1040/2006',
        // Estabilidad reforzada por salud / discapacidad
        'SU-049/2017',
        'C-531/2000',
        'T-320/2016',
        // Debido proceso disciplinario / sancionatorio
        'C-593/2014',
        // Acoso laboral (Ley 1010 de 2006)
        'T-238/2008',
        // Prepensionados
        'SU-003/2018',
    ];

    public function handle(JurisprudenciaScraperService $scraper): int
    {
        $refs = $this->option('refs')
            ? collect(explode(',', $this->option('refs')))->map(fn($r) => trim($r))->filter()->all()
            : $this->set;

        $this->info('Importando ' . count($refs) . ' sentencia(s)... (quedan inactivas para revisar)');
        $ok = 0; $fail = 0;

        foreach ($refs as $ref) {
            $this->line("  · {$ref} ... ");
            try {
                $j = $scraper->importar($ref);
                $estado = $j->estado === 'procesado' ? 'OK' : strtoupper($j->estado);
                $this->info("    {$estado} — {$j->tema}");
                $j->estado === 'procesado' ? $ok++ : $fail++;
            } catch (\Throwable $e) {
                $this->error('    FALLO — ' . $e->getMessage());
                $fail++;
            }
        }

        $this->newLine();
        $this->info("Listo. Procesadas: {$ok} · Con problemas: {$fail}.");
        $this->comment('Revísalas y actívalas en Configuración → Jurisprudencia.');

        return self::SUCCESS;
    }
}
