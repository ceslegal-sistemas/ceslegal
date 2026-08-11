<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SancionProcessEvent extends Model
{
    protected $fillable = [
        'proceso_id',
        'user_id',
        'event_type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
