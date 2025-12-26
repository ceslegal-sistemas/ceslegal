<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreguntaDescargo extends Model
{
    protected $table = 'preguntas_descargos';

    protected $fillable = [
        'diligencia_descargo_id',
        'pregunta',
        'orden',
        'es_generada_por_ia',
        'pregunta_padre_id',
        'estado',
    ];

    protected $casts = [
        'es_generada_por_ia' => 'boolean',
        'orden' => 'integer',
    ];

    public function diligenciaDescargo(): BelongsTo
    {
        return $this->belongsTo(DiligenciaDescargo::class, 'diligencia_descargo_id');
    }

    public function respuesta(): HasOne
    {
        return $this->hasOne(RespuestaDescargo::class, 'pregunta_descargo_id');
    }

    public function preguntaPadre(): BelongsTo
    {
        return $this->belongsTo(PreguntaDescargo::class, 'pregunta_padre_id');
    }

    public function preguntasHijas(): HasMany
    {
        return $this->hasMany(PreguntaDescargo::class, 'pregunta_padre_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', 'activa');
    }

    public function scopeRespondidas($query)
    {
        return $query->where('estado', 'respondida');
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden', 'asc');
    }
}
