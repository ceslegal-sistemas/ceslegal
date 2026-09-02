<?php

namespace App\Services;

use App\Models\SolicitudContrato;
use Carbon\Carbon;

/**
 * Plazos y renovación de contratos a término fijo (Art. 46 CST, modificado
 * por el Art. 6 de la Ley 2466 de 2025). Deliberadamente NO reutiliza
 * TerminoLegalService: ese servicio calcula en DÍAS HÁBILES (pensado para
 * plazos procesales de descargos), mientras que el preaviso de 30 días de
 * este artículo es en DÍAS CALENDARIO - mezclar ambas lógicas en un mismo
 * servicio arriesgaba romper el cálculo de descargos el día que alguien
 * ajustara uno pensando solo en el otro caso de uso. Decisión confirmada
 * con el usuario.
 */
class PlazoContratoService
{
    /** Días calendario hasta el vencimiento (negativo si ya venció). */
    public function diasHastaVencimiento(SolicitudContrato $solicitud): int
    {
        if (empty($solicitud->fecha_fin_contrato)) {
            return PHP_INT_MAX;
        }

        return (int) now()->startOfDay()->diffInDays(
            Carbon::parse($solicitud->fecha_fin_contrato)->startOfDay(),
            false
        );
    }

    /** Nadie ha decidido formalmente "no renovar" para el período vigente. */
    public function sinDecisionTomada(SolicitudContrato $solicitud): bool
    {
        return is_null($solicitud->decision_no_renovacion_en);
    }

    /**
     * Ventana en la que se le debe avisar al CLIENTE (45 días por defecto) -
     * da margen real para gestionar el preaviso de 30 días que exige la ley.
     */
    public function estaEnVentanaDeAlerta(SolicitudContrato $solicitud, int $diasAlerta = 45): bool
    {
        if (!$this->sinDecisionTomada($solicitud)) {
            return false;
        }

        $dias = $this->diasHastaVencimiento($solicitud);

        return $dias >= 0 && $dias <= $diasAlerta;
    }

    /**
     * true cuando ya se cumplió el plazo LEGAL (30 días antes del
     * vencimiento) sin que nadie haya decidido no renovar - en ese momento,
     * conforme al Art. 46 CST, el contrato YA se entiende renovado
     * automáticamente (no hay que esperar a la fecha de vencimiento misma).
     */
    public function yaVencioPlazoLegalSinDecision(SolicitudContrato $solicitud): bool
    {
        if (!$this->sinDecisionTomada($solicitud)) {
            return false;
        }

        return $this->diasHastaVencimiento($solicitud) <= 30;
    }

    /**
     * Calcula la próxima renovación aplicando las 3 reglas del Art. 46 CST:
     *  1. Se repite la misma duración del período que se vence ("mismo
     *     período").
     *  2. A partir de la 4a prórroga, la renovación no puede ser inferior a
     *     1 año (se aplica como un mínimo, nunca acorta un período que ya
     *     seria más largo).
     *  3. En ningún caso el contrato puede superar 4 años desde su fecha de
     *     inicio ORIGINAL (fecha_inicio_propuesta, no el período vigente).
     *
     * @return array{puede_renovar: bool, nueva_fecha_inicio: Carbon, nueva_fecha_fin: Carbon, excede_tope_4_anios: bool}
     */
    public function calcularProximaRenovacion(SolicitudContrato $solicitud): array
    {
        $inicioPeriodoActual = Carbon::parse($solicitud->fecha_inicio_periodo_actual);
        $finPeriodoActual    = Carbon::parse($solicitud->fecha_fin_contrato);
        $duracionActualDias  = $inicioPeriodoActual->diffInDays($finPeriodoActual);
        $numeroProrroga      = $solicitud->veces_prorrogado + 1;

        $nuevoInicio = $finPeriodoActual->copy()->addDay();

        $finMismoPeriodo = $nuevoInicio->copy()->addDays($duracionActualDias);

        if ($numeroProrroga >= 4) {
            $finMinimoUnAnio = $nuevoInicio->copy()->addYear()->subDay();
            $nuevoFin = $finMismoPeriodo->lt($finMinimoUnAnio) ? $finMinimoUnAnio : $finMismoPeriodo;
        } else {
            $nuevoFin = $finMismoPeriodo;
        }

        $inicioOriginal = Carbon::parse($solicitud->fecha_inicio_propuesta ?? $inicioPeriodoActual);
        $topeMaximo     = $inicioOriginal->copy()->addYears(4);
        $excedeTope     = $nuevoFin->gt($topeMaximo);

        return [
            'puede_renovar'       => !$excedeTope,
            'nueva_fecha_inicio'  => $nuevoInicio,
            'nueva_fecha_fin'     => $nuevoFin,
            'excede_tope_4_anios' => $excedeTope,
        ];
    }

    /**
     * Aplica la renovación automática si es segura (dentro del tope de 4
     * años); si no, NO renueva - marca requiere_revision_manual_renovacion
     * para que una persona lo resuelva (caso raro, de alto riesgo legal).
     */
    public function aplicarRenovacionAutomatica(SolicitudContrato $solicitud): void
    {
        $calculo = $this->calcularProximaRenovacion($solicitud);

        if (!$calculo['puede_renovar']) {
            $solicitud->forceFill(['requiere_revision_manual_renovacion' => true])->save();
            return;
        }

        $solicitud->forceFill([
            'fecha_inicio_periodo_actual'  => $calculo['nueva_fecha_inicio'],
            'fecha_fin_contrato'           => $calculo['nueva_fecha_fin'],
            'veces_prorrogado'             => $solicitud->veces_prorrogado + 1,
            'renovado_automaticamente_en'  => now(),
            // Nuevo período -> nueva ventana de alerta de 45 días propia.
            'notificado_vencimiento_en'    => null,
        ])->save();
    }
}
