<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Las notificaciones para usuarios 'cliente' ya ENVIADAS antes del panel
 * /empresa tienen la URL vieja (/admin/...) horneada en su columna `data`
 * (json) - cambiar el código no las actualiza retroactivamente, porque
 * Filament guarda el payload completo al momento de crear la notificación,
 * no lo recalcula al mostrarla. Sin esto, un cliente que abra una
 * notificación vieja desde la campanita cae en un enlace que ahora le da 403
 * (/admin ya está bloqueado para su rol).
 *
 * Mismo patrón que notificaciones:fix-link-auditar-rit (comando anterior,
 * caso puntual de una sola URL) generalizado a cualquier /admin/... dentro
 * de data.actions[].url, pero SOLO para notificaciones de usuarios cuyo rol
 * es 'cliente' - las de bufete/admin deben seguir apuntando a /admin.
 *
 * Uso: php artisan notificaciones:migrar-cliente-a-empresa [--dry-run]
 */
class MigrarNotificacionesClienteAPanelEmpresa extends Command
{
    protected $signature = 'notificaciones:migrar-cliente-a-empresa {--dry-run : Solo mostrar qué se cambiaría, sin guardar}';

    protected $description = 'Reescribe /admin/... a /empresa/... en notificaciones ya guardadas de usuarios cliente';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $clienteIds = User::where('role', 'cliente')->pluck('id');

        if ($clienteIds->isEmpty()) {
            $this->info('No hay usuarios cliente.');
            return self::SUCCESS;
        }

        $rows = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $clienteIds)
            ->where('data', 'like', '%/admin/%')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No hay notificaciones de cliente con enlaces /admin/ viejos.');
            return self::SUCCESS;
        }

        $this->info("Encontradas {$rows->count()} notificaciones de cliente con enlaces /admin/.");
        $actualizadas = 0;

        foreach ($rows as $row) {
            $data = json_decode($row->data, true);
            if (!is_array($data) || empty($data['actions'])) {
                continue;
            }

            $cambio = false;
            foreach ($data['actions'] as &$accion) {
                if (!empty($accion['url']) && is_string($accion['url']) && str_contains($accion['url'], '/admin/')) {
                    $nuevaUrl = str_replace('/admin/', '/empresa/', $accion['url']);
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
