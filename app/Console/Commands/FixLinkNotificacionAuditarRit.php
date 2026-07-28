<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Las notificaciones "Nueva normativa disponible" ya ENVIADAS antes del fix
 * (ver DocumentoLegalObserver) tienen la URL vieja (/admin/auditar-r-i-t)
 * horneada en su columna `data` (json) - cambiar el código no las actualiza
 * retroactivamente, porque Filament guarda el payload completo al momento
 * de crear la notificación, no lo recalcula al mostrarla.
 *
 * Uso: php artisan notificaciones:fix-link-auditar-rit [--dry-run]
 */
class FixLinkNotificacionAuditarRit extends Command
{
    protected $signature = 'notificaciones:fix-link-auditar-rit {--dry-run : Solo mostrar qué se cambiaría, sin guardar}';

    protected $description = 'Corrige la URL de /admin/auditar-r-i-t a /admin/mi-reglamento-interno en notificaciones ya enviadas';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('notifications')
            ->where('data', 'like', '%auditar-r-i-t%')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No hay notificaciones con la URL vieja.');
            return self::SUCCESS;
        }

        $this->info("Encontradas {$rows->count()} notificaciones con la URL vieja.");
        $actualizadas = 0;

        foreach ($rows as $row) {
            $data = json_decode($row->data, true);
            if (!is_array($data) || empty($data['actions'])) {
                continue;
            }

            $cambio = false;
            foreach ($data['actions'] as &$accion) {
                if (!empty($accion['url']) && str_contains($accion['url'], '/admin/auditar-r-i-t')) {
                    $nuevaUrl = str_replace('/admin/auditar-r-i-t', '/admin/mi-reglamento-interno', $accion['url']);
                    if (!str_contains($nuevaUrl, 'resaltar=')) {
                        $nuevaUrl .= (str_contains($nuevaUrl, '?') ? '&' : '?') . 'resaltar=auditar';
                    }
                    $this->line("  id={$row->id}: {$accion['url']} -> {$nuevaUrl}");
                    $accion['url'] = $nuevaUrl;
                    $cambio = true;
                }
            }
            unset($accion);

            if ($cambio) {
                $actualizadas++;
                if (!$dryRun) {
                    DB::table('notifications')
                        ->where('id', $row->id)
                        ->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                }
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Simulación (--dry-run): {$actualizadas} notificaciones se actualizarían. Corra sin --dry-run para aplicar.");
        } else {
            $this->info("Listo. {$actualizadas} notificaciones actualizadas.");
        }

        return self::SUCCESS;
    }
}
