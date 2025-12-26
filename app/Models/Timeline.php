<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timeline extends Model
{
    public $timestamps = false;

    protected $table = 'timeline';

    protected $fillable = [
        'proceso_tipo',
        'proceso_id',
        'user_id',
        'accion',
        'descripcion',
        'estado_anterior',
        'estado_nuevo',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function proceso()
    {
        if ($this->proceso_tipo === 'proceso_disciplinario') {
            return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
        } else {
            return $this->belongsTo(SolicitudContrato::class, 'proceso_id');
        }
    }
}
