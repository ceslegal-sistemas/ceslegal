<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespuestaDescargo extends Model
{
    protected $table = 'respuestas_descargos';

    protected $fillable = [
        'pregunta_descargo_id',
        'respuesta',
        'respondido_en',
        'archivos_adjuntos',
        'fue_pegada',
        'tiempo_respuesta_segundos',
        'cambios_pestana_durante',
    ];

    protected $casts = [
        'respondido_en'             => 'datetime',
        'archivos_adjuntos'         => 'array',
        'fue_pegada'                => 'boolean',
        'tiempo_respuesta_segundos' => 'integer',
        'cambios_pestana_durante'   => 'integer',
    ];

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(PreguntaDescargo::class, 'pregunta_descargo_id');
    }

    public function tieneContenido(): bool
    {
        return !empty(trim($this->respuesta));
    }

    public function cumpleLongitudMinima(int $caracteres = 10): bool
    {
        return strlen(trim($this->respuesta)) >= $caracteres;
    }
}
