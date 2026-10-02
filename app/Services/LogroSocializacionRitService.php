<?php

namespace App\Services;

use App\Models\Empresa;
use LevelUp\Experience\Models\Achievement;

/**
 * Logro "Reglamento 100% aceptado" (mejora "excentrica" pedida por el
 * usuario, 2026-09-23): reconoce a la empresa cuando TODOS sus
 * trabajadores activos aceptaron la version vigente del RIT. Igual que
 * LogroDescargosService, el logro pertenece a la EMPRESA (cjmellor/level-up
 * configurado con Empresa como "user"). Es un logro PERMANENTE: una vez
 * otorgado, no se revoca si despues bajan del 100% (decision explicita del
 * usuario) - por eso revisarYOtorgar() nunca "desotorga" nada, solo
 * verifica si corresponde otorgarlo por primera vez.
 */
class LogroSocializacionRitService
{
    public const NOMBRE_LOGRO = 'Reglamento 100% aceptado';

    /**
     * Denominador = el MAYOR entre numero_empleados declarado por la
     * empresa y los trabajadores activos ya registrados - evita un falso
     * 100% cuando hay muchos mas empleados reales que trabajadores
     * cargados en el sistema, sin bloquear el logro para siempre si la
     * empresa nunca declaro el numero (cae al conteo real).
     */
    public function estadoDashboard(Empresa $empresa): array
    {
        [$aceptados, $denominador] = $this->calcular($empresa);

        return [
            'aceptados' => $aceptados,
            'total' => $denominador,
            'porcentaje' => $denominador > 0 ? (int) round(($aceptados / $denominador) * 100) : 0,
            'completo' => $denominador > 0 && $aceptados >= $denominador,
        ];
    }

    /**
     * Se llama desde SocializacionRit::aceptarReglamento() justo despues
     * de guardar cada aceptacion - si esta aceptacion en particular llevo
     * a la empresa al 100% y el logro todavia no estaba desbloqueado, lo
     * otorga y marca la celebracion pendiente para la proxima visita de
     * CUALQUIER usuario del panel al Dashboard (ver
     * Dashboard::mount() y la nota en la migracion del campo).
     */
    public function revisarYOtorgar(Empresa $empresa): void
    {
        [$aceptados, $denominador] = $this->calcular($empresa);

        if ($denominador === 0 || $aceptados < $denominador) {
            return;
        }

        $achievement = Achievement::where('name', self::NOMBRE_LOGRO)->first();
        if (!$achievement) {
            return;
        }

        $yaGranted = $empresa->allAchievements()->find($achievement->id);
        if ($yaGranted) {
            return;
        }

        $empresa->grantAchievement(achievement: $achievement, progress: 100, count: 1);
        $empresa->forceFill(['logro_socializacion_rit_pendiente_celebrar' => now()])->save();
    }

    /**
     * Detalle por trabajador de quién aceptó el RIT VIGENTE y quién no
     * (pedido de Andrés Sarmiento, 2026-09-29). Compara por HASH del texto
     * vigente, igual que Trabajador::aceptoRitVigente(), para que un
     * trabajador que aceptó una versión ya reemplazada aparezca
     * correctamente como "pendiente". Extraído de
     * MiReglamentoInterno::detalleTrabajadoresSocializacion() (2026-09-30)
     * para reusarlo también en la tarjeta del Dashboard.
     *
     * @return array<int, array{nombre: string, cargo: ?string, acepto: bool, fecha_aceptacion: ?\Carbon\Carbon}>
     */
    public function detallePorTrabajador(Empresa $empresa): array
    {
        $reglamento = $empresa->reglamentoInterno;

        if (!$reglamento || empty($reglamento->texto_completo)) {
            return [];
        }

        $hashVigente = hash('sha256', $reglamento->texto_completo);

        return $empresa->trabajadores()
            ->where('active', true)
            ->get()
            ->map(function (\App\Models\Trabajador $trabajador) use ($hashVigente) {
                $aceptacion = $trabajador->aceptacionesReglamentoInterno()
                    ->where('texto_rit_hash', $hashVigente)
                    ->latest('aceptado_en')
                    ->first();

                return [
                    'nombre' => $trabajador->nombre_completo,
                    'cargo' => $trabajador->cargo,
                    'acepto' => (bool) $aceptacion,
                    'fecha_aceptacion' => $aceptacion?->aceptado_en,
                ];
            })
            ->sortBy('acepto')
            ->values()
            ->all();
    }

    /**
     * @return array{0: int, 1: int} [aceptados, denominador]
     */
    private function calcular(Empresa $empresa): array
    {
        $trabajadoresActivos = $empresa->trabajadores()->where('active', true)->get();
        $denominador = max((int) ($empresa->numero_empleados ?? 0), $trabajadoresActivos->count());

        $aceptados = $trabajadoresActivos->filter(fn ($trabajador) => $trabajador->aceptoRitVigente())->count();

        return [$aceptados, $denominador];
    }
}
