<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'calificacion',
        'nps_score',
        'sugerencia',
        'respuestas_adicionales',
        'tipo',
        'trigger',
        'proceso_disciplinario_id',
        'diligencia_descargo_id',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'calificacion'           => 'integer',
        'nps_score'              => 'integer',
        'respuestas_adicionales' => 'array',
    ];

    // Tipos de feedback
    public const TIPO_DESCARGO_TRABAJADOR = 'descargo_trabajador';
    public const TIPO_DESCARGO_REGISTRO   = 'descargo_registro';
    public const TIPO_PLATAFORMA_GENERAL  = 'plataforma_general';

    // Triggers de feedback (contexto que disparó el modal)
    public const TRIGGER_PRIMER_PROCESO  = 'primer_proceso';
    public const TRIGGER_POST_DILIGENCIA = 'post_diligencia';
    public const TRIGGER_PERIODICO       = 'periodico';
    public const TRIGGER_HITO            = 'hito';

    public function procesoDisciplinario(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class);
    }

    public function diligenciaDescargo(): BelongsTo
    {
        return $this->belongsTo(DiligenciaDescargo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getCalificacionTextAttribute(): string
    {
        return match ($this->calificacion) {
            1 => 'Muy malo',
            2 => 'Malo',
            3 => 'Regular',
            4 => 'Bueno',
            5 => 'Excelente',
            default => 'Sin calificar',
        };
    }

    public function getTipoTextAttribute(): string
    {
        return match ($this->tipo) {
            self::TIPO_DESCARGO_TRABAJADOR => 'Diligencia de descargos (Trabajador)',
            self::TIPO_DESCARGO_REGISTRO   => 'Registro de proceso (Admin)',
            self::TIPO_PLATAFORMA_GENERAL  => 'Plataforma general',
            default                        => $this->tipo,
        };
    }

    public function getTriggerTextAttribute(): string
    {
        return match ($this->trigger) {
            self::TRIGGER_PRIMER_PROCESO  => 'Primer proceso',
            self::TRIGGER_POST_DILIGENCIA => 'Post diligencia',
            self::TRIGGER_PERIODICO       => 'Periódico',
            self::TRIGGER_HITO            => 'Hito de uso',
            default                       => $this->trigger ?? '—',
        };
    }

    /**
     * Categoría NPS: Promotor (9-10), Neutro (7-8), Detractor (0-6)
     */
    public function getNpsCategoria(): ?string
    {
        if ($this->nps_score === null) {
            return null;
        }
        if ($this->nps_score >= 9) {
            return 'Promotor';
        }
        if ($this->nps_score >= 7) {
            return 'Neutro';
        }
        return 'Detractor';
    }
}
