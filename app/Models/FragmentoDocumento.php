<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FragmentoDocumento extends Model
{
    protected $table = 'fragmentos_documento';

    protected $fillable = [
        'documento_legal_id',
        'orden',
        'contenido',
        'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
        'orden'     => 'integer',
    ];

    public function documentoLegal(): BelongsTo
    {
        return $this->belongsTo(DocumentoLegal::class);
    }

    public function tieneEmbedding(): bool
    {
        return !empty($this->embedding);
    }
}
