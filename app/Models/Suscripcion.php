<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suscripcion extends Model
{
    protected $table = 'suscripciones';

    protected $fillable = [
        'empresa_id',
        'plan',
        'ciclo_facturacion',
        'estado',
        'trial_ends_at',
        'fecha_inicio',
        'fecha_fin',
        'payment_reference',
        'payment_transaction_id',
        'monto_pagado',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'fecha_inicio'  => 'datetime',
        'fecha_fin'     => 'datetime',
        'monto_pagado'  => 'decimal:2',
        'estado'        => 'string',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function estaActiva(): bool
    {
        if ($this->estado === 'trial') {
            return $this->trial_ends_at !== null && $this->trial_ends_at->gt(now());
        }

        return $this->estado === 'activa'
            && ($this->fecha_fin === null || $this->fecha_fin->gt(now()));
    }

    public function enTrial(): bool
    {
        return $this->estado === 'trial'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->gt(now());
    }
}
