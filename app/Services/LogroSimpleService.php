<?php

namespace App\Services;

use AlexSyvolap\FilamentConfetti\Confetti;
use App\Models\Empresa;
use App\Models\User;
use LevelUp\Experience\Models\Achievement;

/**
 * Motor genérico reutilizable para logros de un solo nivel ("Constructor de
 * RIT", "Primer Otrosí") o de 3 niveles con la misma mecánica ya probada en
 * LogroDescargosService (1/5/10) - evita reescribir grant/increment/celebrar
 * para cada familia nueva de logros. Mismo criterio ya establecido: el logro
 * pertenece a la EMPRESA (cjmellor/level-up), notificación + confeti en vivo
 * al desbloquear.
 */
class LogroSimpleService
{
    /** Renovar un contrato ANTES de que venza su plazo (SolicitudContratoIAService::aplicarProrrogaAlContrato()). */
    public const UMBRALES_RENOVACION_A_TIEMPO = [
        'Primera Renovación a Tiempo' => 1,
        'Renovador Confiable' => 5,
        'Cero Vencimientos' => 10,
    ];

    /** Aprobar una sugerencia de actualización del RIT (RitActualizacionAutomaticaService::aplicarSugerencia()). */
    public const UMBRALES_ACTUALIZACION_RIT_APROBADA = [
        'Primera Actualización Aprobada' => 1,
        'Reglamento Actualizado' => 5,
        'Empresa Blindada' => 10,
    ];

    /** Otorga un logro de un solo nivel (100% de una vez) si todavía no lo tiene. */
    public function otorgarUnicoSiNoExiste(Empresa $empresa, string $nombreLogro): void
    {
        $achievement = Achievement::where('name', $nombreLogro)->first();
        if (!$achievement) {
            return;
        }

        if ($empresa->allAchievements()->find($achievement->id)) {
            return;
        }

        $empresa->grantAchievement(achievement: $achievement, progress: 100, count: 1);
        $this->celebrar($empresa, $achievement);
    }

    /**
     * Incrementa un nivel de un grupo de 3 (o los que sean) - un umbral por
     * nombre de Achievement, igual mecánica que
     * LogroDescargosService::registrarPlazoCumplido().
     *
     * @param array<string, int> $umbrales nombre del logro => meta para llegar a 100%
     */
    public function incrementarGrupo(Empresa $empresa, array $umbrales): void
    {
        foreach ($umbrales as $nombreLogro => $meta) {
            $achievement = Achievement::where('name', $nombreLogro)->first();
            if (!$achievement) {
                continue;
            }

            $this->incrementarNivel($empresa, $achievement, $meta);
        }
    }

    private function incrementarNivel(Empresa $empresa, Achievement $achievement, int $meta): void
    {
        $incremento = (int) round(100 / $meta);
        $yaGranted = $empresa->allAchievements()->find($achievement->id);
        $progresoAntes = $yaGranted?->pivot->progress ?? 0;

        if ($progresoAntes >= 100) {
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
     * Progreso de cada nivel de un grupo, mismo formato que
     * LogroDescargosService::todosLosNiveles() - reutilizable por
     * LogrosVitrinaService para cualquier familia de 3 niveles.
     *
     * @param array<string, int> $umbrales
     * @return array<int, array{nombre: string, descripcion: ?string, imagen: ?string, completado: bool, progreso_porcentaje: int, progreso_texto: string, fecha_obtenido: ?\Illuminate\Support\Carbon}>
     */
    public function nivelesDeGrupo(Empresa $empresa, array $umbrales, string $unidadTexto): array
    {
        $niveles = [];

        foreach ($umbrales as $nombreLogro => $meta) {
            $achievement = Achievement::where('name', $nombreLogro)->first();
            if (!$achievement) {
                continue;
            }

            $pivot = $empresa->allAchievements()->withTimestamps()->find($achievement->id)?->pivot;
            $progreso = $pivot?->progress ?? 0;
            $count = min($pivot?->count ?? 0, $meta);
            $completado = $progreso >= 100;

            $niveles[] = [
                'nombre' => $achievement->name,
                'descripcion' => $achievement->description,
                'imagen' => $achievement->image,
                'completado' => $completado,
                'progreso_porcentaje' => min(100, $progreso),
                'progreso_texto' => "{$count} de {$meta} {$unidadTexto}",
                'fecha_obtenido' => $completado ? $pivot?->updated_at : null,
            ];
        }

        return $niveles;
    }

    /** Mismo formato que nivelesDeGrupo() pero para un logro de un solo nivel. */
    public function nivelUnico(Empresa $empresa, string $nombreLogro, string $progresoTextoPendiente): ?array
    {
        $achievement = Achievement::where('name', $nombreLogro)->first();
        if (!$achievement) {
            return null;
        }

        $pivot = $empresa->allAchievements()->withTimestamps()->find($achievement->id)?->pivot;
        $completado = ($pivot?->progress ?? 0) >= 100;

        return [
            'nombre' => $achievement->name,
            'descripcion' => $achievement->description,
            'imagen' => $achievement->image,
            'completado' => $completado,
            'progreso_porcentaje' => $completado ? 100 : 0,
            'progreso_texto' => $completado ? '' : $progresoTextoPendiente,
            'fecha_obtenido' => $completado ? $pivot?->updated_at : null,
        ];
    }

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

        if (class_exists(Confetti::class)) {
            Confetti::fireworks()->shoot();
        }
    }
}
