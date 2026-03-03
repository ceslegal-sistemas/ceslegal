<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformeJuridico extends Model
{
    protected $table = 'informes_juridicos';

    protected $fillable = [
        'codigo',
        'empresa_id',
        'created_by',
        'anio',
        'mes',
        'fecha_gestion',
        'area_practica_id',
        'area_practica_otro',
        'tipo_gestion_id',
        'tipo_gestion_otro',
        'subtipo_id',
        'subtipo_otro',
        'descripcion',
        'estado',
        'observacion',
        'tiempo_minutos',
        'adjuntos',
    ];

    protected $casts = [
        'anio'          => 'integer',
        'tiempo_minutos' => 'integer',
        'fecha_gestion' => 'date',
        'adjuntos'      => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function areaPractica(): BelongsTo
    {
        return $this->belongsTo(AreaPractica::class, 'area_practica_id');
    }

    public function tipoGestion(): BelongsTo
    {
        return $this->belongsTo(TipoGestion::class, 'tipo_gestion_id');
    }

    public function subtipo(): BelongsTo
    {
        return $this->belongsTo(SubtipoGestion::class, 'subtipo_id');
    }

    public function getMesTextoAttribute(): string
    {
        return match ($this->mes) {
            'enero' => 'Enero',
            'febrero' => 'Febrero',
            'marzo' => 'Marzo',
            'abril' => 'Abril',
            'mayo' => 'Mayo',
            'junio' => 'Junio',
            'julio' => 'Julio',
            'agosto' => 'Agosto',
            'septiembre' => 'Septiembre',
            'octubre' => 'Octubre',
            'noviembre' => 'Noviembre',
            'diciembre' => 'Diciembre',
            default => $this->mes,
        };
    }

    public function getAreaPracticaTextoAttribute(): string
    {
        if ($this->area_practica_otro) {
            return $this->area_practica_otro;
        }

        return $this->areaPractica?->nombre ?? 'Sin área';
    }

    public function getTipoGestionTextoAttribute(): string
    {
        if ($this->tipo_gestion_otro) {
            return $this->tipo_gestion_otro;
        }

        return $this->tipoGestion?->nombre ?? 'Sin tipo';
    }

    public function getSubtipoTextoAttribute(): ?string
    {
        if ($this->subtipo_otro) {
            return $this->subtipo_otro;
        }

        return $this->subtipo?->nombre;
    }

    public function getEstadoTextoAttribute(): string
    {
        return match ($this->estado) {
            'entregado' => 'Entregado',
            'pendiente' => 'Pendiente',
            'en_proceso' => 'En Proceso',
            'realizado' => 'Realizado',
            default => $this->estado,
        };
    }

    public function getTiempoFormateadoAttribute(): string
    {
        $horas = intdiv($this->tiempo_minutos, 60);
        $minutos = $this->tiempo_minutos % 60;

        if ($horas > 0) {
            return "{$horas}h {$minutos}m";
        }

        return "{$minutos} min";
    }
}
