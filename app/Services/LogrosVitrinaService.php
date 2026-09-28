<?php

namespace App\Services;

use App\Models\Empresa;
use LevelUp\Experience\Models\Achievement;

/**
 * Combina TODOS los logros existentes (sin importar de qué servicio vengan)
 * en una forma normalizada para la vitrina "Mis Logros" - no duplica la
 * lógica de cálculo de progreso de cada servicio, solo la reutiliza y
 * adapta al mismo formato para poder mostrarlos juntos en una sola grilla.
 */
class LogrosVitrinaService
{
    public function __construct(
        private readonly LogroDescargosService $logroDescargosService,
        private readonly LogroSocializacionRitService $logroSocializacionRitService,
    ) {
    }

    /**
     * @return array<int, array{nombre: string, descripcion: ?string, imagen: ?string, completado: bool, progreso_porcentaje: int, progreso_texto: string, fecha_obtenido: ?\Illuminate\Support\Carbon}>
     */
    public function paraEmpresa(Empresa $empresa): array
    {
        $logros = $this->logroDescargosService->todosLosNiveles($empresa);

        $achievementRit = Achievement::where('name', LogroSocializacionRitService::NOMBRE_LOGRO)->first();
        if ($achievementRit) {
            $estado = $this->logroSocializacionRitService->estadoDashboard($empresa);
            $pivot = $empresa->allAchievements()->withTimestamps()->find($achievementRit->id)?->pivot;

            $logros[] = [
                'nombre' => $achievementRit->name,
                'descripcion' => $achievementRit->description,
                'imagen' => $achievementRit->image,
                'completado' => $estado['completo'],
                'progreso_porcentaje' => $estado['porcentaje'],
                'progreso_texto' => "{$estado['aceptados']} de {$estado['total']} trabajadores aceptaron el Reglamento Interno",
                'fecha_obtenido' => $estado['completo'] ? $pivot?->updated_at : null,
            ];
        }

        return $logros;
    }
}
