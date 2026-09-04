<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\User;
use LevelUp\Experience\Models\Achievement;

/**
 * Logros de "Plazos de Descargos Cumplidos" - cumplimiento proactivo (nunca
 * dejar vencer un término legal), no volumen de sanciones. Deliberadamente
 * NO premia cuántas sanciones se emiten - eso incentivaría sancionar de más
 * para subir de nivel, decisión explícita del usuario.
 *
 * El logro le pertenece a la EMPRESA (cjmellor/level-up configurado con
 * Empresa como "user" - ver config/level-up.php), no al usuario individual
 * que hizo clic: una empresa puede tener varios usuarios 'cliente'.
 */
class LogroDescargosService
{
    /**
     * Nombre del logro (debe coincidir con LogrosSeeder) => meta de procesos
     * cerrados a tiempo para desbloquearlo. El paquete no guarda una "meta"
     * en el propio Achievement - vive acá.
     */
    public const UMBRALES = [
        'Primer plazo cumplido' => 1,
        'Gestor puntual' => 5,
        'Constancia total' => 10,
    ];

    /**
     * Estado para la tarjeta del Dashboard: el primer logro aún no
     * desbloqueado (con su progreso actual) y cuántos de los 3 ya están al
     * 100%. Si los 3 ya están completos, 'actual' viene en null.
     */
    public function estadoDashboard(Empresa $empresa): array
    {
        $completados = 0;
        $actual = null;

        foreach (self::UMBRALES as $nombre => $meta) {
            $achievement = Achievement::where('name', $nombre)->first();
            $pivot = $achievement ? $empresa->allAchievements()->find($achievement->id)?->pivot : null;
            $progreso = $pivot?->progress ?? 0;
            $count = $pivot?->count ?? 0;

            if ($progreso >= 100) {
                $completados++;

                continue;
            }

            if ($actual === null) {
                $actual = [
                    'nombre' => $nombre,
                    'count' => min($count, $meta),
                    'meta' => $meta,
                    'progreso' => $progreso,
                ];
            }
        }

        return [
            'completados' => $completados,
            'total' => count(self::UMBRALES),
            'actual' => $actual,
        ];
    }

    /**
     * Se llama una vez por proceso disciplinario, al emitir la sanción
     * ('sancion_emitida'), siempre que ningún término legal del proceso
     * haya llegado a 'vencido' hasta ese punto - ver
     * ProcesoDisciplinarioObserver::aplicarLogicaEstado(). No espera al
     * cierre automático del proceso (decisión explícita del usuario,
     * 2026-09-04): se acepta el riesgo de que una impugnación posterior
     * revierta la sanción, a cambio de premiar la puntualidad tan pronto
     * se resuelve el fondo del asunto.
     */
    public function registrarPlazoCumplido(Empresa $empresa): void
    {
        foreach (self::UMBRALES as $nombre => $meta) {
            $achievement = Achievement::where('name', $nombre)->first();

            if (!$achievement) {
                continue;
            }

            $this->incrementarLogro($empresa, $achievement, $meta);
        }
    }

    private function incrementarLogro(Empresa $empresa, Achievement $achievement, int $meta): void
    {
        $incremento = (int) round(100 / $meta);
        $yaGranted = $empresa->allAchievements()->find($achievement->id);
        $progresoAntes = $yaGranted?->pivot->progress ?? 0;

        if ($progresoAntes >= 100) {
            // Ya desbloqueado - no hay nada más que incrementar en ESTE logro.
            return;
        }

        if (!$yaGranted) {
            $progresoDespues = min(100, $incremento);
            $empresa->grantAchievement(achievement: $achievement, progress: $progresoDespues, count: 1);
        } else {
            $progresoDespues = $empresa->incrementAchievementProgress(
                achievement: $achievement,
                amount: $incremento,
                count: 1,
            );
        }

        if ($progresoDespues >= 100) {
            $this->celebrar($empresa, $achievement);
        }
    }

    /**
     * Notificación (mismo sistema nativo de notificaciones ya usado en todo
     * el proyecto) + flag de confeti en sesión - el Dashboard ya tiene el
     * mismo patrón para "celebrar_registro_rit" (Confetti::fireworks() al
     * hacer mount()), se reutiliza la misma mecánica sin duplicar código de
     * animación.
     */
    private function celebrar(Empresa $empresa, Achievement $achievement): void
    {
        $usuarios = User::where('empresa_id', $empresa->id)
            ->where('active', true)
            ->where('role', 'cliente')
            ->get();

        $notificacionService = app(NotificacionService::class);

        foreach ($usuarios as $usuario) {
            $notificacionService->crear(
                userId: $usuario->id,
                tipo: 'logro_desbloqueado',
                titulo: '¡Nuevo logro desbloqueado!',
                mensaje: "Su empresa desbloqueó el logro \"{$achievement->name}\": {$achievement->description}",
                prioridad: 'baja',
            );
        }

        session()->put('celebrar_logro', $achievement->name);
    }
}
