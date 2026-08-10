<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudCambioEmpresa extends Model
{
    protected $table = 'solicitudes_cambio_empresa';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'mensaje',
        'estado',
        'motivo_rechazo',
        'resuelto_por',
        'resuelto_en',
    ];

    protected $casts = [
        'resuelto_en' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }
}
