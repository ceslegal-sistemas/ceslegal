<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GapReporte extends Model
{
    protected $table = 'gap_reportes';

    protected $fillable = [
        'auditoria_rit_id',
        'empresa_id',
        'estado',
        'ruta_ejecutivo',
        'ruta_tecnico',
        'score_snapshot',
        'mensaje_error',
    ];

    protected $casts = [
        'score_snapshot' => 'integer',
    ];

    public function auditoria(): BelongsTo
    {
        return $this->belongsTo(AuditoriaRIT::class, 'auditoria_rit_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function estaGenerando(): bool
    {
        return $this->estado === 'generando';
    }

    public function estaListo(): bool
    {
        return $this->estado === 'completado'
            && !empty($this->ruta_ejecutivo)
            && !empty($this->ruta_tecnico);
    }

    public function falloGeneracion(): bool
    {
        return $this->estado === 'error';
    }
}
