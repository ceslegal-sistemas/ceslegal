<?php

namespace App\Services;

use App\Models\ProcesoDisciplinario;
use Carbon\Carbon;

class DisponibilidadHelper
{
    // Horario de oficina
    const HORA_INICIO = '08:00';
    const HORA_FIN = '17:00';
    const DURACION_DESCARGO = 45; // minutos

    // Horario de almuerzo (no disponible)
    const ALMUERZO_INICIO = '12:00';
    const ALMUERZO_FIN = '13:00';

    /**
     * Obtiene los slots disponibles para un abogado en una fecha específica
     */
    public static function obtenerSlotsDisponibles(int $abogadoId, string $fecha, string $modalidad, ?int $procesoIdExcluir = null): array
    {
        $fechaCarbon = Carbon::parse($fecha);

        // Verificar que sea día laboral (lunes a viernes)
        if ($fechaCarbon->isWeekend()) {
            return [];
        }

        // Generar todos los slots posibles del día
        $todosLosSlots = self::generarSlotsDia($fechaCarbon);

        // Obtener slots ocupados
        $slotsOcupados = self::obtenerSlotsOcupados($abogadoId, $fecha, $modalidad, $procesoIdExcluir);

        // Filtrar slots disponibles
        $slotsDisponibles = [];
        foreach ($todosLosSlots as $slot) {
            if (!self::slotEstaOcupado($slot, $slotsOcupados)) {
                $slotsDisponibles[] = $slot;
            }
        }

        return $slotsDisponibles;
    }

    /**
     * Genera todos los slots posibles de 45 minutos en un día
     */
    protected static function generarSlotsDia(Carbon $fecha): array
    {
        $slots = [];
        $horaActual = Carbon::parse($fecha->format('Y-m-d') . ' ' . self::HORA_INICIO);
        $horaFin = Carbon::parse($fecha->format('Y-m-d') . ' ' . self::HORA_FIN);
        $almuerzoInicio = Carbon::parse($fecha->format('Y-m-d') . ' ' . self::ALMUERZO_INICIO);
        $almuerzoFin = Carbon::parse($fecha->format('Y-m-d') . ' ' . self::ALMUERZO_FIN);

        while ($horaActual->copy()->addMinutes(self::DURACION_DESCARGO)->lessThanOrEqualTo($horaFin)) {
            $horaFinSlot = $horaActual->copy()->addMinutes(self::DURACION_DESCARGO);

            // Verificar si el slot NO se solapa con horario de almuerzo
            if (!self::slotEnHorarioAlmuerzo($horaActual, $horaFinSlot, $almuerzoInicio, $almuerzoFin)) {
                $slots[] = [
                    'inicio' => $horaActual->format('H:i'),
                    'fin' => $horaFinSlot->format('H:i'),
                    'datetime_inicio' => $horaActual->format('Y-m-d H:i:s'),
                    'datetime_fin' => $horaFinSlot->format('Y-m-d H:i:s'),
                ];
            }

            $horaActual->addMinutes(self::DURACION_DESCARGO);
        }

        return $slots;
    }

    /**
     * Verifica si un slot se solapa con el horario de almuerzo
     */
    protected static function slotEnHorarioAlmuerzo(Carbon $slotInicio, Carbon $slotFin, Carbon $almuerzoInicio, Carbon $almuerzoFin): bool
    {
        // Si el slot comienza antes del fin del almuerzo Y termina después del inicio del almuerzo = solapamiento
        return $slotInicio->lessThan($almuerzoFin) && $slotFin->greaterThan($almuerzoInicio);
    }

    /**
     * Obtiene los slots ocupados por procesos ya programados
     */
    protected static function obtenerSlotsOcupados(int $abogadoId, string $fecha, string $modalidad, ?int $procesoIdExcluir = null): array
    {
        $query = ProcesoDisciplinario::where('abogado_id', $abogadoId)
            ->whereDate('fecha_descargos_programada', $fecha)
            ->whereIn('modalidad_descargos', [$modalidad, 'ambos'])
            ->whereNotNull('fecha_descargos_programada');

        if ($procesoIdExcluir) {
            $query->where('id', '!=', $procesoIdExcluir);
        }

        $procesosOcupados = $query->get();

        $slotsOcupados = [];
        foreach ($procesosOcupados as $proceso) {
            $inicio = Carbon::parse($proceso->fecha_descargos_programada);
            $fin = $inicio->copy()->addMinutes(self::DURACION_DESCARGO);

            $slotsOcupados[] = [
                'inicio' => $inicio->format('H:i'),
                'fin' => $fin->format('H:i'),
                'datetime_inicio' => $inicio->format('Y-m-d H:i:s'),
                'datetime_fin' => $fin->format('Y-m-d H:i:s'),
            ];
        }

        return $slotsOcupados;
    }

    /**
     * Verifica si un slot está ocupado
     */
    protected static function slotEstaOcupado(array $slot, array $slotsOcupados): bool
    {
        $slotInicio = Carbon::parse($slot['datetime_inicio']);
        $slotFin = Carbon::parse($slot['datetime_fin']);

        foreach ($slotsOcupados as $ocupado) {
            $ocupadoInicio = Carbon::parse($ocupado['datetime_inicio']);
            $ocupadoFin = Carbon::parse($ocupado['datetime_fin']);

            // Verificar si hay solapamiento
            if ($slotInicio->lessThan($ocupadoFin) && $slotFin->greaterThan($ocupadoInicio)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Formatea los slots para mostrar en el selector
     */
    public static function formatearSlotsParaSelector(array $slots): array
    {
        $opciones = [];
        foreach ($slots as $index => $slot) {
            $key = $slot['datetime_inicio']; // Usar datetime como key
            $label = sprintf('%s - %s', $slot['inicio'], $slot['fin']);
            $opciones[$key] = $label;
        }

        return $opciones;
    }
}
