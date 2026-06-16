<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurisprudencia extends Model
{
    protected $table = 'jurisprudencias';

    protected $fillable = [
        'referencia',
        'tipo',
        'corporacion',
        'fecha',
        'magistrado',
        'tema',
        'tesis',
        'texto_completo',
        'embedding',
        'estado',
        'error_mensaje',
        'total_palabras',
        'activo',
    ];

    protected $casts = [
        'fecha'          => 'date',
        'embedding'      => 'array',
        'total_palabras' => 'integer',
        'activo'         => 'boolean',
    ];

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeProcesadas($query)
    {
        return $query->where('estado', 'procesado');
    }

    /** Texto que se indexa/embebe y se inyecta a la IA: tema + tesis. */
    public function textoIndexable(): string
    {
        return trim(($this->tema ? $this->tema . '. ' : '') . ($this->tesis ?? ''));
    }
}
