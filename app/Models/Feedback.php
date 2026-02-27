<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'calificacion',
        'sugerencia',
        'tipo',
        'proceso_disciplinario_id',
        'diligencia_descargo_id',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'calificacion' => 'integer',
    ];

    public const TIPO_DESCARGO_TRABAJADOR = 'descargo_trabajador';
    public const TIPO_DESCARGO_REGISTRO = 'descargo_registro';
    public const TIPO_PLATAFORMA_GENERAL = 'plataforma_general';

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
            self::TIPO_DESCARGO_REGISTRO => 'Registro de proceso (Admin)',
            self::TIPO_PLATAFORMA_GENERAL => 'Plataforma general',
            default => $this->tipo,
        };
    }
}
