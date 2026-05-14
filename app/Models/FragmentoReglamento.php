<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FragmentoReglamento extends Model
{
    protected $table = 'fragmentos_reglamento_interno';

    protected $fillable = ['reglamento_interno_id', 'orden', 'contenido', 'embedding'];

    protected $casts = [
        'embedding' => 'array',
        'orden'     => 'integer',
    ];

    public function reglamento()
    {
        return $this->belongsTo(ReglamentoInterno::class, 'reglamento_interno_id');
    }
}
