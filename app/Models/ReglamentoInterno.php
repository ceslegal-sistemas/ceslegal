<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReglamentoInterno extends Model
{
    protected $table = 'reglamentos_internos';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'texto_completo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
