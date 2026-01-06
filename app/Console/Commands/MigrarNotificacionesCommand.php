<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Notifications\ProcesoNotification;
use Illuminate\Support\Str;

class MigrarNotificacionesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notificaciones:migrar
                            {--test : Ejecutar en modo prueba sin migrar datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra las notificaciones de la tabla antigua "notificaciones" a la tabla nativa "notifications" de Laravel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $testMode = $this->option('test');

        if ($testMode) {
            $this->info('🧪 Ejecutando en modo prueba (no se migrarán datos)');
        }

        $this->info('📊 Analizando tabla antigua "notificaciones"...');

        // Contar notificaciones antiguas
        $totalAntiguas = DB::table('notificaciones')->count();

        if ($totalAntiguas === 0) {
            $this->info('✅ No hay notificaciones antiguas para migrar.');
            return Command::SUCCESS;
        }

        $this->info("📌 Se encontraron {$totalAntiguas} notificaciones en la tabla antigua.");

        if (!$testMode) {
            if (!$this->confirm('¿Desea continuar con la migración?', true)) {
                $this->warn('❌ Migración cancelada por el usuario.');
                return Command::SUCCESS;
            }
        }

        $this->info('🚀 Iniciando migración...');
        $bar = $this->output->createProgressBar($totalAntiguas);
        $bar->start();

        $migradas = 0;
        $errores = 0;

        // Obtener todas las notificaciones antiguas
        $notificacionesAntiguas = DB::table('notificaciones')->get();

        foreach ($notificacionesAntiguas as $notificacion) {
            try {
                $user = User::find($notificacion->user_id);

                if (!$user) {
                    $this->newLine();
                    $this->warn("⚠️  Usuario {$notificacion->user_id} no encontrado. Saltando notificación ID {$notificacion->id}");
                    $errores++;
                    $bar->advance();
                    continue;
                }

                // Determinar URL
                $url = ProcesoNotification::determinarUrl(
                    $notificacion->relacionado_tipo,
                    $notificacion->relacionado_id
                );

                if (!$testMode) {
                    // Insertar directamente en la tabla notifications
                    DB::table('notifications')->insert([
                        'id' => Str::uuid()->toString(),
                        'type' => ProcesoNotification::class,
                        'notifiable_type' => User::class,
                        'notifiable_id' => $notificacion->user_id,
                        'data' => json_encode([
                            'tipo' => $notificacion->tipo,
                            'titulo' => $notificacion->titulo,
                            'mensaje' => $notificacion->mensaje,
                            'prioridad' => $notificacion->prioridad,
                            'relacionado_tipo' => $notificacion->relacionado_tipo,
                            'relacionado_id' => $notificacion->relacionado_id,
                            'url' => $url,
                        ]),
                        'read_at' => $notificacion->leida ? $notificacion->fecha_lectura : null,
                        'created_at' => $notificacion->created_at,
                        'updated_at' => $notificacion->updated_at,
                    ]);
                }

                $migradas++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("❌ Error al migrar notificación ID {$notificacion->id}: {$e->getMessage()}");
                $errores++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Resumen
        $this->info('📊 Resumen de migración:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Total en tabla antigua', $totalAntiguas],
                ['Migradas exitosamente', $migradas],
                ['Errores', $errores],
            ]
        );

        if ($testMode) {
            $this->info('🧪 Modo prueba completado. No se migraron datos reales.');
        } else {
            $this->info('✅ Migración completada exitosamente!');
            $this->newLine();

            if ($this->confirm('¿Desea eliminar la tabla antigua "notificaciones"?', false)) {
                DB::statement('DROP TABLE notificaciones');
                $this->info('🗑️  Tabla antigua "notificaciones" eliminada.');
            } else {
                $this->warn('⚠️  La tabla antigua "notificaciones" se mantuvo. Puede eliminarla manualmente más tarde.');
            }
        }

        return Command::SUCCESS;
    }
}