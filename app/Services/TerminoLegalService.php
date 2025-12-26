<?php

namespace App\Services;

use App\Models\DiaNoHabil;
use App\Models\TerminoLegal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TerminoLegalService
{
    private Collection $diasNoHabiles;

    public function __construct()
    {
        // Cargar todos los días no hábiles en memoria para mejor performance
        $this->diasNoHabiles = DiaNoHabil::pluck('fecha')->map(fn($fecha) => Carbon::parse($fecha)->format('Y-m-d'));
    }

    /**
     * Calcula la fecha de vencimiento sumando días hábiles a una fecha inicial
     */
    public function calcularFechaVencimiento(Carbon $fechaInicio, int $diasHabiles): Carbon
    {
        $fechaActual = $fechaInicio->copy();
        $diasSumados = 0;

        while ($diasSumados < $diasHabiles) {
            $fechaActual->addDay();

            if ($this->esDiaHabil($fechaActual)) {
                $diasSumados++;
            }
        }

        return $fechaActual;
    }

    /**
     * Verifica si una fecha es día hábil (no es fin de semana ni festivo)
     */
    public function esDiaHabil(Carbon $fecha): bool
    {
        // Verificar si es fin de semana
        if ($fecha->isWeekend()) {
            return false;
        }

        // Verificar si es día no hábil (festivo)
        $fechaStr = $fecha->format('Y-m-d');
        return !$this->diasNoHabiles->contains($fechaStr);
    }

    /**
     * Calcula los días hábiles transcurridos entre dos fechas
     */
    public function calcularDiasHabilesTranscurridos(Carbon $fechaInicio, Carbon $fechaFin): int
    {
        $fechaActual = $fechaInicio->copy();
        $diasHabiles = 0;

        while ($fechaActual->lte($fechaFin)) {
            if ($this->esDiaHabil($fechaActual)) {
                $diasHabiles++;
            }
            $fechaActual->addDay();
        }

        return $diasHabiles;
    }

    /**
     * Calcula los días hábiles restantes hasta una fecha de vencimiento
     */
    public function calcularDiasHabilesRestantes(Carbon $fechaVencimiento): int
    {
        $hoy = Carbon::now();

        if ($hoy->gte($fechaVencimiento)) {
            return 0; // Ya venció
        }

        return $this->calcularDiasHabilesTranscurridos($hoy, $fechaVencimiento);
    }

    /**
     * Crea un nuevo término legal para un proceso
     */
    public function crearTermino(
        string $procesoTipo,
        int $procesoId,
        string $terminoTipo,
        Carbon $fechaInicio,
        int $diasHabiles,
        ?string $observaciones = null
    ): TerminoLegal {
        $fechaVencimiento = $this->calcularFechaVencimiento($fechaInicio, $diasHabiles);

        return TerminoLegal::create([
            'proceso_tipo' => $procesoTipo,
            'proceso_id' => $procesoId,
            'termino_tipo' => $terminoTipo,
            'fecha_inicio' => $fechaInicio,
            'dias_habiles' => $diasHabiles,
            'fecha_vencimiento' => $fechaVencimiento,
            'dias_transcurridos' => 0,
            'estado' => 'activo',
            'observaciones' => $observaciones,
        ]);
    }

    /**
     * Actualiza el estado de todos los términos activos
     * Debe ejecutarse diariamente mediante un comando
     */
    public function actualizarTerminos(): void
    {
        $terminosActivos = TerminoLegal::where('estado', 'activo')->get();
        $hoy = Carbon::now();

        foreach ($terminosActivos as $termino) {
            $diasTranscurridos = $this->calcularDiasHabilesTranscurridos(
                Carbon::parse($termino->fecha_inicio),
                $hoy
            );

            $termino->dias_transcurridos = $diasTranscurridos;

            // Verificar si ya venció
            if ($hoy->gte(Carbon::parse($termino->fecha_vencimiento))) {
                $termino->estado = 'vencido';
            }

            $termino->save();
        }
    }

    /**
     * Cierra un término legal manualmente
     */
    public function cerrarTermino(TerminoLegal $termino, ?string $observaciones = null): void
    {
        $termino->update([
            'estado' => 'cerrado',
            'fecha_cierre' => now(),
            'observaciones' => $observaciones ?? $termino->observaciones,
        ]);
    }

    /**
     * Obtiene los términos próximos a vencer (en los próximos N días hábiles)
     */
    public function getTerminosProximosVencer(int $diasAlerta = 2): Collection
    {
        return TerminoLegal::where('estado', 'activo')
            ->get()
            ->filter(function ($termino) use ($diasAlerta) {
                $diasRestantes = $this->calcularDiasHabilesRestantes(
                    Carbon::parse($termino->fecha_vencimiento)
                );
                return $diasRestantes > 0 && $diasRestantes <= $diasAlerta;
            });
    }

    /**
     * Obtiene los términos vencidos
     */
    public function getTerminosVencidos(): Collection
    {
        return TerminoLegal::where('estado', 'vencido')->get();
    }

    /**
     * Verifica si un término específico está vencido
     */
    public function estaVencido(TerminoLegal $termino): bool
    {
        return Carbon::now()->gte(Carbon::parse($termino->fecha_vencimiento));
    }

    /**
     * Obtiene información detallada de un término
     */
    public function getInfoTermino(TerminoLegal $termino): array
    {
        $hoy = Carbon::now();
        $fechaVencimiento = Carbon::parse($termino->fecha_vencimiento);

        return [
            'dias_habiles_totales' => $termino->dias_habiles,
            'dias_transcurridos' => $this->calcularDiasHabilesTranscurridos(
                Carbon::parse($termino->fecha_inicio),
                $hoy
            ),
            'dias_restantes' => $this->calcularDiasHabilesRestantes($fechaVencimiento),
            'porcentaje_transcurrido' => round(($termino->dias_transcurridos / $termino->dias_habiles) * 100, 2),
            'esta_vencido' => $this->estaVencido($termino),
            'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d'),
            'estado' => $termino->estado,
        ];
    }
}
