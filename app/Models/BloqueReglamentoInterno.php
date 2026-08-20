<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloqueReglamentoInterno extends Model
{
    protected $table = 'bloques_reglamento_interno';

    protected $fillable = ['reglamento_interno_id', 'orden', 'contenido', 'embedding'];

    protected $casts = [
        'embedding' => 'array',
        'orden'     => 'integer',
    ];

    public function reglamentoInterno(): BelongsTo
    {
        return $this->belongsTo(ReglamentoInterno::class);
    }
}
