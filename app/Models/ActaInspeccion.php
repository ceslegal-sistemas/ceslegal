<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActaInspeccion extends Model
{
    protected $table = 'actas_inspeccion';

    protected $fillable = [
        'numero_acta',
        'empresa_id',
        'user_id',
        'fecha',
        'hora_inicio',
        'hora_cierre',
        'objetivo',
        'tema',
        'asistentes',
        'compromisos',
        'hallazgos',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'fecha'       => 'date',
        'asistentes'  => 'array',
        'compromisos' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Genera el siguiente número de acta para la empresa: AI-2026-001
     */
    public static function generarNumero(int $empresaId): string
    {
        $año    = now()->year;
        $ultimo = static::where('empresa_id', $empresaId)
            ->whereYear('created_at', $año)
            ->count();

        return 'AI-' . $año . '-' . str_pad($ultimo + 1, 3, '0', STR_PAD_LEFT);
    }

    public function getEstadoBadgeColorAttribute(): string
    {
        return match ($this->estado) {
            'finalizada' => 'success',
            default      => 'warning',
        };
    }
}
