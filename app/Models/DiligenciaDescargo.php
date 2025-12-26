<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiligenciaDescargo extends Model
{
    protected $table = 'diligencias_descargos';

    protected $fillable = [
        'proceso_id',
        'fecha_diligencia',
        'lugar_diligencia',
        'trabajador_asistio',
        'motivo_inasistencia',
        'acompanante_nombre',
        'acompanante_cargo',
        'preguntas_formuladas',
        'respuestas',
        'pruebas_aportadas',
        'descripcion_pruebas',
        'observaciones',
        'acta_generada',
        'ruta_acta',
        'token_acceso',
        'token_expira_en',
        'acceso_habilitado',
        'fecha_acceso_permitida',
        'trabajador_accedio_en',
        'primer_acceso_en',
        'tiempo_limite',
        'tiempo_expirado',
        'ip_acceso',
    ];

    protected $casts = [
        'fecha_diligencia' => 'datetime',
        'trabajador_asistio' => 'boolean',
        'preguntas_formuladas' => 'array',
        'respuestas' => 'array',
        'pruebas_aportadas' => 'boolean',
        'acta_generada' => 'boolean',
        'token_expira_en' => 'datetime',
        'acceso_habilitado' => 'boolean',
        'fecha_acceso_permitida' => 'date',
        'trabajador_accedio_en' => 'datetime',
        'primer_acceso_en' => 'datetime',
        'tiempo_limite' => 'datetime',
        'tiempo_expirado' => 'boolean',
    ];

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
    }

    public function preguntas()
    {
        return $this->hasMany(PreguntaDescargo::class, 'diligencia_descargo_id')->ordenadas();
    }

    public function trazabilidadIA()
    {
        return $this->hasMany(TrazabilidadIADescargo::class, 'diligencia_descargo_id');
    }

    public function generarTokenAcceso(): string
    {
        $this->token_acceso = bin2hex(random_bytes(32));
        $this->token_expira_en = now()->addDays(6);
        $this->save();

        return $this->token_acceso;
    }

    public function tokenEsValido(): bool
    {
        if (!$this->token_acceso || !$this->token_expira_en) {
            return false;
        }

        if (now()->greaterThan($this->token_expira_en)) {
            return false;
        }

        if (!$this->acceso_habilitado) {
            return false;
        }

        return true;
    }

    public function puedeAccederHoy(): bool
    {
        if (!$this->fecha_acceso_permitida) {
            return false;
        }

        return now()->toDateString() === $this->fecha_acceso_permitida->toDateString();
    }

    // Métodos para el timer de 45 minutos
    public function iniciarTimer()
    {
        if (!$this->primer_acceso_en) {
            $this->update([
                'primer_acceso_en' => now(),
                'tiempo_limite' => now()->addMinutes(45),
                'tiempo_expirado' => false,
            ]);
        }
    }

    public function tiempoRestante(): ?int
    {
        if (!$this->tiempo_limite) {
            return null;
        }

        $segundos = now()->diffInSeconds($this->tiempo_limite, false);
        return $segundos > 0 ? $segundos : 0;
    }

    public function tiempoHaExpirado(): bool
    {
        if (!$this->tiempo_limite) {
            return false;
        }

        return now()->greaterThan($this->tiempo_limite);
    }

    public function marcarTiempoExpirado()
    {
        $this->update(['tiempo_expirado' => true]);
    }
}
